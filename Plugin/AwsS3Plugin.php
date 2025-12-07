<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin;

use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use Magento\AwsS3\Driver\AwsS3 as CoreAwsS3;
use Magento\Framework\Filesystem\DriverInterface;
use League\Flysystem\FilesystemException as FlysystemFilesystemException;
use League\Flysystem\UnableToRetrieveMetadata;
use Psr\Log\LoggerInterface;
use ReflectionException;

/**
 * Rozszerzenie oryginalnego sterownika AwsS3 w celu dodania nagłówka CacheControl do obrazków.
 */
class AwsS3Plugin extends CoreAwsS3
{
    private const string CACHE_CONTROL_VALUE = 'max-age=31536000, public';

    /**
     * Zwraca bazową konfigurację S3 z dodanym nagłówkiem CacheControl, jeśli jest to obrazek.
     *
     * @param string|null $path Opcjonalna ścieżka do sprawdzenia rozszerzenia.
     * @param bool $isImageContent Czy zawartość jest obrazkiem (wymagane dla filePutContents).
     * @return array
     */
    private function getExtendedConfig(?string $path = null, bool $isImageContent = false): array
    {
        // Użycie refleksji do dostępu do prywatnej stałej CONFIG z klasy bazowej
        $reflectionClass = new \ReflectionClass(CoreAwsS3::class);
        $config = $reflectionClass->getConstant('CONFIG');

        // Sprawdzanie po rozszerzeniu, co jest bezpieczniejsze w copy/rename/fileClose
        if ($isImageContent || ($path && preg_match('/\.(jpg|jpeg|png|gif|webp|avif|svg)$/i', $path))) {
            $config['CacheControl'] = self::CACHE_CONTROL_VALUE;
        }

        return $config;
    }

    /**
     * Pobiera prywatną właściwość z klasy bazowej za pomocą refleksji.
     *
     * @param string $propertyName
     * @return mixed
     * @throws ReflectionException
     */
    private function getPrivateProperty(string $propertyName): mixed
    {
        $reflectionClass = new \ReflectionClass(CoreAwsS3::class);
        $property = $reflectionClass->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($this);
    }

    /**
     * Wywołuje prywatną metodę z klasy bazowej za pomocą refleksji.
     *
     * @param string $methodName
     * @param array $args
     * @return mixed
     * @throws ReflectionException
     */
    private function callPrivateMethod(string $methodName, array $args): mixed
    {
        $reflectionClass = new \ReflectionClass(CoreAwsS3::class);
        $method = $reflectionClass->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($this, $args);
    }

    /**
     * @inheritDoc
     * Nadpisanie metody filePutContents w celu dodania CacheControl dla obrazków.
     */
    public function filePutContents($path, $content, $mode = null): bool|int
    {
        // Użycie refleksji do wywołania prywatnej metody normalizeRelativePath
        $path = $this->callPrivateMethod('normalizeRelativePath', [$path, true]);

        // Sprawdzenie, czy zawartość jest obrazkiem
        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        $isImageContent = (false !== ($imageSize = @getimagesizefromstring($content)));

        $config = $this->getExtendedConfig(null, $isImageContent);

        if ($isImageContent) {
            $config['Metadata'] = [
                'image-width' => $imageSize[0],
                'image-height' => $imageSize[1]
            ];
        }

        try {
            /** @var FilesystemAdapter $adapter */
            $adapter = $this->getPrivateProperty('adapter');

            $adapter->write($path, $content, new Config($config));
            // To jest bezpieczniejsze niż wywołanie adapter->fileSize($path)->fileSize()
            return true;
        } catch (FlysystemFilesystemException|UnableToRetrieveMetadata $e) {
            /** @var LoggerInterface $logger */
            $logger = $this->getPrivateProperty('logger');
            $logger->error($e->getMessage());
            return false;
        }
    }

    /**
     * @inheritDoc
     * Nadpisanie metody copy w celu dodania CacheControl dla obrazków.
     * @throws ReflectionException
     */
    public function copy($source, $destination, ?DriverInterface $targetDriver = null): bool
    {
        // Użycie refleksji do wywołania prywatnej metody normalizeRelativePath
        $sourcePath = $this->callPrivateMethod('normalizeRelativePath', [$source, true]);
        $destinationPath = $this->callPrivateMethod('normalizeRelativePath', [$destination, true]);

        $config = $this->getExtendedConfig($sourcePath);

        try {
            /** @var FilesystemAdapter $adapter */
            $adapter = $this->getPrivateProperty('adapter');

            $adapter->copy(
                $sourcePath,
                $destinationPath,
                new Config($config)
            );
        } catch (FlysystemFilesystemException $e) {
            /** @var LoggerInterface $logger */
            $logger = $this->getPrivateProperty('logger');
            $logger->error($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * @inheritDoc
     * Nadpisanie metody rename (przeniesienie) w celu dodania CacheControl dla obrazków.
     * @throws ReflectionException
     */
    public function rename($oldPath, $newPath, ?DriverInterface $targetDriver = null): bool
    {
        if ($oldPath === $newPath) {
            return true;
        }

        // Użycie refleksji do wywołania prywatnej metody normalizeRelativePath
        $oldPathRelative = $this->callPrivateMethod('normalizeRelativePath', [$oldPath, true]);
        $newPathRelative = $this->callPrivateMethod('normalizeRelativePath', [$newPath, true]);

        $config = $this->getExtendedConfig($oldPathRelative);

        try {
            /** @var FilesystemAdapter $adapter */
            $adapter = $this->getPrivateProperty('adapter');

            $adapter->move(
                $oldPathRelative,
                $newPathRelative,
                new Config($config)
            );
        } catch (FlysystemFilesystemException $e) {
            /** @var LoggerInterface $logger */
            $logger = $this->getPrivateProperty('logger');
            $logger->error($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * @inheritDoc
     * Nadpisanie metody fileClose w celu dodania CacheControl przy zapisie strumienia.
     * @throws ReflectionException
     * @throws FlysystemFilesystemException
     */
    public function fileClose($resource): bool
    {
        if (!is_resource($resource)) {
            return false;
        }
        //phpcs:disable
        $meta = stream_get_meta_data($resource);
        //phpcs:enable

        /** @var array $streams */
        $streams = $this->getPrivateProperty('streams');

        foreach ($streams as $path => $stream) {
            // phpcs:ignore
            if (stream_get_meta_data($stream)['uri'] === $meta['uri']) {
                if (isset($meta['seekable']) && $meta['seekable']) {
                    // rewind the file pointer to make sure the full content of the file is saved
                    $this->fileSeek($resource, 0);
                }

                // Użycie nowej konfiguracji opartej na rozszerzeniu ścieżki
                $config = $this->getExtendedConfig($path);

                /** @var FilesystemAdapter $adapter */
                $adapter = $this->getPrivateProperty('adapter');
                $adapter->writeStream($path, $resource, new Config($config));

                // Ręczne usunięcie ścieżki z prywatnej tablicy streams
                $reflectionClass = new \ReflectionClass(CoreAwsS3::class);
                $streamsProperty = $reflectionClass->getProperty('streams');
                $streamsProperty->setAccessible(true);
                unset($streams[$path]);
                $streamsProperty->setValue($this, $streams);

                // phpcs:ignore Magento2.Functions.DiscouragedFunction.DiscouragedWithAlternative
                return fclose($stream);
            }
        }

        return false;
    }
}
