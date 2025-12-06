<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Framework\Image\Adapter;

use Magento\Framework\Image\Adapter\Gd2;
use ReflectionProperty;
use ReflectionException;

class Gd2Plugin
{
    /**
     * Teach the GD2 adapter how to handle WebP before opening a file.
     *
     * @param Gd2 $subject
     * @param string|null $fileName
     * @return array
     */
    public function beforeOpen(Gd2 $subject, string $fileName = null): array
    {
        if ($fileName && str_ends_with(strtolower($fileName), '.webp')) {
            $this->addWebpSupport($subject);
        }

        return [$fileName];
    }

    /**
     * Teach the GD2 adapter how to handle WebP before saving a file.
     *
     * @param Gd2 $subject
     * @param string|null $destination
     * @param string|null $newName
     * @return array
     */
    public function beforeSave(Gd2 $subject, string $destination = null, string $newName = null): array
    {
        if ($destination && str_ends_with(strtolower($destination), '.webp')) {
            $this->addWebpSupport($subject);

            // Set file type to WebP for saving
            try {
                $fileTypeProperty = new ReflectionProperty(Gd2::class, '_fileType');
                $fileTypeProperty->setAccessible(true);
                $fileTypeProperty->setValue($subject, IMAGETYPE_WEBP);
            } catch (ReflectionException $e) {
                // Silently ignore to not break core functionality.
            }
        }

        return [$destination, $newName];
    }

    /**
     * Use reflection to add WebP support to the GD2 adapter's internal callbacks.
     * Also handles palette-to-true-color and transparency issues.
     *
     * @param Gd2 $subject
     */
    private function addWebpSupport(Gd2 $subject): void
    {
        try {
            // Add WebP to the list of supported formats for opening and saving
            $callbacksProperty = new ReflectionProperty(Gd2::class, '_callbacks');
            $callbacksProperty->setAccessible(true);
            $callbacks = $callbacksProperty->getValue($subject);
            if (!isset($callbacks[IMAGETYPE_WEBP])) {
                $callbacks[IMAGETYPE_WEBP] = ['output' => 'imagewebp', 'create' => 'imagecreatefromwebp'];
                $callbacksProperty->setValue($subject, $callbacks);
            }

            // Access the internal image resource to apply fixes
            $imageHandlerProperty = new ReflectionProperty(Gd2::class, '_imageHandler');
            $imageHandlerProperty->setAccessible(true);
            $imageResource = $imageHandlerProperty->getValue($subject);

            if ($imageResource) {
                // FIX for palette-based images
                if (function_exists('imageistruecolor') && !imageistruecolor($imageResource)) {
                    imagepalettetotruecolor($imageResource);
                }
                // FIX for transparency issues (black background)
                if (function_exists('imagealphablending')) {
                    imagealphablending($imageResource, false);
                }
                if (function_exists('imagesavealpha')) {
                    imagesavealpha($imageResource, true);
                }
            }
        } catch (ReflectionException $e) {
            // Silently ignore if reflection fails.
        }
    }
}
