<?php
/**
 * Plugin, który dodaje wsparcie dla nowoczesnych formatów obrazów (WebP, AVIF, SVG)
 * w głównym uploaderze plików multimedialnych Magento,
 * nadpisując logikę dozwolonych rozszerzeń.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\MediaStorage\File;

use Magento\MediaStorage\Model\File\Uploader as Subject;

class Uploader
{
    /**
     * Lista rozszerzeń, które chcemy dodatkowo dozwolić.
     * @var array
     */
    private const array CUSTOM_ALLOWED_EXTENSIONS = [
        'webp',
        'avif',
        'svg',
    ];

    /**
     * Przechwytuje wynik metody checkAllowedExtension.
     * Jeśli oryginalna metoda z klasy nadrzędnej zwróciła 'false' (niedozwolone),
     * sprawdzamy, czy rozszerzenie jest na naszej niestandardowej liście.
     *
     * @param Subject $subject
     * @param bool $result Wynik zwracany przez oryginalną metodę
     * @param string $extension Rozszerzenie pliku, które jest sprawdzane
     * @return bool
     */
    public function afterCheckAllowedExtension(Subject $subject, bool $result, string $extension): bool
    {
        // 1. Jeśli oryginalna metoda już zwróciła 'true', rozszerzenie jest dozwolone.
        if ($result === true) {
            return true;
        }

        $extension = strtolower($extension);

        // 2. Jeśli wynik jest 'false', sprawdzamy, czy jest to jedno z naszych niestandardowych rozszerzeń.
        if (in_array($extension, self::CUSTOM_ALLOWED_EXTENSIONS, true)) {
            return true;
        }

        // 3. W przeciwnym razie zwracamy oryginalny wynik 'false'.
        return $result;
    }
}
