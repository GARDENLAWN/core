<?php
declare(strict_types=1);

namespace GardenLawn\Core\Helper;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;

class CommandProgress
{
    private const CACHE_PREFIX = 'gl_cmd_progress_';
    private const CACHE_LIFETIME = 3600; // 1 hour

    private CacheInterface $cache;
    private SerializerInterface $serializer;

    public function __construct(
        CacheInterface $cache,
        SerializerInterface $serializer
    ) {
        $this->cache = $cache;
        $this->serializer = $serializer;
    }

    public function init(string $processId, string $message = 'Starting...'): void
    {
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
            'error' => false
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
            'output' => $output
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
            'error' => true
        ];
        $this->save($processId, $data);
    }

    public function get(string $processId): array
    {
        $data = $this->cache->load(self::CACHE_PREFIX . $processId);
        if (!$data) {
            return ['finished' => true, 'error' => true, 'message' => 'Process not found or expired.'];
        }
        return $this->serializer->unserialize($data);
    }

    private function save(string $processId, array $data): void
    {
        $this->cache->save(
            $this->serializer->serialize($data),
            self::CACHE_PREFIX . $processId,
            [],
            self::CACHE_LIFETIME
        );
    }
}
