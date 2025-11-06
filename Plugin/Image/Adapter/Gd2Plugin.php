<?php

namespace GardenLawn\Core\Plugin\Image\Adapter;

use Exception;
use Magento\Framework\Image\Adapter\Gd2\Interceptor;
use Magento\Framework\Filesystem\Driver\File;
use Psr\Log\LoggerInterface;
use Magento\Framework\Image\Factory as ImageFactory;
use Magento\MediaStorage\Helper\File\Storage\Database;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem as MagentoFilesystem;

class Gd2Plugin
{
    /**
     * @var File
     */
    protected File $fileDriver;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var ImageFactory
     */
    protected ImageFactory $imageFactory;

    /**
     * @var Database
     */
    protected Database $mediaStorageHelper;

    /**
     * @var MagentoFilesystem\Directory\ReadInterface
     */
    protected MagentoFilesystem\Directory\ReadInterface $mediaDirectoryRead;

    /**
     * Gd2Plugin constructor.
     * @param File $fileDriver
     * @param LoggerInterface $logger
     * @param ImageFactory $imageFactory
     * @param Database $mediaStorageHelper
     * @param MagentoFilesystem $filesystem
     */
    public function __construct(
        File              $fileDriver,
        LoggerInterface   $logger,
        ImageFactory      $imageFactory,
        Database          $mediaStorageHelper,
        MagentoFilesystem $filesystem
    )
    {
        $this->fileDriver = $fileDriver;
        $this->logger = $logger;
        $this->imageFactory = $imageFactory;
        $this->mediaStorageHelper = $mediaStorageHelper;
        $this->mediaDirectoryRead = $filesystem->getDirectoryRead(DirectoryList::MEDIA);
    }

    /**
     * After save plugin to generate WebP image
     *
     * @param Interceptor $subject
     * @param string $result
     * @param string|null $destination
     * @return string
     */
    public function afterSave(Interceptor $subject, string $result, ?string $destination = null): string
    {
        if (!$destination) {
            $destination = $result;
        }

        $extension = strtolower(pathinfo($destination, PATHINFO_EXTENSION));

        // Only process if the original image is JPEG, JPG or PNG
        if (in_array($extension, ['jpeg', 'jpg', 'png'])) {
            try {
                // Sprawdź, czy plik istnieje lokalnie przed próbą otwarcia
                if (!$this->fileDriver->isExists($destination)) {
                    $this->logger->warning('Original image file not found for WebP conversion: ' . $destination);
                    return $result;
                }

                $imageInfo = getimagesize($destination);
                if ($imageInfo === false) {
                    $this->logger->error('Could not get image size for ' . $destination);
                    return $result;
                }

                $fileType = $imageInfo[2]; // IMAGETYPE_JPEG, IMAGETYPE_PNG, etc.

                $createFunction = null;
                switch ($fileType) {
                    case IMAGETYPE_JPEG:
                        $createFunction = 'imagecreatefromjpeg';
                        break;
                    case IMAGETYPE_PNG:
                        $createFunction = 'imagecreatefrompng';
                        break;
                    case IMAGETYPE_GIF:
                        $createFunction = 'imagecreatefromgif';
                        break;
                    default:
                        $this->logger->warning('Unsupported MIME type for WebP conversion: ' . $imageInfo['mime'] . ' for file: ' . $destination);
                        return $result;
                }

                if ($createFunction && function_exists($createFunction)) {
                    $sourceImageResource = $createFunction($destination);
                    if ($sourceImageResource) {
                        $webpDestination = $this->getWebpDestination($destination);
                        $quality = 80; // Możesz to uczynić konfigurowalnym

                        // Utwórz nowy obraz GD dla WebP
                        $webpResource = imagecreatetruecolor(imagesx($sourceImageResource), imagesy($sourceImageResource));
                        // Zachowaj przezroczystość dla PNG
                        if ($fileType === IMAGETYPE_PNG) {
                            imagealphablending($webpResource, false);
                            imagesavealpha($webpResource, true);
                        }
                        imagecopy($webpResource, $sourceImageResource, 0, 0, 0, 0, imagesx($sourceImageResource), imagesy($sourceImageResource));

                        // Zapisz jako WebP
                        imagewebp($webpResource, $webpDestination, $quality);
                        imagedestroy($webpResource);
                        imagedestroy($sourceImageResource); // Zwolnij zasób

                        $this->logger->info('Generated WebP image: ' . $webpDestination);

                        // Oblicz ścieżkę względną do pliku w katalogu mediów
                        $relativeWebpPath = $this->mediaDirectoryRead->getRelativePath($webpDestination);

                        $this->mediaStorageHelper->saveFile($relativeWebpPath);
                        $this->logger->info('Uploaded WebP image to S3: ' . $relativeWebpPath);

                        // Usuń tymczasowy plik lokalny po przesłaniu do S3
                        $this->fileDriver->deleteFile($webpDestination);
                    }
                }
            } catch (Exception $e) {
                $this->logger->error('Error generating or uploading WebP image for ' . $destination . ': ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Get WebP destination path
     *
     * @param string $originalDestination
     * @return string
     */
    protected function getWebpDestination(string $originalDestination): string
    {
        $pathInfo = pathinfo($originalDestination);
        return $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $pathInfo['filename'] . '.webp';
    }
}
