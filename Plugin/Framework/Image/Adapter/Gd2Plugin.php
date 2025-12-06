<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Framework\Image\Adapter;

use Magento\Framework\Image\Adapter\Gd2;
use ReflectionProperty;
use ReflectionException;

class Gd2Plugin
{
    public function beforeSave(Gd2 $subject, $destination = null, $newName = null): array
    {
        if ($destination && str_ends_with(strtolower($destination), '.webp')) {
            // Add WebP support to callbacks
            try {
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

                // FIX: Convert palette-based images to true color right before saving as WebP
                $imageHandlerProperty = new ReflectionProperty(Gd2::class, '_imageHandler');
                $imageHandlerProperty->setAccessible(true);
                $imageResource = $imageHandlerProperty->getValue($subject);

                if ($imageResource && function_exists('imageistruecolor') && !imageistruecolor($imageResource)) {
                    imagepalettetotruecolor($imageResource);
                }

            } catch (ReflectionException $e) {
                // Silently ignore if reflection fails, to not break core functionality.
                // A log could be added here if needed.
            }
        }

        return [$destination, $newName];
    }
}
