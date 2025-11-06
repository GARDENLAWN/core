<?php

namespace GardenLawn\Core\Plugin\Image\Adapter;

use Exception;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Image\Adapter\Gd2 as Gd2Adapter;
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
     * @param Gd2Adapter $subject
     * @param mixed $result
     * @param string|null $destination
     * @param string|null $newName
     * @return mixed
     * @throws FileSystemException
     */
    public function afterSave(Gd2Adapter $subject, mixed $result, string $destination = null, string $newName = null): mixed
    {
        // Resolve final file path written by Gd2::save()
        $finalPath = null;
        if ($destination) {
            try {
                if ($this->fileDriver->isDirectory($destination) && $newName) {
                    $finalPath = rtrim($destination, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newName;
                } else {
                    $finalPath = $destination;
                }
            } catch (Exception $e) {
                // If isDirectory throws for non-existing path, assume it's a file path
                $finalPath = $destination;
            }
        } else {
            // When save(null, null) is used, we cannot reliably know the final path here.
            $this->logger->debug('Gd2Plugin: save() called without destination/newName – skipping WebP generation.');
            return $result;
        }

        // Only proceed if we can locate the output file
        if (!$this->fileDriver->isExists($finalPath)) {
            $this->logger->warning('Gd2Plugin: output image not found for WebP conversion: ' . $finalPath);
            return $result;
        }

        // Skip if WebP already exists
        $webpDestination = $this->getWebpDestination($finalPath);
        if ($this->fileDriver->isExists($webpDestination)) {
            return $result;
        }

        // Ensure environment supports WebP
        if (!function_exists('imagewebp')) {
            $this->logger->warning('Gd2Plugin: GD does not support WebP. Skipping WebP generation.');
            return $result;
        }

        try {
            $imageInfo = @getimagesize($finalPath);
            if ($imageInfo === false) {
                $this->logger->error('Gd2Plugin: Could not get image size for ' . $finalPath);
                return $result;
            }

            $fileType = $imageInfo[2]; // IMAGETYPE_*
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
                    $this->logger->debug('Gd2Plugin: Unsupported image type for WebP conversion (' . ($imageInfo['mime'] ?? 'unknown') . ') for file: ' . $finalPath);
                    return $result;
            }

            if (!function_exists($createFunction)) {
                $this->logger->warning('Gd2Plugin: Missing GD function ' . $createFunction . ' – skipping WebP.');
                return $result;
            }

            $sourceImageResource = @$createFunction($finalPath);
            if (!$sourceImageResource) {
                $this->logger->error('Gd2Plugin: Failed to create image resource for ' . $finalPath);
                return $result;
            }

            $width = imagesx($sourceImageResource);
            $height = imagesy($sourceImageResource);
            $webpResource = imagecreatetruecolor($width, $height);

            if ($fileType === IMAGETYPE_PNG || $fileType === IMAGETYPE_GIF) {
                imagealphablending($webpResource, false);
                imagesavealpha($webpResource, true);
            }

            imagecopy($webpResource, $sourceImageResource, 0, 0, 0, 0, $width, $height);

            $quality = 89; // could be made configurable
            if (!@imagewebp($webpResource, $webpDestination, $quality)) {
                $this->logger->error('Gd2Plugin: imagewebp() failed for ' . $finalPath);
            } else {
                $this->logger->info('Gd2Plugin: Generated WebP image: ' . $webpDestination);

                // Try to push to remote storage if configured for DB storage helper
                try {
                    $relativeWebpPath = $this->mediaDirectoryRead->getRelativePath($webpDestination);
                    $this->mediaStorageHelper->saveFile($relativeWebpPath);
                    $this->logger->info('Gd2Plugin: Saved WebP via media storage helper: ' . $relativeWebpPath);
                } catch (Exception $e) {
                    // Non-fatal: environment may not use DB storage
                    $this->logger->debug('Gd2Plugin: media storage saveFile skipped/failed: ' . $e->getMessage());
                }
            }

            imagedestroy($webpResource);
            imagedestroy($sourceImageResource);
        } catch (Exception $e) {
            $this->logger->error('Gd2Plugin: Error generating or uploading WebP image for ' . $finalPath . ': ' . $e->getMessage());
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
