<?php
namespace GardenLawn\Core\Plugin\Framework\Image\Adapter;

use Closure;
use Magento\Framework\Image\Adapter\Gd2;

class Gd2Plugin
{
    /**
     * Bypass GD2 validation for unsupported image types like WEBP, AVIF, and SVG.
     *
     * @param Gd2 $subject
     * @param Closure $proceed
     * @param string $filePath
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundValidateUploadFile(Gd2 $subject, Closure $proceed, string $filePath): bool
    {
        // Suppress errors for getimagesize if file is not a valid image (e.g., SVG)
        $imageInfo = @getimagesize($filePath);

        if ($imageInfo === false) {
            // getimagesize fails for non-raster images like SVG.
            // Let's do a basic check for SVG content.
            $fileHandle = @fopen($filePath, 'r');
            if ($fileHandle) {
                $fileContent = fread($fileHandle, 256); // Read first 256 bytes
                fclose($fileHandle);
                // Simple check for <svg tag
                if (stripos(trim($fileContent), '<svg') === 0) {
                    return true; // It's an SVG, bypass GD validation.
                }
            }
            // If it's not SVG and getimagesize failed, let the original validation handle it.
            return $proceed($filePath);
        }

        $imageType = $imageInfo[2]; // This is the IMAGETYPE_* constant

        // Define constants if they don't exist (for older PHP)
        $webpImageType = defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : 18;
        $avifImageType = defined('IMAGETYPE_AVIF') ? IMAGETYPE_AVIF : 19;

        if (in_array($imageType, [$webpImageType, $avifImageType])) {
            // It's a WEBP or AVIF. The default GD adapter doesn't support it.
            // We assume it's valid and bypass the GD check.
            return true;
        }

        // For all other supported image types (JPEG, PNG, GIF), let the original method validate.
        return $proceed($filePath);
    }
}
