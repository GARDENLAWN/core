<?php

namespace GardenLawn\Core\Cron;

use Aws\S3\S3Client;
use Exception;
use GardenLawn\Core\Utils\Logger;
use GardenLawn\Core\Utils\Utils;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class AwsS3Sync
{
    private const int MAX_SIZE = 1600;
    protected ObjectManager $objectManager;
    protected S3Client $s3client;
    protected AdapterInterface $connection;
    private bool $isTest = false;

    public function __construct()
    {
        $this->objectManager = ObjectManager::getInstance();
        $this->s3client = Utils::getS3Client();
        $resource = $this->objectManager->get('Magento\Framework\App\ResourceConnection');
        $this->connection = $resource->getConnection();
    }

    /**
     * @throws Exception
     */
    public function execute(): void
    {

        $this->moveProductImages();
        $this->deleteTmpImages();
        $this->mediaGalleryExecute();
        $this->mediaGalleryExecute("pub/media/catalog/product");
        $this->mediaGalleryExecute("pub/media/gallery");
        $this->producersExecute();
        $this->productsExecute();
    }

    public function deleteTmpImages(): void
    {
        $images = $this->getMediaFiles('pub');
        foreach ($images as $key) {
            if (substr_count($key, '/') == 1 && Utils::isImage($key, true)) {
                $this->s3client->deleteObject([
                    'Bucket' => Utils::Bucket,
                    'Key' => $key
                ]);
            }
        }
    }

    public function moveProductImages(): void
    {
        $images = $this->getMediaFiles('pub/pub');
        foreach ($images as $key) {
            if (Utils::isImage($key, true)) {
                $to = str_replace('pub/pub/', 'pub/', $key);
                Logger::writeLog("Copy from $key to $to");
                $this->s3client->copyObject([
                    'Bucket' => Utils::Bucket,
                    'CopySource' => Utils::Bucket . '/' . $key,
                    'Key' => $to
                ]);
                $this->s3client->deleteObject([
                    'Bucket' => Utils::Bucket,
                    'Key' => $key
                ]);
            }
        }
    }

    public function getMediaFiles(string $prefix): array
    {
        $contents = $this->s3client->listObjectsV2([
            'Bucket' => Utils::Bucket,
            'Prefix' => $prefix
        ]);

        $dirs = [];
        $dirs [] = $prefix;

        if ($contents['Contents'] != null) {
            foreach ($contents['Contents'] as $content) {
                if (str_ends_with($content['Key'], '/')) {
                    $dirs[] = $content['Key'];
                }
            }
        }

        $images = [];

        foreach ($dirs as $key => $dir) {
            $result = $this->s3client->listObjectsV2([
                'Bucket' => Utils::Bucket,
                'Prefix' => $dir
            ]);

            if ($result['Contents'] != null) {
                foreach ($result['Contents'] as $content) {
                    $path = $content['Key'];
                    $images[] = $path;
                }
            }

            $images = array_merge($images, array_unique($images));
        }

        return array_unique($images);
    }

    public function worksExecute(): void
    {
        try {
            $this->isTest = false;
            $images = $this->getMediaFiles('pub/media/gallery');
            $this->imagesExecute($images, true, null);
        } catch (Exception $e) {
            Logger::writeLog($e);
        }
    }

    public function mediaGalleryExecute(string $path = "pub/media"): void
    {
        try {
            $this->isTest = false;

            $mediaUrl = Utils::getMediaUrl();
            $path = str_replace($mediaUrl, '', $path);
            $images = $this->getMediaFiles($path);

            $paths = Utils::getMediaGalleryAssetPaths();

            foreach ($images as $key) {
                $path = str_replace('pub/media/', '', $key);
                if (!in_array($path, $paths)) {
                    $fullPath = $mediaUrl . $path;
                    $path_parts = pathinfo($fullPath);
                    if (array_key_exists('extension', $path_parts)) {
                        $extension = $path_parts['extension'];
                        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp']) &&
                            !str_contains($key, 'cache')) {
                            $this->insertToMediaGalleryAsset($path);
                        }
                    }
                }
            }

            $paths = Utils::getMediaGalleryAssetPaths();
            $dirs = [];

            foreach ($paths as $path) {
                $dirs [] = dirname($path);
            }

            $dirs = array_unique($dirs);
            foreach ($dirs as $dir) {
                $insertSql = "INSERT INTO gardenlawn_mediagallery (name) SELECT '" . $dir . "' WHERE NOT EXISTS (SELECT * FROM gardenlawn_mediagallery WHERE name = '" . $dir . "')";
                $this->connection->query($insertSql);
            }

            $assetsToUpdate = Utils::getMediaGalleryAssetWithoutMediaGallery();
            foreach ($assetsToUpdate as $asset) {
                $updateSql = "UPDATE media_gallery_asset SET mediagallery_id = (SELECT m.id FROM gardenlawn_mediagallery m WHERE m.name = '" . dirname($asset['path']) . "') WHERE id = " . $asset['id'];
                $this->connection->query($updateSql);
            }

            $this->worksExecute();
        } catch (Exception $e) {
            Logger::writeLog($e);
        }
    }

    /**
     * @throws NoSuchEntityException
     */
    private function imagesExecute(array $images, bool $crop, string|null $galleryKey): void
    {
        $mediaUrl = Utils::getMediaUrl();
        $paths = $this->isTest ? [] : Utils::getMediaGalleryAssetPaths();
        foreach ($images as $key) {
            $path = str_replace('pub/media/', '', str_replace($mediaUrl, '', $key));
            $fullPath = $mediaUrl . $path;
            if (!in_array($path, $paths)) {
                $path_parts = pathinfo($fullPath);
                if (array_key_exists('extension', $path_parts)) {
                    $dirname = $path_parts['dirname'];
                    $filename = $path_parts['filename'];

                    if (str_contains($path, '.thumbs')) {
                        $this->insertToMediaGalleryAsset($mediaUrl . $path);
                    } elseif (Utils::isImage($fullPath) &&
                        !str_contains($path, '.thumbs') &&
                        !str_contains($path, 'cache')) {
                        $this->insertToMediaGalleryAsset($mediaUrl . $path);
                        $imgTo = $dirname . '/' . $filename;
                        $this->createImage($fullPath, $imgTo, $crop, $galleryKey);
                    }
                }
            }
        }
    }

    /**
     * @throws NoSuchEntityException
     */
    private function insertToMediaGalleryAsset($path): void
    {
        if ($this->isTest) {
            return;
        }

        $mediaUrl = Utils::getMediaUrl();
        $path = str_replace('pub/media/', '', str_replace($mediaUrl, '', $path));
        $fullPath = $mediaUrl . str_replace(' ', '+', $path);

        $select = "SELECT * FROM media_gallery_asset WHERE path = '" . $path . "'";
        $result = $this->connection->fetchAll($select);

        if (count($result) > 0) {
            return;
        }

        $path_parts = pathinfo($fullPath);
        $title = str_replace('_', ' ', $path_parts['filename']);
        try {
            $info = getimagesize($fullPath);
            $mime = $info['mime'];
            $width = $info[0];
            $height = $info[1];
            $size = get_headers($fullPath, true)["Content-Length"];
            $hash = hash_hmac_file('sha256', $fullPath, 'secret');
            $insertSql = 'INSERT INTO media_gallery_asset(path, title, description, source, hash, content_type, width, height, size)
                SELECT "' . $path . '","' . $title . '",null , "Local", "' . $hash . '","' . $mime . '",' . $width . ',' . $height . ',' . $size;

            $this->connection->query($insertSql);
        } catch (\Http\Client\Exception) {

        }
    }

    /**
     * @throws NoSuchEntityException
     */
    private function createImage(string $imgFrom, string $imgTo, bool $crop = false, $galleryKey = null): void
    {
        $mediaUrl = Utils::getMediaUrl();

        $imageWebP = $imgTo . '.webp';
        $path = str_replace($mediaUrl, '', $imageWebP);
        $key = 'pub/media/' . $path;

        if (Utils::getMediaGalleryAsset($path)) {
            return;
        }

        $image = $this->createImageAny($imgFrom, $crop, $galleryKey);

        //Put webp image to S3
        ob_start();
        imagepalettetotruecolor($image);
        imagewebp($image, null, 100);
        $data = ob_get_clean();

        $this->s3client->putObject([
            'Bucket' => Utils::Bucket,
            'Key' => $key,
            'Body' => $data
        ]);
        $this->insertToMediaGalleryAsset($imageWebP);

        $imageThumb = imagescale($this->createImageAny($imgFrom, $crop, $galleryKey), 150);
        ob_start();
        if (str_contains($imgFrom, '.jp')) {
            imagejpeg($imageThumb, null, 100);
        } elseif (str_contains($imgFrom, '.png')) {
            imagepng($imageThumb, null, 0);
        }
        $imgData = ob_get_clean();

        $keyThumb = 'pub/media/.thumbs' . str_replace($mediaUrl, '', $imgFrom);

        $this->insertToMediaGalleryAsset(str_replace('pub/media/', '', $keyThumb));

        Logger::writeLog($keyThumb);
        //Put original thumb
        $this->s3client->putObject([
            'Bucket' => Utils::Bucket,
            'Key' => $keyThumb,
            'Body' => $imgData
        ]);

        $image = imagescale($image, 150);
        ob_start();
        imagewebp($image, null, 100);
        $data = ob_get_clean();

        $keyThumb = 'pub/media/.thumbs' . str_replace($mediaUrl, '', $imageWebP);

        //Put webp thumb
        $this->s3client->putObject([
            'Bucket' => Utils::Bucket,
            'Key' => $keyThumb,
            'Body' => $data
        ]);

        $this->insertToMediaGalleryAsset(str_replace('pub/media/', '', $keyThumb));
    }

    private function createImageAny($imgFrom, bool $crop = false, $galleryKey = null)
    {
        if ($crop) {
            return $this->cropImage(str_replace(' ', '+', $imgFrom), $galleryKey);
        } else {
            if (str_contains($imgFrom, '.jp')) {
                return imagecreatefromjpeg(str_replace(' ', '+', $imgFrom));
            } elseif (str_contains($imgFrom, '.png')) {
                return imagecreatefrompng(str_replace(' ', '+', $imgFrom));
            } elseif (str_contains($imgFrom, '.webp')) {
                return imagecreatefromwebp(str_replace(' ', '+', $imgFrom));
            } else {
                return null;
            }
        }
    }

    public function cropImage($image, $galleryKey = null)
    {
        $rotate = false;
        $degrees = null;

        list($w_i, $h_i, $type) = getimagesize($image); // Return the size and image type (number)
        $maxSize = max($w_i, $h_i);

        try {
            $exif = exif_read_data($image);

            if (array_key_exists('Orientation', $exif)) {
                if ($exif['Orientation'] == '6') {
                    $degrees = 270;
                    $rotate = true;
                }
                if ($exif['Orientation'] == '8') {
                    $degrees = 90;
                    $rotate = true;
                }
                if ($exif['Orientation'] == '3') {
                    $degrees = 180;
                    $rotate = true;
                }
            } elseif ($h_i > $w_i) {
                $degrees = 0;
                $rotate = true;
            }
        } catch (Exception $e) {
            //Skip...
        }

        $factor = (9 / 16 + 3 / 4) / 2;
        $imgFactor = $w_i > $h_i ? $h_i / $w_i : $w_i / $h_i;

        $w = 16;
        $h = 9;

        if ($galleryKey == 'products' || $imgFactor > $factor) {
            $w = 4;
            $h = 3;
        }

        $w_o = $maxSize;
        $h_o = $maxSize * $h / $w;
        if ($h_i > $h_o) {
            $h_o = $h_i;
            $w_o = $h_o * $w / $h;
        }

        $types = ["", "gif", "jpeg", "png"];
        $ext = $types[$type];
        if ($ext) {
            $func = 'imagecreatefrom' . $ext;
            $img_i = $func($image);
        } else {
            Logger::writeLog('Incorrect image: ' . $image);
        }

        $x = ($w_i - $w_o) / 2;
        $y = ($h_i - $h_o) / 2;

        $img_i = imagecrop($img_i, ['x' => $x, 'y' => $y, 'width' => $w_o, 'height' => $h_o]);

        if ($w_o > self::MAX_SIZE) {
            $h_o = intval((1 - ($w_o - self::MAX_SIZE) / $w_o) * $h_o);
            $w_o = self::MAX_SIZE;
        } elseif ($h_o > self::MAX_SIZE) {
            $w_o = intval((1 - ($h_o - self::MAX_SIZE) / $h_o) * $w_o);
            $h_o = self::MAX_SIZE;
        }

        $img_i = imagescale($img_i, intval($w_o), intval($h_o));

        if ($rotate) {
            $img_i = imagerotate($img_i, $degrees, 255);
        }

        imagealphablending($img_i, false);
        imagesavealpha($img_i, true);
        imagepng($img_i);

        for ($x = 0; $x < imagesx($img_i); ++$x) {
            for ($y = 0; $y < imagesy($img_i); ++$y) {
                $index = imagecolorat($img_i, $x, $y);
                $rgb = imagecolorsforindex($img_i, $index);
                if ($rgb['red'] == 0 && $rgb['green'] == 0 && $rgb['blue'] == 0) {
                    $color = imagecolorallocatealpha($img_i, 0, 0, 0, 127);
                    imagesetpixel($img_i, $x, $y, $color);
                }
            }
        }

        return $img_i;
    }

    public function producersExecute(): void
    {
        try {
            $this->isTest = false;
            $images = $this->getMediaFiles('pub/media/producers');
            $this->imagesExecute($images, false, null);
        } catch (Exception $e) {
            Logger::writeLog($e);
        }
    }

    public function productsExecute(): void
    {
        try {
            $this->isTest = false;
            $images = $this->getMediaFiles('pub/media/catalog/product');
            $this->imagesExecute($images, false, 'products');
        } catch (Exception $e) {
            Logger::writeLog($e);
        }
    }

    private function staticFilesExecute(): void
    {
        try {
            $files = Utils::getAllFiles('/var/www/html/magento/pub/static');
            foreach ($files as $key => $file) {
                $this->s3client->putObject([
                    'Bucket' => Utils::Bucket,
                    'Key' => 'static/' . str_replace('/var/www/html/magento/pub/static/', '', $file),
                    'SourceFile' => $file
                ]);
            }
        } catch (Exception $e) {
            Logger::writeLog($e);
        }
    }

    private function mediaImagesExecute(): void
    {
        try {
            $this->isTest = false;
            $images = $this->getMediaFiles('pub/media/poolrobots');
            $this->imagesExecute($images, false, null);
            $images = $this->getMediaFiles('pub/media/images');
            $this->imagesExecute($images, false, null);
        } catch (Exception $e) {
            Logger::writeLog($e);
        }
    }

    private function testExecute(): void
    {
        $this->isTest = true;

        $contents = $this->s3client->listObjectsV2([
            'Bucket' => Utils::Bucket,
            'Prefix' => 'pub/media/test'
        ]);

        $this->imagesExecute([], false, null);

        $this->isTest = false;
    }

    private function imageAvif($name): void
    {
        //$i = new \Imagick();
        //ImageResizer::imageMagic->open($name);
        //ImageResizer::imageMagic->save($name . '.avif');
    }
}
