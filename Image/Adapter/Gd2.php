<?php

namespace GardenLawn\Core\Image\Adapter;

use Exception;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Image\Adapter\Gd2 as CoreGd2;
use Psr\Log\LoggerInterface;

/**
 * Preference adapter that ensures WebP generation and writes via MEDIA filesystem (S3-aware).
 */
class Gd2 extends CoreGd2
{
    /**
     * Save image to specific path and generate WebP alongside (if supported),
     * storing it through Magento MEDIA filesystem so remote storage (e.g. S3) is used.
     *
     * @param null|string $destination
     * @param null|string $newName
     * @return void
     */
    public function save($destination = null, $newName = null)
    {
        // Compute final target path exactly as core does
        $fileName = $this->_prepareDestination($destination, $newName);

        // Let core write the image first
        parent::save($destination, $newName);

        // Generate WebP next to saved file (best-effort)
        $om = ObjectManager::getInstance();
        /** @var LoggerInterface $logger */
        $logger = $om->get(LoggerInterface::class);

        try {
            // Basic guards
            if (!function_exists('imagewebp')) {
                $logger->debug('GardenLawn\\Core\\Image\\Adapter\\Gd2: GD has no WebP support, skipping.');
                return;
            }
            if (!is_string($fileName) || $fileName === '' || !file_exists($fileName)) {
                $logger->debug('GardenLawn\\Core\\Image\\Adapter\\Gd2: Output file not found, skipping WebP for: ' . (string)$fileName);
                return;
            }

            // Determine image type
            $imageInfo = @getimagesize($fileName);
            if ($imageInfo === false) {
                $logger->warning('GardenLawn\\Core\\Image\\Adapter\\Gd2: getimagesize failed for ' . $fileName);
                return;
            }
            $type = $imageInfo[2]; // IMAGETYPE_*

            switch ($type) {
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
                    // Unsupported for WebP conversion
                    $logger->debug('GardenLawn\\Core\\Image\\Adapter\\Gd2: Unsupported type for WebP (' . ($imageInfo['mime'] ?? 'unknown') . ') for ' . $fileName);
                    return;
            }

            if (!function_exists($createFunction)) {
                $logger->warning('GardenLawn\\Core\\Image\\Adapter\\Gd2: Missing GD function ' . $createFunction . ', skipping WebP.');
                return;
            }

            $src = @$createFunction($fileName);
            if (!$src) {
                $logger->warning('GardenLawn\\Core\\Image\\Adapter\\Gd2: Failed to create image resource for ' . $fileName);
                return;
            }

            $w = imagesx($src);
            $h = imagesy($src);
            $dst = imagecreatetruecolor($w, $h);

            if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }

            imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);

            // Figure out relative path under MEDIA for final write (S3-aware)
            /** @var \Magento\Framework\App\Filesystem\DirectoryList $dirList */
            $dirList = $om->get(\Magento\Framework\App\Filesystem\DirectoryList::class);
            /** @var \Magento\Framework\Filesystem $fs */
            $fs = $om->get(\Magento\Framework\Filesystem::class);
            $mediaWrite = $fs->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
            $mediaRead  = $fs->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);

            $mediaRootPath = rtrim($mediaRead->getAbsolutePath(), DIRECTORY_SEPARATOR);
            $tmpMediaRoot  = rtrim($dirList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR)
                . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'media', DIRECTORY_SEPARATOR);

            $relativeUnderMedia = null;
            if (strpos($fileName, $mediaRootPath . DIRECTORY_SEPARATOR) === 0) {
                $relativeUnderMedia = ltrim(substr($fileName, strlen($mediaRootPath)), DIRECTORY_SEPARATOR);
            } elseif (strpos($fileName, $tmpMediaRoot . DIRECTORY_SEPARATOR) === 0) {
                $relativeUnderMedia = ltrim(substr($fileName, strlen($tmpMediaRoot)), DIRECTORY_SEPARATOR);
            } else {
                $logger->debug('GardenLawn\\Core\\Image\\Adapter\\Gd2: Unrecognized base path for ' . $fileName);
                imagedestroy($dst);
                imagedestroy($src);
                return;
            }

            // Replace extension with .webp
            $relativeWebp = preg_replace('/\.[^.]+$/', '.webp', $relativeUnderMedia);

            // Skip if already exists via MEDIA adapter
            if ($mediaWrite->isExist($relativeWebp)) {
                imagedestroy($dst);
                imagedestroy($src);
                return;
            }

            // Generate WebP binary into buffer
            $quality = 89; // could be made configurable later
            ob_start();
            imagewebp($dst, null, $quality);
            $webpBinary = ob_get_clean();

            // Ensure directory exists and write through MEDIA FS (S3-aware)
            $mediaWrite->create(dirname($relativeWebp));
            $mediaWrite->writeFile($relativeWebp, $webpBinary);
            $logger->info('GardenLawn\\Core\\Image\\Adapter\\Gd2: Generated WebP via media FS: ' . $relativeWebp);

            imagedestroy($dst);
            imagedestroy($src);
        } catch (Exception $e) {
            $logger->error('GardenLawn\\Core\\Image\\Adapter\\Gd2: Error during WebP generation: ' . $e->getMessage());
        }
    }
}
