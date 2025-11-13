<?php
/**
 * Plugin to extend Magento\Framework\Image\Adapter\ImageMagick to support WebP and AVIF formats
 * for both opening/processing and saving into cache.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Framework\Image\Adapter;

use Exception;
use Imagick;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Image\Adapter\ImageMagick as Subject;
use Magento\Framework\Phrase;
use ReflectionClass;

class ImageMagick
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
     * @throws LocalizedException
     * @throws Exception
     */
    public function aroundOpen(
        Subject  $subject,
        callable $proceed,
        string $filename // Poprawna sygnatura
    ): Subject {
        $filePath = $filename;
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Pliki SVG są wektorowe i są ignorowane przez adaptery rastrowe
        if ($extension === self::EXTENSION_SVG) {
            // Kontynuujemy oryginalną metodę, która zazwyczaj ignoruje SVG lub zrzuca błąd,
            // jeśli plik nie jest prawidłowym formatem rastrowym.
            return $proceed($filePath);
        }

        try {
            // Spróbuj użyć oryginalnej metody open() (obsługuje standardowe formaty)
            return $proceed($filePath);
        } catch (LocalizedException $e) {
            // Jeśli oryginalne open() zawiedzie, spróbuj nowoczesnych formatów.
            if ($extension === self::EXTENSION_WEBP || $extension === self::EXTENSION_AVIF) {
                if (!extension_loaded('imagick')) {
                    throw new LocalizedException(new Phrase('Required PHP extension \'imagick\' was not loaded.'));
                }

                try {
                    // 1. Utwórz nową instancję Imagick i spróbuj odczytać plik
                    $imageHandler = new Imagick();
                    $imageHandler->setFormat($extension);
                    $imageHandler->readImage($filePath);

                    // 2. Użyj Refleksji, aby ustawić chronione właściwości adaptera
                    $reflection = new ReflectionClass($subject);

                    // Ustawienie $_fileName, które mogło nie zostać ustawione w oryginalnej metodzie
                    $fileNameProperty = $reflection->getProperty('_fileName');
                    $fileNameProperty->setAccessible(true);
                    $fileNameProperty->setValue($subject, $filePath);

                    // Ustawienie chronionego obiektu $_imageHandler
                    $imageHandlerProperty = $reflection->getProperty('_imageHandler');
                    $imageHandlerProperty->setAccessible(true);
                    $imageHandlerProperty->setValue($subject, $imageHandler);

                    // 3. Odśwież wymiary i kontynuuj konfigurację
                    $subject->refreshImageDimensions();
                    // Musimy wywołać metody konfigurujące, które są wywoływane w oryginalnej open()
                    $this->invokeProtectedMethod($subject, 'getColorspace');
                    $this->invokeProtectedMethod($subject, 'maybeConvertColorspace');
                    $subject->backgroundColor();
                    $subject->getMimeType();

                    return $subject;
                } catch (Exception $readException) {
                    // Jeśli nowoczesny format się nie uda, zgłoś pierwotny wyjątek od Magento,
                    // a nie błąd Imagick, aby zachować spójność.
                    throw $e;
                }
            } else {
                // Ponowne zgłoszenie oryginalnego wyjątku dla nieznanych formatów
                throw $e;
            }
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
     * @throws Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundSave(
        Subject  $subject,
        callable $proceed,
        string   $destination = null, // Poprawna sygnatura
        string   $newName = null      // Poprawna sygnatura
    ): Subject {
        $destinationPath = $this->invokeProtectedMethod($subject, '_prepareDestination', [$destination, $newName]);
        $extension = strtolower(pathinfo($destinationPath, PATHINFO_EXTENSION));

        $imageHandler = $this->getImageHandler($subject);

        switch ($extension) {
            case self::EXTENSION_WEBP:
                // Ustawienie formatu i jakości dla WebP
                $this->invokeProtectedMethod($subject, '_applyOptions');
                $imageHandler->stripImage();
                $imageHandler->setImageFormat('webp');
                $imageHandler->setCompressionQuality($subject->getQuality() ?: 80);
                $imageHandler->writeImage($destinationPath);
                break;
            case self::EXTENSION_AVIF:
                // Ustawienie formatu i jakości dla AVIF
                $this->invokeProtectedMethod($subject, '_applyOptions');
                $imageHandler->stripImage();
                $imageHandler->setImageFormat('avif');
                $imageHandler->setCompressionQuality($subject->getQuality() ?: 80);
                $imageHandler->writeImage($destinationPath);
                break;
            case self::EXTENSION_SVG:
            default:
                // Dla SVG i innych formatów użyj oryginalnej metody
                $proceed($destination, $newName);
                break;
        }

        return $subject;
    }

    /**
     * Helper to get the protected $_imageHandler object from the adapter using reflection.
     *
     * @param Subject $subject
     * @return Imagick
     * @throws \ReflectionException
     * @throws Exception
     */
    private function getImageHandler(Subject $subject): Imagick
    {
        $reflection = new ReflectionClass($subject);
        $imageHandlerProperty = $reflection->getProperty('_imageHandler');
        $imageHandlerProperty->setAccessible(true);
        $handler = $imageHandlerProperty->getValue($subject);

        if (!$handler instanceof Imagick) {
            throw new Exception('Image handler is not an instance of Imagick.');
        }

        return $handler;
    }

    /**
     * Helper to invoke a protected method on the subject using reflection.
     *
     * @param Subject $subject
     * @param string $methodName
     * @param array $args
     * @return mixed
     * @throws \ReflectionException
     */
    private function invokeProtectedMethod(Subject $subject, string $methodName, array $args = []): mixed
    {
        $reflection = new ReflectionClass($subject);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($subject, $args);
    }
}
