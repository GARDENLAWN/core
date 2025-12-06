<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Cms\Model\Wysiwyg\Images;

use Closure;
use Exception;
use Magento\Cms\Model\Wysiwyg\Images\Storage;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;

class StorageResizeFilePlugin
{
    private Filesystem\Directory\WriteInterface $mediaDirectory;
    private LoggerInterface $logger;

    /**
     * @throws FileSystemException
     */
    public function __construct(
        Filesystem $filesystem,
        LoggerInterface $logger
    ) {
        $this->mediaDirectory = $filesystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
        $this->logger = $logger;
    }

    /**
     * Around plugin for Magento\Cms\Model\Wysiwyg\Images\Storage::resizeFile
     *
     * Prevents resizing of WebP and SVG files, instead copies them directly.
     *
     * @param Storage $subject
     * @param Closure $proceed
     * @param string $source
     * @param bool $keepRatio
     * @return bool|string
     */
    public function aroundResizeFile(Storage $subject, Closure $proceed, string $source, bool $keepRatio = true): bool|string
    {
        try {
            $sourceExtension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            $copyOnlyExtensions = ['webp', 'svg'];

            if (in_array($sourceExtension, $copyOnlyExtensions, true)) {
                $this->logger->info('[StorageResizeFilePlugin] Detected ' . $sourceExtension . ' file. Copying directly instead of resizing.');

                $thumbnailRoot = $subject->getThumbnailRoot();
                $relativePathToRoot = $this->getRelativePathToRoot($subject, $source);
                $targetThumbnailPath = $thumbnailRoot . $relativePathToRoot;

                // Ensure the thumbnail directory exists
                $targetThumbnailDir = $this->mediaDirectory->getRelativePath(dirname($targetThumbnailPath));
                if (!$this->mediaDirectory->isExist($targetThumbnailDir)) {
                    $this->mediaDirectory->create($targetThumbnailDir);
                }

                // Copy the original file to the thumbnail location
                $this->mediaDirectory->copyFile(
                    $this->mediaDirectory->getRelativePath($source),
                    $this->mediaDirectory->getRelativePath($targetThumbnailPath)
                );

                return $targetThumbnailPath;
            }
        } catch (Exception $e) {
            $this->logger->error(
                '[StorageResizeFilePlugin] Error during custom resizeFile logic: ' . $e->getMessage(),
                ['exception' => $e]
            );
            // Fallback to original method if our custom logic fails
            return $proceed($source, $keepRatio);
        }

        // For other image types (jpg, png), proceed with original resizeFile method
        return $proceed($source, $keepRatio);
    }

    /**
     * Helper to get relative path to storage root, similar to Storage::_getRelativePathToRoot
     *
     * @param Storage $subject
     * @param string $path
     * @return string
     */
    private function getRelativePathToRoot(Storage $subject, string $path): string
    {
        // Accessing protected method via reflection
        try {
            $reflection = new \ReflectionClass($subject);
            $method = $reflection->getMethod('_getRelativePathToRoot');
            $method->setAccessible(true);
            return $method->invoke($subject, $path);
        } catch (\ReflectionException $e) {
            $this->logger->error('Reflection failed for _getRelativePathToRoot: ' . $e->getMessage());
            // Fallback if reflection fails (should not happen in production)
            $storageRoot = $subject->getCmsWysiwygImages()->getStorageRoot();
            return substr($path, strlen($storageRoot));
        }
    }
}
