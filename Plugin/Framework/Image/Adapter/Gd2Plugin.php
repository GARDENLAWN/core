<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Framework\Image\Adapter;

use Magento\Framework\Image\Adapter\Gd2;
use ReflectionProperty;

class Gd2Plugin
{
    public function beforeSave(Gd2 $subject, $destination = null, $newName = null): array
    {
        if ($destination && str_ends_with(strtolower($destination), '.webp')) {
            // Add WebP support to callbacks
            $callbacksProperty = new ReflectionProperty(Gd2::class, '_callbacks');
            $callbacksProperty->setAccessible(true);
            $callbacks = $callbacksProperty->getValue($subject);
            if (!isset($callbacks[IMAGETYPE_WEBP])) {
                $callbacks[IMAGETYPE_WEBP] = ['output' => 'imagewebp', 'create' => 'imagecreatefromwebp'];
                $callbacksProperty->setValue($subject, $callbacks);
            }

            // Set file type to WebP
            $fileTypeProperty = new ReflectionProperty(Gd2::class, '_fileType');
            $fileTypeProperty->setAccessible(true);
            $fileTypeProperty->setValue($subject, IMAGETYPE_WEBP);
        }

        return [$destination, $newName];
    }
}
