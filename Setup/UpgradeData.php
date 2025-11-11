<?php

namespace GardenLawn\Core\Setup;

use Exception;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;

class UpgradeData implements UpgradeDataInterface
{
    protected AdapterInterface $connection;

    public function __construct()
    {
        $objectManager = ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $this->connection = $resource->getConnection();
    }

    /**
     * @throws Exception
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context): void
    {
        $installer = $setup;
        $installer->startSetup();

        if (version_compare($context->getVersion(), '1.0.0', '<=')) {
            echo "Start upgrade data v1.0.0\n";
            $this->upgrade_v_1_0_0($setup);
            echo "End upgrade data v1.0.0\n";
        }

        $installer->endSetup();
    }

    private function upgrade_v_1_0_0($setup): void
    {

    }
}
