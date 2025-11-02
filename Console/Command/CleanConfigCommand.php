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
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use GardenLawn\Core\Model\Config\Cleaner;

class CleanConfigCommand extends Command
{
    private const DRY_RUN_OPTION = 'dry-run';
    private const DEBUG_LOG_FILE = 'gardenlawn_core_debug.log'; // <-- Nazwa naszego pliku logu

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var AdapterInterface
     */
    private $connection;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Cleaner
     */
    private $cleaner;

    /**
     * @param ResourceConnection $resourceConnection
     * @param ScopeConfigInterface $scopeConfig
     * @param TypeListInterface $cacheTypeList
     * @param LoggerInterface $logger
     * @param string|null $name
     */
    public function __construct(
        ResourceConnection   $resourceConnection,
        ScopeConfigInterface $scopeConfig,
        TypeListInterface    $cacheTypeList,
        LoggerInterface      $logger,
        Cleaner              $cleaner,
        string               $name = null
    )
    {
        $this->resourceConnection = $resourceConnection;
        $this->scopeConfig = $scopeConfig;
        $this->cacheTypeList = $cacheTypeList;
        $this->logger = $logger;
        $this->cleaner = $cleaner;
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure()
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDryRun = $input->getOption(self::DRY_RUN_OPTION);

        $result = $this->cleaner->cleanRedundantConfig($isDryRun);

        foreach ($result['messages'] as $message) {
            $output->writeln(sprintf('<info>%s</info>', $message));
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
     * @param int|string $storeId
     * @return int|null
     */
    private function getWebsiteIdForStore($storeId): ?int
    {
        $select = $this->connection->select()->from(
            $this->connection->getTableName('store'),
            ['website_id']
        )->where('store_id = ?', $storeId);

        $websiteId = $this->connection->fetchOne($select);
        return $websiteId ? (int)$websiteId : null;
    }
}
