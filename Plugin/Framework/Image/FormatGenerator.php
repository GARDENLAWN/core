<?php
/**
 * Plugin to automatically generate WebP and AVIF versions of images whenever
 * an original file (JPG/PNG) is saved to the image cache.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Framework\Image;

use Magento\Framework\Image;
use Magento\Framework\Image\Adapter\AdapterInterface;
use Magento\Framework\Image\Adapter\Gd2; // Import dla adaptera GD2
use Magento\Framework\Image\Adapter\ImageMagick; // ZAKTUALIZOWANY IMPORT dla adaptera ImageMagick
use Psr\Log\LoggerInterface;

class FormatGenerator
{
    private const string EXTENSION_WEBP = 'webp';
    private const string EXTENSION_AVIF = 'avif';

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * After plugin for the save() method in \Magento\Framework\Image.
     * Triggers the saving of WebP and AVIF alternatives after the original is saved.
     *
     * @param Image $subject
     * @param Image $result
     * @param string|null $destination
     * @return Image
     */
    public function afterSave(
        Image $subject,
        Image $result,
        string $destination = null
    ): Image {
        // Kontynuuj tylko, jeśli jest to standardowy format rastrowy, który ma być konwertowany
        $extension = strtolower(pathinfo($destination, PATHINFO_EXTENSION));

        if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            return $result;
        }

        // Zapisz AVIF
        $this->saveAlternativeFormat($subject, $destination, self::EXTENSION_AVIF);

        // Zapisz WebP
        $this->saveAlternativeFormat($subject, $destination, self::EXTENSION_WEBP);

        return $result;
    }

    /**
     * Saves the image in a specified alternative format (WebP or AVIF).
     *
     * @param Image $imageProcessor
     * @param string $originalDestination
     * @param string $newExtension
     * @return void
     */
    private function saveAlternativeFormat(
        Image $imageProcessor,
        string $originalDestination,
        string $newExtension
    ): void {
        try {
            // Wymuś przekazanie obrazu do adaptera
            /** @var AdapterInterface $adapter */
            $adapter = $imageProcessor->getAdapter();

            // Zaktualizowany warunek: Sprawdza, czy adapter jest instancją Gd2 LUB ImageMagick
            if ($adapter instanceof Gd2 || $adapter instanceof ImageMagick) {

                // Utwórz docelową ścieżkę z nowym rozszerzeniem (np. .jpg.webp)
                $newDestination = $originalDestination . '.' . $newExtension;

                // Użyj adaptera bezpośrednio, aby zapisać obraz w nowym formacie.
                $adapter->save($newDestination);
            }
        } catch (\Exception $e) {
            // Logowanie błędu, jeśli np. serwer nie obsługuje WebP/AVIF
            $this->logger->warning(
                sprintf('Failed to generate %s image for cache: %s', $newExtension, $e->getMessage())
            );
        }
    }
}
