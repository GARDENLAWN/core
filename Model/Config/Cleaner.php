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
use Zend_Log;
use Zend_Log_Exception;
use Zend_Log_Writer_Stream;
use Exception;

class Cleaner
{
    private const string DEBUG_LOG_FILE = 'gardenlawn_core_debug.log';

    /** @var ResourceConnection */
    private ResourceConnection $resourceConnection;

    /** @var AdapterInterface|null */
    private ?AdapterInterface $connection = null; // Ustawione na null, aby było inicjowane w execute()

    /** @var ScopeConfigInterface */
    private ScopeConfigInterface $scopeConfig;

    /** @var TypeListInterface */
    private TypeListInterface $cacheTypeList;

    public function __construct(
        ResourceConnection $resourceConnection,
        ScopeConfigInterface $scopeConfig,
        TypeListInterface $cacheTypeList
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->scopeConfig = $scopeConfig;
        $this->cacheTypeList = $cacheTypeList;
    }

    /**
     * @throws Zend_Log_Exception
     */
    public function cleanRedundantConfig(bool $isDryRun = false): array
    {
        // Inicjalizacja loggera przed rozpoczęciem pracy
        $writer = new Zend_Log_Writer_Stream(BP . '/var/log/' . self::DEBUG_LOG_FILE);
        $logger = new Zend_Log();
        $logger->addWriter($writer);

        $logger->info('--- Starting Redundant Config Cleanup ---');

        $this->connection = $this->resourceConnection->getConnection();
        $tableName = $this->connection->getTableName('core_config_data');
        $idsToDelete = [];
        $messages = [];

        if ($isDryRun) {
            $messages[] = 'Dry run mode enabled. No data will be deleted.';
            $logger->info('Dry run mode enabled.');
        }

        // Process default-level configs (usuwanie wartości, które są puste/null w bazie)
        $messages[] = 'Processing default-level configurations (cleaning empty values)...';
        $defaultConfigs = $this->getConfigData(ScopeConfigInterface::SCOPE_TYPE_DEFAULT);

        foreach ($defaultConfigs as $config) {
            // UWAGA: Czyste wpisy w zakresie 'default' są rzadko usuwane,
            // ponieważ stanowią podstawę konfiguracji.
            // Usuwamy tylko te, które mają pustą wartość, zakładając, że
            // pusta wartość oznacza brak intencji ustawienia.
            if (empty($config['value'])) {
                $idsToDelete[] = $config['config_id'];
                $messages[] = sprintf('Found redundant (empty value) default entry for path: %s [ID: %d]', $config['path'], $config['config_id']);
                $logger->info(sprintf("Marked for deletion (ID: %d) as empty default value | Value: '%s'", $config['config_id'], $config['value']));
            }
        }

        // Process website-level configs (redundancja względem zakresu default/XML)
        $messages[] = 'Processing website-level configurations...';
        $websiteConfigs = $this->getConfigData(ScopeInterface::SCOPE_WEBSITES);

        foreach ($websiteConfigs as $config) {
            // Pobieramy wartość domyślną (scope default)
            $defaultValue = $this->scopeConfig->getValue($config['path']);

            // PORÓWNANIE: Czy wartość z bazy jest identyczna z wartością domyślną?
            // Używamy rzutowania do stringa, aby obsłużyć przypadki null/''
            if ((string)$config['value'] === (string)$defaultValue) {
                $idsToDelete[] = $config['config_id'];
                $messages[] = sprintf('Found redundant entry for path: %s [ID: %d]', $config['path'], $config['config_id']);
                $logger->info(sprintf("Marked for deletion (ID: %d) | Value: '%s'", $config['config_id'], $config['value']));
            }
        }

        // Process store-level configs (redundancja względem zakresu website)
        $messages[] = 'Processing store-level configurations...';
        $storeConfigs = $this->getConfigData(ScopeInterface::SCOPE_STORES);

        foreach ($storeConfigs as $config) {
            $websiteId = $this->getWebsiteIdForStore((string)$config['scope_id']);

            if ($websiteId === null) {
                $logger->warn(sprintf("Skipping store config (ID: %d) because website ID not found.", $config['config_id']));
                continue;
            }

            // Pobieramy wartość z nadrzędnego zakresu (website)
            $websiteValue = $this->scopeConfig->getValue($config['path'], ScopeInterface::SCOPE_WEBSITES, $websiteId);

            // PORÓWNANIE: Czy wartość z bazy jest identyczna z wartością nadrzędną (website)?
            // Używamy rzutowania do stringa, aby obsłużyć przypadki null/''
            if ((string)$config['value'] === (string)$websiteValue) {
                $idsToDelete[] = $config['config_id'];
                $messages[] = sprintf('Found redundant entry for path: %s [ID: %d]', $config['path'], $config['config_id']);
                $logger->info(sprintf("Marked for deletion (ID: %d) | Value: '%s'", $config['config_id'], $config['value']));
            }
        }

        $deletedCount = count($idsToDelete);
        $logger->info(sprintf('Total entries marked for deletion: %d', $deletedCount));

        if ($deletedCount > 0 && !$isDryRun) {
            try {
                $this->connection->delete($tableName, ['config_id IN (?)' => $idsToDelete]);
                $messages[] = sprintf('Successfully deleted %d redundant configuration entries.', $deletedCount);
                $logger->info('Successfully deleted entries from database.');
                $this->cacheTypeList->invalidate(Config::TYPE_IDENTIFIER);
                $messages[] = 'Configuration cache has been flushed.';
            } catch (Exception $e) {
                $messages[] = 'An error occurred while deleting entries: ' . $e->getMessage();
                $logger->err('An error occurred: ' . $e->getMessage());
            }
        } elseif ($deletedCount > 0 && $isDryRun) {
            $messages[] = sprintf('Dry run finished. Found %d entries to delete.', $deletedCount);
        } else {
            $messages[] = 'No redundant configuration entries found.';
        }

        $logger->info('--- Finished ---');

        return ['deleted_count' => $deletedCount, 'messages' => $messages];
    }

    private function getConfigData(string $scope): array
    {
        $select = $this->connection->select()->from(
            $this->connection->getTableName('core_config_data')
        )->where('scope = ?', $scope);

        return $this->connection->fetchAll($select);
    }

    private function getWebsiteIdForStore(string $storeId): ?int
    {
        $select = $this->connection->select()->from(
            $this->connection->getTableName('store'),
            ['website_id']
        )->where('store_id = ?', $storeId);

        $websiteId = $this->connection->fetchOne($select);
        return $websiteId ? (int)$websiteId : null;
    }
}
