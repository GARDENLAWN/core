<?php
namespace GardenLawn\Core\Plugin\Framework\Image\Adapter;

use Closure;
use Magento\Framework\Image\Adapter\Gd2;

class Gd2Plugin
{
    /**
     * @param Gd2 $subject
     * @param Closure $proceed
     * @param string $filePath
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundValidateUploadFile(Gd2 $subject, Closure $proceed, string $filePath): bool
    {
        $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($fileExtension, ['svg', 'webp', 'avif'])) {
            // For SVG, WEBP, and AVIF, we can bypass the GD2 validation
            // as it might not support these formats by default.
            // We've already validated the extension and MIME type earlier.
            return true;
        }

        return $proceed($filePath);
    }
}
