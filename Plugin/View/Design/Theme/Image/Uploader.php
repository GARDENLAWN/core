<?php
/**
 * Plugin to extend allowed file extensions (WebP, AVIF, SVG) for Theme Image Uploader.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\View\Design\Theme\Image;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Design\Theme\Image\Uploader as Subject;

class Uploader
{
    private const array NEW_EXTENSIONS = ['webp', 'avif', 'svg'];

    /**
     * Around plugin for the uploadPreviewImage() method.
     * Overwrites the protected $_allowedExtensions property before the uploader is created.
     *
     * @param Subject $subject
     * @param callable $proceed
     * @param string $scope
     * @param string $destinationPath
     * @return string|bool
     * @throws LocalizedException
     */
    public function aroundUploadPreviewImage(
        Subject $subject,
        callable $proceed,
        string $scope,
        string $destinationPath
    ): bool|string
    {
        // Użycie refleksji (Reflection) jest konieczne, ponieważ właściwość $_allowedExtensions
        // jest chroniona (protected) i nie ma publicznego getter'a.
        try {
            $reflection = new \ReflectionClass($subject);
            $property = $reflection->getProperty('_allowedExtensions');
            $property->setAccessible(true);

            // Pobranie oryginalnej listy
            $originalExtensions = $property->getValue($subject);

            // Zmodyfikowanie listy przed wywołaniem proceed()
            $newExtensions = array_unique(array_merge($originalExtensions, self::NEW_EXTENSIONS));
            $property->setValue($subject, $newExtensions);

            // Wywołanie oryginalnej metody uploadPreviewImage()
            $result = $proceed($scope, $destinationPath);

            // Przywrócenie oryginalnej listy (dobre praktyki)
            $property->setValue($subject, $originalExtensions);

            return $result;

        } catch (\ReflectionException $e) {
            // Logowanie lub rzucanie wyjątku, jeśli refleksja się nie powiedzie.
            // Dla uproszczenia, w przypadku błędu refleksji, po prostu kontynuujemy
            // oryginalne działanie (choć to może prowadzić do błędu uploadu).
            return $proceed($scope, $destinationPath);
        }
    }
}
