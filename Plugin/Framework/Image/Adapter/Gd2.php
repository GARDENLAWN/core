<?php
/**
 * Plugin to extend Magento\Framework\Image\Adapter\Gd2 to support WebP and AVIF formats
 * for both opening/processing and saving into cache.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Framework\Image\Adapter;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Image\Adapter\Gd2 as Subject;
use Magento\Framework\Phrase;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty; // Dodane, aby jawnie używać klasy

class Gd2
{
    private const string EXTENSION_WEBP = 'webp';
    private const string EXTENSION_AVIF = 'avif';
    private const string EXTENSION_SVG = 'svg';

    /**
     * Around plugin for the open() method to support WebP and AVIF.
     *
     * @param Subject $subject
     * @param callable $proceed
     * @param string $filename Ścieżka pliku przekazana do oryginalnej metody open().
     * @return Subject
     * @throws LocalizedException|ReflectionException
     */
    public function aroundOpen(
        Subject  $subject,
        callable $proceed,
        string $filename
    ): Subject {
        $originalFilePath = $filename;
        $extension = strtolower(pathinfo($originalFilePath, PATHINFO_EXTENSION));
        $finalFilePath = $originalFilePath;

        if ($extension === self::EXTENSION_SVG) {
            // Przekazujemy ścieżkę do oryginalnej metody, która zazwyczaj nie obsługuje SVG.
            return $proceed($originalFilePath);
        }

        try {
            // Spróbuj wykonać oryginalną logikę (obsługuje standardowe formaty JPG, PNG, GIF)
            return $proceed($originalFilePath);
        } catch (\Exception $e) {

            $imageResource = null;
            // Sprawdzanie IMAGETYPE_WEBP (18) i IMAGETYPE_AVIF (19)
            $fileType = (function_exists('exif_imagetype') && is_file($originalFilePath))
                ? (int)exif_imagetype($originalFilePath)
                : null;

            $isWebp = ($extension === self::EXTENSION_WEBP || $fileType === 18); // 18 to IMAGETYPE_WEBP
            $isAvif = ($extension === self::EXTENSION_AVIF || $fileType === 19); // 19 to IMAGETYPE_AVIF (zależy od wersji PHP)

            // --- Logika dodawania rozszerzenia do pliku tymczasowego ---
            if ($isWebp && $extension === '') {
                $finalFilePath = $originalFilePath . '.' . self::EXTENSION_WEBP;
                // Tworzenie linku symbolicznego (najszybsza opcja)
                if (symlink($originalFilePath, $finalFilePath)) {
                    // Pomyślnie utworzono symlink
                } else {
                    // W przypadku niepowodzenia (np. ograniczenia bezpieczeństwa), używamy kopii
                    copy($originalFilePath, $finalFilePath);
                }
            } elseif ($isAvif && $extension === '') {
                $finalFilePath = $originalFilePath . '.' . self::EXTENSION_AVIF;
                if (symlink($originalFilePath, $finalFilePath)) {
                    // Pomyślnie utworzono symlink
                } else {
                    copy($originalFilePath, $finalFilePath);
                }
            }
            // -----------------------------------------------------------

            try {
                if ($isWebp) {
                    if (!function_exists('imagecreatefromwebp')) {
                        throw new LocalizedException(new Phrase('GD library compiled without WEBP support.'));
                    }
                    // Używamy pliku z rozszerzeniem
                    $imageResource = imagecreatefromwebp($finalFilePath);
                } elseif ($isAvif) {
                    if (!function_exists('imagecreatefromavif')) {
                        throw new LocalizedException(new Phrase('GD library compiled without AVIF support (PHP 8.1+ required).'));
                    }
                    // Używamy pliku z rozszerzeniem
                    $imageResource = imagecreatefromavif($finalFilePath);
                } else {
                    // Jeśli plik nie jest ani WebP, ani AVIF, rzucamy oryginalny wyjątek
                    throw $e;
                }
            } finally {
                // KLUCZOWE: Usuwamy tymczasowy symlink lub kopię, aby nie zaśmiecać /tmp
                if ($finalFilePath !== $originalFilePath && file_exists($finalFilePath)) {
                    @unlink($finalFilePath); // Użycie @ dla uniknięcia ostrzeżeń
                }
            }

            // Jeśli $imageResource jest false, oznacza to, że plik jest uszkodzony lub ma nieprawidłową sygnaturę
            if (!$imageResource) {
                throw new LocalizedException(new Phrase('Unsupported image format or corrupt file. File: %1', [$originalFilePath]));
            }

            // --- Logika Refleksji ---
            // Zapewniamy, że używamy klasy bazowej Subject::class

            // Ustawienie $_fileName (używamy oryginalnej ścieżki, nie tej z rozszerzeniem)
            $this->setProtectedProperty($subject, '_fileName', $originalFilePath);

            // Ustawienie $_gdImage (zasób GD)
            $this->setProtectedProperty($subject, '_gdImage', $imageResource);

            $subject->refreshImageDimensions();
            return $subject;
        }
    }

    /**
     * Around plugin for the save() method to support WebP and AVIF for cache generation.
     *
     * @param Subject $subject
     * @param callable $proceed
     * @param string|null $destination
     * @param string|null $newName
     * @return Subject
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws ReflectionException
     */
    public function aroundSave(
        Subject  $subject,
        callable $proceed,
                 $destination = null,
                 $newName = null
    ): Subject {
        // Użyj refleksji, aby pobrać faktyczną ścieżkę docelową
        $destinationPath = $this->invokeProtectedMethod($subject, '_prepareDestination', [$destination, $newName]);

        $extension = strtolower(pathinfo($destinationPath, PATHINFO_EXTENSION));

        // Jeśli ścieżka docelowa nie ma rozszerzenia LUB nie jest to format, który obsługujemy,
        // to delegujemy to do oryginalnej metody, która domyślnie użyje formatu JED (albo z _fileType).
        if ($extension !== self::EXTENSION_WEBP && $extension !== self::EXTENSION_AVIF) {
            $proceed($destination, $newName);
            return $subject;
        }

        $gdImage = $this->getGdImage($subject);

        switch ($extension) {
            case self::EXTENSION_WEBP:
                // GD używa skali jakości 0-100 dla WebP
                imagewebp($gdImage, $destinationPath, $subject->getQuality() ?: 80);
                return $subject;
            case self::EXTENSION_AVIF:
                // GD używa skali jakości 0-100 dla AVIF (wymaga PHP 8.1+)
                if (function_exists('imageavif')) {
                    imageavif($gdImage, $destinationPath, $subject->getQuality() ?: 80);
                    return $subject;
                }
                // Jeśli AVIF nie jest obsługiwane przez funkcję, rzuć błąd.
                throw new LocalizedException(new Phrase('GD library compiled without AVIF support (PHP 8.1+ required).'));
        }

        // Powyższy kod już obsługuje wszystkie przypadki, ale dla formalności:
        return $subject;
    }

    /**
     * Helper to get the protected $_gdImage resource from the adapter using reflection.
     *
     * @param Subject $subject
     * @return resource|false
     * @throws ReflectionException
     */
    private function getGdImage(Subject $subject)
    {
        // Używamy Subject::class, aby odwołać się do oryginalnej klasy bazowej (Gd2)
        $reflectionProperty = new ReflectionProperty(Subject::class, '_gdImage');
        $reflectionProperty->setAccessible(true);
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        return $reflectionProperty->getValue($subject);
    }

    /**
     * Helper to invoke a protected method on the subject using reflection.
     * Używane do wywołania _prepareDestination w aroundSave
     *
     * @param Subject $subject
     * @param string $methodName
     * @param array $args
     * @return mixed
     * @throws \ReflectionException
     */
    private function invokeProtectedMethod(Subject $subject, string $methodName, array $args = [])
    {
        // Używamy ReflectionClass z Subject::class
        $reflection = new ReflectionClass(Subject::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($subject, $args);
    }

    /**
     * Helper to set a protected property value using reflection.
     *
     * @param Subject $subject
     * @param string $propertyName
     * @param mixed $value
     * @throws ReflectionException
     */
    private function setProtectedProperty(Subject $subject, string $propertyName, $value): void
    {
        // Bezpośrednie użycie ReflectionProperty(Subject::class, ...) jest najbardziej odporne
        $reflectionProperty = new ReflectionProperty(Subject::class, $propertyName);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($subject, $value);
    }
}
