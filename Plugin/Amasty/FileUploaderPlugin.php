<?php
namespace GardenLawn\Core\Plugin\Amasty;

use Amasty\ImportCore\Import\Filesystem\FileUploader;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\ObjectManager;
use Psr\Log\LoggerInterface;

class FileUploaderPlugin
{
    private LoggerInterface $logger;

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
    public function afterInitialize(FileUploader $subject): void
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
            $this->logger->warning('FileUploaderPlugin: Could not inject MIME types: ' . $e->getMessage());
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

        try {
            // 1. Fix missing protocol
            if (!preg_match('#^https?://#i', $fileName)) {
                // Heuristic: if it contains wp-content or looks like a domain
                // Use stripos for case-insensitive check
                if (stripos($fileName, 'wp-content/uploads') !== false || preg_match('#^[a-z0-9.-]+\.[a-z]{2,}/#i', $fileName)) {
                    $fileName = 'https://' . ltrim($fileName, '/');
                }
            }

            // 2. Encode URL (only spaces)
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
            }
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error("FileUploaderPlugin Error: " . $e->getMessage());
            // Fallback to original filename if something goes wrong in our logic
            return $proceed($originalFileName, $renameFileOff);
        }

        return $proceed($fileName, $renameFileOff);
    }

    private function encodeUrl(string $url): string
    {
        $url = trim($url);
        // Only encode spaces to avoid altering other characters that might be valid or specific to the source
        // This preserves the original link as much as possible while fixing the most common issue
        return str_replace(' ', '%20', $url);
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
}
