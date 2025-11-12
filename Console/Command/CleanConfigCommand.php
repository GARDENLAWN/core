<?php
/**
 * Copyright © GardenLawn. All rights reserved.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Console\Command;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Cache\TypeListInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use GardenLawn\Core\Model\Config\Cleaner;
use Zend_Log_Exception;

class CleanConfigCommand extends Command
{
    private const string DRY_RUN_OPTION = 'dry-run';

    /**
     * @var Cleaner
     */
    private Cleaner $cleaner;

    /**
     * @param ResourceConnection $resourceConnection
     * @param ScopeConfigInterface $scopeConfig
     * @param TypeListInterface $cacheTypeList
     * @param LoggerInterface $logger
     * @param Cleaner $cleaner
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
        $this->cleaner = $cleaner;
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
     * @throws Zend_Log_Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDryRun = $input->getOption(self::DRY_RUN_OPTION);

        $result = $this->cleaner->cleanRedundantConfig($isDryRun);

        foreach ($result['messages'] as $message) {
            $output->writeln(sprintf('<info>%s</info>', $message));
        }

        return 0;
    }
}
