<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\AwsS3;

use Closure;
use League\Flysystem\Config;
use League\Flysystem\FilesystemException as FlysystemFilesystemException;
use League\Flysystem\UnableToRetrieveMetadata;
use Magento\AwsS3\Driver\AwsS3;
use Psr\Log\LoggerInterface;

/**
 * Plugin to add Cache-Control metadata to files uploaded to S3.
 */
class DriverPlugin
{
    private const string CACHE_CONTROL = 'public, max-age=31536000';

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Używamy LoggerInterface, ponieważ jest wstrzykiwany w oryginalnej klasie,
     * a my chcemy zachować tę samą logikę obsługi błędów.
     * * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Around plugin for filePutContents to add Cache-Control header to S3 files.
     * * @param AwsS3 $subject
     * @param Closure $proceed
     * @param string $path
     * @param string $content
     * @param string|null $mode
     * @return bool|int
     * @throws \ReflectionException
     */
    public function aroundFilePutContents(
        AwsS3 $subject,
        Closure $proceed,
        $path,
        $content,
        $mode = null
    ): bool|int
    {
        // Musimy użyć refleksji, aby uzyskać dostęp do prywatnych właściwości i metod,
        // takich jak adapter, logger oraz stała CONFIG i metoda normalizeRelativePath,
        // które są używane w oryginalnej implementacji filePutContents.

        $reflection = new \ReflectionClass($subject);

        // 1. Znormalizuj ścieżkę, tak jak to robi oryginalna metoda
        $normalizeRelativePath = $reflection->getMethod('normalizeRelativePath');
        $normalizeRelativePath->setAccessible(true);
        $normalizedPath = $normalizeRelativePath->invoke($subject, $path, true);

        // 2. Pobierz konfigurację domyślną (self::CONFIG)
        $configConstant = $reflection->getConstant('CONFIG');
        $config = $configConstant;

        // 3. Dodaj metadane rozmiaru obrazu, tak jak to robi oryginalna metoda
        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        if (false !== ($imageSize = @getimagesizefromstring($content))) {
            $config['Metadata'] = [
                'image-width' => $imageSize[0],
                'image-height' => $imageSize[1]
            ];
        }

        // 4. *** DODANIE TWOJEJ METADANY CacheControl ***
        $config['CacheControl'] = self::CACHE_CONTROL;

        // 5. Pobierz adapter Flysystem
        $adapterProperty = $reflection->getProperty('adapter');
        $adapterProperty->setAccessible(true);
        $adapter = $adapterProperty->getValue($subject);

        try {
            // 6. Użyj adaptera do zapisu z nową konfiguracją
            $adapter->write($normalizedPath, $content, new Config($config));

            // 7. Zwróć wynik sprawdzenia rozmiaru pliku (jak w oryginalnej metodzie)
            // Użycie fileExists, aby uniknąć konieczności dostępu do metody fileSize
            return $adapter->fileExists($normalizedPath) ? strlen($content) : true;

        } catch (FlysystemFilesystemException | UnableToRetrieveMetadata $e) {
            // 8. Logowanie błędu, tak jak w oryginalnej metodzie
            $this->logger->error($e->getMessage());
            return false;
        }
    }
}
