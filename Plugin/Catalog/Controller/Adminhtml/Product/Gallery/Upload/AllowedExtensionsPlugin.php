<?php
/**
 * Plugin to modify the protected $allowedMimeTypes property in the Catalog Gallery Upload controller
 * to support WebP, AVIF, and SVG images during product media upload.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Catalog\Controller\Adminhtml\Product\Gallery\Upload;

use Magento\Catalog\Controller\Adminhtml\Product\Gallery\Upload;
use Closure;
use ReflectionClass;

class AllowedExtensionsPlugin
{
    /**
     * Nowe dozwolone typy MIME i rozszerzenia.
     * WebP, AVIF i SVG.
     */
    private const array EXTENSIONS_TO_ADD = [
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'svg' => 'image/svg+xml',
    ];

    /**
     * Around plugin for the __construct method.
     * We use around construct to modify the protected $allowedMimeTypes property
     * before the controller logic is executed.
     *
     * @param Upload $subject
     * @param Closure $proceed
     * @param array $args
     * @return Upload
     */
    public function aroundConstruct(
        Upload $subject,
        Closure $proceed,
        array $args
    ): Upload {
        // 1. Wywołaj oryginalny konstruktor.
        // Upewni to, że wszystkie oryginalne zależności zostały wstrzyknięte.
        $proceed(...$args);

        try {
            // 2. Użyj ReflectionClass, aby uzyskać dostęp do chronionej właściwości $allowedMimeTypes.
            $reflection = new ReflectionClass($subject);
            $property = $reflection->getProperty('allowedMimeTypes');
            $property->setAccessible(true);

            // 3. Pobierz oryginalne wartości i dodaj nowe.
            $originalMimeTypes = $property->getValue($subject);

            // Scal stare i nowe typy MIME
            $updatedMimeTypes = array_merge($originalMimeTypes, self::EXTENSIONS_TO_ADD);

            // 4. Ustaw zaktualizowaną wartość z powrotem.
            $property->setValue($subject, $updatedMimeTypes);
            $property->setAccessible(false);

        } catch (\ReflectionException $e) {
            // Logowanie błędu, jeśli refleksja zawiedzie.
            // W środowisku produkcyjnym Magento jest to mało prawdopodobne.
        }

        return $subject;
    }
}
