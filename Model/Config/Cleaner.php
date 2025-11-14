<?php
/**
 * Copyright © GardenLawn. All rights reserved.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Model\Config;

use Magento\Framework\App\Cache\Type\Config;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface; // Używamy Psr\Log\LoggerInterface
use Magento\Framework\App\Filesystem\DirectoryList; // Do ścieżki logów
use Exception;

class Cleaner
{
    private const string DEBUG_LOG_FILE = 'gardenlawn_core_debug.log';
    public const XML_PATH_CLEANER_ENABLED = 'gardenlawn_core/cleanup/enabled';
    public const XML_PATH_CLEANER_DRY_RUN = 'gardenlawn_core/cleanup/is_dry_run';

    /** @var ResourceConnection */
    private ResourceConnection $resourceConnection;

    /** @var AdapterInterface */
    private AdapterInterface $connection; // Inicjalizujemy w konstruktorze

    /** @var ScopeConfigInterface */
    private ScopeConfigInterface $scopeConfig;

    /** @var TypeListInterface */
    private TypeListInterface $cacheTypeList;

    /** @var LoggerInterface */
    private LoggerInterface $logger; // Wstrzykujemy logger

    /** @var DirectoryList */
    private DirectoryList $directoryList; // Wstrzykujemy DirectoryList

    /** @var array|null */
    private ?array $storeWebsiteMap = null; // Cache dla mapowania store_id na website_id

    public function __construct(
        ResourceConnection $resourceConnection,
        ScopeConfigInterface $scopeConfig,
        TypeListInterface $cacheTypeList,
        LoggerInterface $logger,
        DirectoryList $directoryList
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->scopeConfig = $scopeConfig;
        $this->cacheTypeList = $cacheTypeList;
        $this->logger = $logger;
        $this->directoryList = $directoryList;
        $this->connection = $this->resourceConnection->getConnection(); // Inicjalizacja połączenia
    }

    /**
     * Clean redundant config entries from core_config_data table.
     *
     * @param bool|null $isDryRunOverride
     * @return array
     * @throws Exception
     */
    public function cleanRedundantConfig(?bool $isDryRunOverride = null): array
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_CLEANER_ENABLED)) {
            $this->logger->info('GardenLawn_Core Cleaner is disabled in system configuration.');
            return ['deleted_count' => 0, 'messages' => ['Cleaner is disabled.']];
        }

        $isDryRun = $isDryRunOverride ?? $this->scopeConfig->isSetFlag(self::XML_PATH_CLEANER_DRY_RUN);

        $this->logger->info('--- Starting Redundant Config Cleanup ---');
        $this->logger->info(sprintf('Log file: %s', $this->directoryList->getPath(DirectoryList::VAR_DIR) . '/log/' . self::DEBUG_LOG_FILE));

        $tableName = $this->connection->getTableName('core_config_data');
        $idsToDelete = [];
        $messages = [];

        if ($isDryRun) {
            $messages[] = 'Dry run mode enabled. No data will be deleted.';
            $this->logger->info('Dry run mode enabled.');
        }

        // Process default-level configs (usuwanie wartości, które są null w bazie)
        $messages[] = 'Processing default-level configurations (cleaning null values)...';
        $defaultConfigs = $this->getConfigData(ScopeConfigInterface::SCOPE_TYPE_DEFAULT);

        foreach ($defaultConfigs as $config) {
            if ($config['value'] === null) {
                $idsToDelete[] = $config['config_id'];
                $messages[] = sprintf('Found redundant (null value) default entry for path: %s [ID: %d]', $config['path'], $config['config_id']);
                $this->logger->info(sprintf("Marked for deletion (ID: %d) as null default value | Value: '%s'", $config['config_id'], $config['value']));
            }
        }

        // Process website-level configs (redundancja względem zakresu default/XML)
        $messages[] = 'Processing website-level configurations...';
        $websiteConfigs = $this->getConfigData(ScopeInterface::SCOPE_WEBSITES);

        foreach ($websiteConfigs as $config) {
            $defaultValue = $this->scopeConfig->getValue($config['path']);
            if ($config['value'] === $defaultValue) {
                $idsToDelete[] = $config['config_id'];
                $messages[] = sprintf('Found redundant entry for path: %s [ID: %d]', $config['path'], $config['config_id']);
                $this->logger->info(sprintf("Marked for deletion (ID: %d) | Value: '%s'", $config['config_id'], $config['value']));
            }
        }

        // Process store-level configs (redundancja względem zakresu website)
        $messages[] = 'Processing store-level configurations...';
        $storeConfigs = $this->getConfigData(ScopeInterface::SCOPE_STORES);
        $this->buildStoreWebsiteMap(); // Build map once

        foreach ($storeConfigs as $config) {
            $websiteId = $this->getWebsiteIdForStore((int)$config['scope_id']);

            if ($websiteId === null) {
                $this->logger->warning(sprintf("Skipping store config (ID: %d) because website ID not found for store_id: %s", $config['config_id'], $config['scope_id']));
                continue;
            }

            $websiteValue = $this->scopeConfig->getValue($config['path'], ScopeInterface::SCOPE_WEBSITES, $websiteId);
            if ($config['value'] === $websiteValue) {
                $idsToDelete[] = $config['config_id'];
                $messages[] = sprintf('Found redundant entry for path: %s [ID: %d]', $config['path'], $config['config_id']);
                $this->logger->info(sprintf("Marked for deletion (ID: %d) | Value: '%s'", $config['config_id'], $config['value']));
            }
        }

        $deletedCount = count($idsToDelete);
        $this->logger->info(sprintf('Total entries marked for deletion: %d', $deletedCount));

        if ($deletedCount > 0 && !$isDryRun) {
            try {
                $this->connection->delete($tableName, ['config_id IN (?)' => $idsToDelete]);
                $messages[] = sprintf('Successfully deleted %d redundant configuration entries.', $deletedCount);
                $this->logger->info('Successfully deleted entries from database.');
                $this->cacheTypeList->invalidate(Config::TYPE_IDENTIFIER);
                $messages[] = 'Configuration cache has been flushed.';
            } catch (Exception $e) {
                $messages[] = 'An error occurred while deleting entries: ' . $e->getMessage();
                $this->logger->error('An error occurred: ' . $e->getMessage());
            }
        } elseif ($deletedCount > 0 && $isDryRun) {
            $messages[] = sprintf('Dry run finished. Found %d entries to delete.', $deletedCount);
        } else {
            $messages[] = 'No redundant configuration entries found.';
        }

        $this->logger->info('--- Finished ---');

        return ['deleted_count' => $deletedCount, 'messages' => $messages];
    }

    /**
     * Get config data for a specific scope.
     *
     * @param string $scope
     * @return array
     */
    private function getConfigData(string $scope): array
    {
        $select = $this->connection->select()->from(
            $this->connection->getTableName('core_config_data')
        )->where('scope = ?', $scope);

        return $this->connection->fetchAll($select);
    }

    /**
     * Build map of store_id to website_id.
     *
     * @return void
     */
    private function buildStoreWebsiteMap(): void
    {
        if ($this->storeWebsiteMap === null) {
            $select = $this->connection->select()->from(
                $this->connection->getTableName('store'),
                ['store_id', 'website_id']
            );
            $this->storeWebsiteMap = $this->connection->fetchPairs($select);
        }
    }

    /**
     * Get website ID for a given store ID.
     *
     * @param int $storeId
     * @return int|null
     */
    private function getWebsiteIdForStore(int $storeId): ?int
    {
        return $this->storeWebsiteMap[$storeId] ?? null;
    }
}
