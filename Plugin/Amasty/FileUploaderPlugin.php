<?php
namespace GardenLawn\Core\Plugin\Amasty;

use Amasty\ImportCore\Import\Filesystem\FileUploader;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\ObjectManager;
use Psr\Log\LoggerInterface;

class FileUploaderPlugin
{
    private $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: ObjectManager::getInstance()->get(LoggerInterface::class);
    }

    /**
     * Ensure WebP and other modern formats are allowed after initialization
     *
     * @param FileUploader $subject
     * @return void
     */
    public function afterInitialize(FileUploader $subject)
    {
        // 1. Add extensions to allowed list
        $subject->setAllowedExtensions(['webp', 'avif', 'svg']);

        // 2. Inject MIME types into protected property via Reflection
        // This ensures that 'type' field is correctly populated in the result
        try {
            $reflection = new \ReflectionClass($subject);
            // _allowedMimeTypes is defined in Magento\CatalogImportExport\Model\Import\Uploader
            // We might need to traverse up to find the property if it's private in parent (it is protected, so accessible here)

            // Find the property in the class hierarchy
            $prop = null;
            $class = $reflection;
            do {
                if ($class->hasProperty('_allowedMimeTypes')) {
                    $prop = $class->getProperty('_allowedMimeTypes');
                    break;
                }
            } while ($class = $class->getParentClass());

            if ($prop) {
                $prop->setAccessible(true);
                $mimeTypes = $prop->getValue($subject);

                $newMimeTypes = [
                    'webp' => 'image/webp',
                    'avif' => 'image/avif',
                    'svg'  => 'image/svg+xml'
                ];

                // Only add if not exists to preserve existing logic
                foreach ($newMimeTypes as $ext => $mime) {
                    if (!isset($mimeTypes[$ext])) {
                        $mimeTypes[$ext] = $mime;
                    }
                }

                $prop->setValue($subject, $mimeTypes);
            }
        } catch (\Exception $e) {
            // Log warning but don't break the import
            if ($this->logger) {
                $this->logger->warning('FileUploaderPlugin: Could not inject MIME types: ' . $e->getMessage());
            }
        }
    }

    /**
     * @throws LocalizedException
     */
    public function aroundMove(
        FileUploader $subject,
        callable $proceed,
                     $fileName,
                     $renameFileOff = false
    ) {
        $originalFileName = $fileName;
        $tempPath = null;
        $tempDirBase = null;

        try {
            // 1. Fix missing protocol
            if (!preg_match('#^https?://#i', $fileName)) {
                // Heuristic: if it contains wp-content or looks like a domain
                if (str_contains($fileName, 'wp-content/uploads') || preg_match('#^[a-z0-9.-]+\.[a-z]{2,}/#i', $fileName)) {
                    $fileName = 'https://' . ltrim($fileName, '/');
                }
            }

            // 2. Encode URL
            if (preg_match('#^https?://#i', $fileName)) {
                $fileName = $this->encodeUrl($fileName);
            }

            // 3. Validate URL and check existence
            if (filter_var($fileName, FILTER_VALIDATE_URL)) {
                if (!$this->remoteFileExists($fileName)) {
                    $this->logger->warning("FileUploaderPlugin: File not found or inaccessible: $fileName (Original: $originalFileName)");
                    // Throw exception to skip this file
                    throw new LocalizedException(
                        __('File %1 (originally %2) does not exist or is not accessible.', $fileName, $originalFileName)
                    );
                }

                // 4. Download file locally to avoid hash_file issues with remote URLs (e.g. 502 Bad Gateway)
                // Use a unique directory to preserve the original filename
                $tempDirBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('am_import_dir_');
                if (!mkdir($tempDirBase) && !is_dir($tempDirBase)) {
                    // Fallback to temp dir if mkdir fails
                    $tempDirBase = sys_get_temp_dir();
                }

                $urlPath = parse_url($fileName, PHP_URL_PATH);
                $baseName = basename($urlPath ?: '');
                if (empty($baseName)) {
                    $baseName = 'import_file_' . uniqid() . '.img';
                }
                // Sanitize filename slightly but keep it recognizable
                $baseName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $baseName);

                $tempPath = $tempDirBase . DIRECTORY_SEPARATOR . $baseName;

                if ($this->downloadFile($fileName, $tempPath)) {
                    $fileName = $tempPath;
                } else {
                    // Cleanup if download failed
                    if ($tempDirBase !== sys_get_temp_dir() && is_dir($tempDirBase)) {
                        @rmdir($tempDirBase);
                    }
                    throw new LocalizedException(
                        __('Failed to download file %1', $fileName)
                    );
                }
            }
        } catch (LocalizedException $e) {
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
            if ($tempDirBase && $tempDirBase !== sys_get_temp_dir() && is_dir($tempDirBase)) {
                @rmdir($tempDirBase);
            }
            throw $e;
        } catch (\Exception $e) {
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
            if ($tempDirBase && $tempDirBase !== sys_get_temp_dir() && is_dir($tempDirBase)) {
                @rmdir($tempDirBase);
            }
            $this->logger->error("FileUploaderPlugin Error: " . $e->getMessage());
            // Fallback to original filename if something goes wrong in our logic
            return $proceed($originalFileName, $renameFileOff);
        }

        try {
            $result = $proceed($fileName, $renameFileOff);
        } finally {
            // Cleanup temp file if it still exists (FileUploader might have moved it)
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
            // Cleanup temp dir
            if ($tempDirBase && $tempDirBase !== sys_get_temp_dir() && is_dir($tempDirBase)) {
                @rmdir($tempDirBase);
            }
        }

        return $result;
    }

    private function encodeUrl(string $url): string
    {
        $url = trim($url);
        // Pre-encode spaces to avoid parse_url issues
        $url = str_replace(' ', '%20', $url);

        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $newUrl = '';
        if (isset($parts['scheme'])) {
            $newUrl .= $parts['scheme'] . '://';
        }
        if (isset($parts['host'])) {
            $newUrl .= $parts['host'];
        }
        if (isset($parts['port'])) {
            $newUrl .= ':' . $parts['port'];
        }

        if (isset($parts['path'])) {
            // Split path and encode segments
            $pathParts = explode('/', $parts['path']);
            foreach ($pathParts as &$part) {
                // Decode first to avoid double encoding if already encoded
                $part = rawurlencode(rawurldecode($part));
            }
            $newUrl .= implode('/', $pathParts);
        }

        if (isset($parts['query'])) {
            $newUrl .= '?' . $parts['query'];
        }
        if (isset($parts['fragment'])) {
            $newUrl .= '#' . $parts['fragment'];
        }

        return $newUrl;
    }

    private function remoteFileExists(string $url): bool
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n",
                'timeout' => 5,
                'ignore_errors' => true // Fetch headers even on 404
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);

        $headers = @get_headers($url, 0, $context);

        if (!$headers) {
            return false;
        }

        // Check status code
        $statusOk = false;
        foreach ($headers as $header) {
            if (preg_match('/HTTP\/[\d.]+\s+(200|301|302)/', $header)) {
                $statusOk = true;
                break;
            }
        }

        if (!$statusOk) {
            return false;
        }

        return true;
    }

    private function downloadFile(string $url, string $destination): bool
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n",
                'timeout' => 30,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);

        $result = @copy($url, $destination, $context);

        if (!$result) {
            return false;
        }

        // Check for HTTP error codes in $http_response_header
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('#^HTTP/\d\.\d\s+(\d+)#', $header, $matches)) {
                    $code = (int)$matches[1];
                    if ($code >= 400) {
                        return false;
                    }
                }
            }
        }

        return true;
    }
}
