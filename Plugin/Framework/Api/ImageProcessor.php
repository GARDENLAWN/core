<?php
/**
 * Plugin to ensure ImageProcessor correctly handles image size detection for WebP and AVIF.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Framework\Api;

use Magento\Framework\Api\ImageProcessor as Subject;

class ImageProcessor
{
    /**
     * Ensure getImageSize can read dimensions for WebP and AVIF formats.
     *
     * @param Subject $subject
     * @param callable $proceed
     * @param string $filePath
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundGetImageSize(
        Subject $subject,
        callable $proceed,
        string $filePath
    ): array {
        // Fallback to original method if the file is not found or is a standard image type
        $result = $proceed($filePath);

        if (!empty($result)) {
            return $result;
        }

        // Try standard PHP function for WebP/AVIF if the original Magento logic (often Imagick-based) failed.
        // PHP's getimagesize has built-in support for WebP (since 5.5) and AVIF (since 8.1+).
        if (is_file($filePath)) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            $imageInfo = @getimagesize($filePath);
            if (is_array($imageInfo) && count($imageInfo) >= 2) {
                return [
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1],
                ];
            }
        }

        return [];
    }
}
