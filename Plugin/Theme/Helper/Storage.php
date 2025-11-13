<?php
/**
 * Plugin to extend allowed extensions for images within the Theme Storage Helper.
 * This ensures WebP and AVIF are recognized as valid image types.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Theme\Helper;

use Magento\Theme\Helper\Storage as Subject;
use Magento\Theme\Model\Wysiwyg\Storage as WysiwygStorage;

class Storage
{
    private const array NEW_IMAGE_EXTENSIONS = ['webp', 'avif', 'svg'];

    /**
     * Add WebP, AVIF, and SVG to the list of allowed extensions for image storage type.
     *
     * @param Subject $subject
     * @param array $result
     * @return array
     */
    public function afterGetAllowedExtensionsByType(
        Subject $subject,
        array $result
    ): array {
        try {
            if ($subject->getStorageType() === WysiwygStorage::TYPE_IMAGE) {
                // Dodajemy nowe rozszerzenia do listy dozwolonych typów obrazów.
                return array_unique(array_merge($result, self::NEW_IMAGE_EXTENSIONS));
            }
        } catch (\Exception $e) {
            // W razie błędu (np. gdy typ magazynu jest nieprawidłowy), zwracamy oryginalny wynik.
            // Logika rzucająca wyjątek jest w getAllowedExtensionsByType, ale lepiej go obsłużyć.
            return $result;
        }

        // Zwracamy oryginalny wynik dla innych typów (np. TYPE_FONT).
        return $result;
    }
}
