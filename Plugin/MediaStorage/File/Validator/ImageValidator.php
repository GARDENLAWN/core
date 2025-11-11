<?php
/**
 * Plugin to extend Magento\MediaStorage\Model\File\Validator\Image to allow WebP, AVIF, and SVG formats.
 * This plugin checks the file's MIME type and bypasses the raster image check for vector SVG files.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\MediaStorage\File\Validator;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\File\Mime;
use Magento\Framework\Image\Factory;
use Magento\MediaStorage\Model\File\Validator\Image;
use Psr\Log\LoggerInterface;

class ImageValidator
{
    // Supported MIME types for modern formats
    private const string MIME_WEBP = 'image/webp';
    private const string MIME_AVIF = 'image/avif';
    private const string MIME_SVG = 'image/svg+xml';

    /**
     * @var Mime
     */
    private Mime $fileMime;

    /**
     * @var Factory
     */
    private Factory $imageFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Mime $fileMime
     * @param Factory $imageFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Mime $fileMime,
        Factory $imageFactory,
        LoggerInterface $logger
    ) {
        $this->fileMime = $fileMime;
        $this->imageFactory = $imageFactory;
        $this->logger = $logger;
    }

    /**
     * Around plugin for isValid to allow modern formats.
     *
     * @param Image $subject
     * @param callable $proceed
     * @param string $filePath
     * @return bool
     * @throws FileSystemException
     */
    public function aroundIsValid(
        Image $subject,
        callable $proceed,
        string $filePath
    ): bool {
        $fileMimeType = $this->fileMime->getMimeType($filePath);

        // Define a map of new allowed MIME types
        $allowedMimeTypes = [
            self::MIME_WEBP,
            self::MIME_AVIF,
            self::MIME_SVG
        ];

        if (in_array($fileMimeType, $allowedMimeTypes, true)) {
            // SVG files: bypass the image factory check.
            if ($fileMimeType === self::MIME_SVG) {
                return true;
            }

            // WebP and AVIF files: still need to be verified as valid image files
            // by trying to open them with the image adapter.
            try {
                $image = $this->imageFactory->create($filePath);
                $image->open();
                return true;
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf('Image validation failed for %s: %s', $filePath, $e->getMessage())
                );
                return false;
            }
        }

        // For all other files (including existing JPG, PNG, GIF), run the original validation logic.
        return $proceed($filePath);
    }
}
