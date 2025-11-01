<?php
/**
 * Copyright © GardenLawn. All rights reserved.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Console\Command;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Config\App\Config\Type\System as SystemConfig;
use Magento\Store\Model\ScopeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanConfigCommand extends Command
{
    private const string DRY_RUN_OPTION = 'dry-run';

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var AdapterInterface
     */
    private AdapterInterface $connection;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var TypeListInterface
     */
    private TypeListInterface $cacheTypeList;

    /**
     * @param ResourceConnection $resourceConnection
     * @param ScopeConfigInterface $scopeConfig
     * @param TypeListInterface $cacheTypeList
     * @param string|null $name
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        ScopeConfigInterface $scopeConfig,
        TypeListInterface $cacheTypeList,
        string $name = null
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->scopeConfig = $scopeConfig;
        $this->cacheTypeList = $cacheTypeList;
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('dev:config:clean-redundant');
        $this->setDescription('Removes redundant configuration values from core_config_data.');
        $this->addOption(
            self::DRY_RUN_OPTION,
            null,
            InputOption::VALUE_NONE,
            'Perform a dry run without deleting any data.'
        );
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDryRun = $input->getOption(self::DRY_RUN_OPTION);
        $this->connection = $this->resourceConnection->getConnection();
        $tableName = $this->connection->getTableName('core_config_data');
        $deletedCount = 0;
        $idsToDelete = [];

        if ($isDryRun) {
            $output->writeln('<info>Dry run mode enabled. No data will be deleted.</info>');
        }

        // --- Process website-level configs ---
        $output->writeln('Processing website-level configurations...');
        $websiteConfigs = $this->getConfigData(ScopeInterface::SCOPE_WEBSITES);

        foreach ($websiteConfigs as $config) {
            $defaultValue = $this->scopeConfig->getValue($config['path']); // Default scope
            if ((string)$config['value'] === (string)$defaultValue) {
                $idsToDelete[] = $config['config_id'];
                $output->writeln(
                    sprintf(
                        '  - Found redundant entry for path: <comment>%s</comment> [ID: %d, Scope: website, Scope ID: %d]',
                        $config['path'],
                        $config['config_id'],
                        $config['scope_id']
                    )
                );
            }
        }

        // --- Process store-level configs ---
        $output->writeln('Processing store-level configurations...');
        $storeConfigs = $this->getConfigData(ScopeInterface::SCOPE_STORES);

        foreach ($storeConfigs as $config) {
            $websiteValue = $this->scopeConfig->getValue(
                $config['path'],
                ScopeInterface::SCOPE_WEBSITES,
                $this->getWebsiteIdForStore($config['scope_id'])
            );

            if ((string)$config['value'] === (string)$websiteValue) {
                $idsToDelete[] = $config['config_id'];
                $output->writeln(
                    sprintf(
                        '  - Found redundant entry for path: <comment>%s</comment> [ID: %d, Scope: store, Scope ID: %d]',
                        $config['path'],
                        $config['config_id'],
                        $config['scope_id']
                    )
                );
            }
        }

        $deletedCount = count($idsToDelete);

        if ($deletedCount > 0 && !$isDryRun) {
            try {
                $this->connection->delete($tableName, ['config_id IN (?)' => $idsToDelete]);
                $output->writeln(sprintf('<info>Successfully deleted %d redundant configuration entries.</info>', $deletedCount));
                $this->cacheTypeList->invalidate(SystemConfig::CACHE_TYPE);
                $output->writeln('<info>Configuration cache has been flushed.</info>');
            } catch (\Exception $e) {
                $output->writeln('<error>An error occurred while deleting entries:</error>');
                $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
                return 1;
            }
        } elseif ($deletedCount > 0 && $isDryRun) {
            $output->writeln(sprintf('<info>Dry run finished. Found %d entries to delete.</info>', $deletedCount));
        } else {
            $output->writeln('<info>No redundant configuration entries found.</info>');
        }

        return 0;
    }

    /**
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
     * Get website ID for a given store ID.
     * Note: This is a simplified helper. A more robust solution might inject StoreManager.
     *
     * @param int|string $storeId
     * @return int|null
     */
    private function getWebsiteIdForStore(int|string $storeId): ?int
    {
        $select = $this->connection->select()->from(
            $this->connection->getTableName('store'),
            ['website_id']
        )->where('store_id = ?', $storeId);

        $websiteId = $this->connection->fetchOne($select);
        return $websiteId ? (int)$websiteId : null;
    }
}
