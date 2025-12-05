<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\MediaStorage\Service;

use Magento\MediaStorage\Service\ImageResize;

class ImageResizePlugin
{
    public function beforeGenerateResizedImage(
        ImageResize $subject,
        array $imageParams,
        string $originalImagePath,
        string $imageAssetPath,
        bool $usingDbAsStorage,
        string $mediaStorageFilename
    ): array {
        // Change destination to WebP
        $imageAssetPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $imageAssetPath);
        $mediaStorageFilename = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $mediaStorageFilename);

        return [
            $imageParams,
            $originalImagePath,
            $imageAssetPath,
            $usingDbAsStorage,
            $mediaStorageFilename
        ];
    }
}
