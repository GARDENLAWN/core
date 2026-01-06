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

        // Optional: Check Content-Type to avoid soft 404s (HTML pages)
        // But be careful, some CDNs might return weird content types.
        // Generally images should be image/...
        /*
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                if (stripos($header, 'text/html') !== false) {
                    return false; // It's an HTML page, not an image
                }
            }
        }
        */

        return true;
    }
}
