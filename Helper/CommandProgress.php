<?php
declare(strict_types=1);

namespace GardenLawn\Core\Helper;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

class CommandProgress
{
    private const FILE_PREFIX = 'gl_cmd_progress_';
    private const FILE_LIFETIME = 86400; // 24 hours

    private Filesystem $filesystem;
    private SerializerInterface $serializer;
    private LoggerInterface $logger;
    private DirectoryList $directoryList;

    public function __construct(
        Filesystem $filesystem,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        DirectoryList $directoryList
    ) {
        $this->filesystem = $filesystem;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->directoryList = $directoryList;
    }

    public function init(string $processId, string $message = 'Starting...'): void
    {
        // Run garbage collection occasionally (e.g., 10% chance) to clean up old files
        if (rand(1, 10) === 1) {
            $this->gc();
        }
        $this->update($processId, 0, 100, $message);
    }

    public function update(string $processId, int $current, int $total, string $message = ''): void
    {
        $data = [
            'current' => $current,
            'total' => $total,
            'percent' => $total > 0 ? round(($current / $total) * 100) : 0,
            'message' => $message,
            'finished' => false,
            'error' => false,
            'updated_at' => time()
        ];
        $this->save($processId, $data);
    }

    public function finish(string $processId, string $message = 'Completed', string $output = ''): void
    {
        $data = [
            'current' => 100,
            'total' => 100,
            'percent' => 100,
            'message' => $message,
            'finished' => true,
            'error' => false,
            'output' => $output,
            'updated_at' => time()
        ];
        $this->save($processId, $data);
    }

    public function error(string $processId, string $message): void
    {
        $data = [
            'current' => 0,
            'total' => 0,
            'percent' => 0,
            'message' => $message,
            'finished' => true,
            'error' => true,
            'updated_at' => time()
        ];
        $this->save($processId, $data);
    }

    public function get(string $processId): array
    {
        try {
            $directory = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
            $filePath = 'tmp/' . self::FILE_PREFIX . $processId . '.json';

            if (!$directory->isExist($filePath)) {
                $this->logger->warning("CommandProgress: File '{$filePath}' not found.");
                return ['finished' => true, 'error' => true, 'message' => 'Process not found or expired.'];
            }

            // Use native PHP file operations for locking support
            $absolutePath = $directory->getAbsolutePath($filePath);
            $handle = fopen($absolutePath, 'r');

            if ($handle && flock($handle, LOCK_SH)) { // Shared lock for reading
                $content = stream_get_contents($handle);
                flock($handle, LOCK_UN);
                fclose($handle);

                if ($content) {
                    return $this->serializer->unserialize($content);
                }
            }

            return ['finished' => true, 'error' => true, 'message' => 'Error reading process status (lock failed).'];

        } catch (\Exception $e) {
            $this->logger->error("CommandProgress: Error reading progress file: " . $e->getMessage());
            return ['finished' => true, 'error' => true, 'message' => 'Error reading process status.'];
        }
    }

    public function clean(string $processId): void
    {
        try {
            $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
            $filePath = 'tmp/' . self::FILE_PREFIX . $processId . '.json';

            if ($directory->isExist($filePath)) {
                $directory->delete($filePath);
            }
        } catch (\Exception $e) {
            $this->logger->warning("CommandProgress: Failed to clean up file for process {$processId}: " . $e->getMessage());
        }
    }

    private function save(string $processId, array $data): void
    {
        try {
            $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
            $filePath = 'tmp/' . self::FILE_PREFIX . $processId . '.json';
            $absolutePath = $directory->getAbsolutePath($filePath);

            // Ensure directory exists
            $directory->create('tmp');

            // Use native PHP file operations for locking support
            $handle = fopen($absolutePath, 'c+'); // Open for reading and writing

            if ($handle && flock($handle, LOCK_EX)) { // Exclusive lock for writing
                ftruncate($handle, 0); // Truncate file
                fwrite($handle, $this->serializer->serialize($data));
                fflush($handle); // Flush output before releasing the lock
                flock($handle, LOCK_UN);
                fclose($handle);
            } else {
                $this->logger->error("CommandProgress: Could not lock file for writing: {$filePath}");
            }

        } catch (\Exception $e) {
            $this->logger->error("CommandProgress: Error saving progress file: " . $e->getMessage());
        }
    }

    /**
     * Garbage Collection: Remove old progress files
     */
    private function gc(): void
    {
        try {
            $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
            if (!$directory->isExist('tmp')) {
                return;
            }

            $files = $directory->read('tmp');
            $now = time();

            foreach ($files as $file) {
                if (strpos($file, self::FILE_PREFIX) !== false) {
                    $stat = $directory->stat($file);
                    if ($now - $stat['mtime'] > self::FILE_LIFETIME) {
                        $directory->delete($file);
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore GC errors
        }
    }
}
