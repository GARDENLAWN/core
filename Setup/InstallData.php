<?php

namespace GardenLawn\Core\Setup;

use Exception;
use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Eav\Model\Config;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Validator\ValidateException;
use Magento\Variable\Model\VariableFactory;
use Psr\Log\LoggerInterface;

class InstallData implements InstallDataInterface
{
    protected LoggerInterface $logger;
    protected AdapterInterface $connection;
    protected VariableFactory $varFactory;
    private EavSetupFactory $eavSetupFactory;
    private Config $eavConfig;

    public function __construct(
        LoggerInterface $logger,
        EavSetupFactory $eavSetupFactory,
        Config          $eavConfig,
        VariableFactory $varFactory
    )
    {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig = $eavConfig;
        $this->varFactory = $varFactory;
        $this->logger = $logger;
        $objectManager = ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $this->connection = $resource->getConnection();
    }

    /**
     * @throws LocalizedException
     * @throws ValidateException
     * @throws Exception
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context): void
    {
        $installer = $setup;
        $installer->startSetup();

        if (version_compare($context->getVersion(), '1.0.0', '<=')) {
            echo "Start install data v1.0.0\n";
            try {
                $this->connection->beginTransaction();
                //$this->install_v_1_0_0($setup);
                $this->connection->commit();
                echo "End install data v1.0.0\n";
            } catch (Exception $e) {
                echo "End install data v1.0.0 with error!\n";
                $this->connection->rollBack();
                $this->logger->error($e);
            }
        }

        $installer->endSetup();
    }

    /**
     * @throws ValidateException
     * @throws LocalizedException
     */
    private function install_v_1_0_0($setup): void
    {
        $variable = $this->varFactory->create();
        $data = [
            'code' => 'customer_groups_ids_b2b',
            'name' => 'customer_groups_ids_b2b',
            'html_value' => '[4];',
            'plain_value' => '[4];',

        ];
        $variable->setData($data);
        $variable->save();

        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $eavSetup->addAttribute(AddressMetadataInterface::ENTITY_TYPE_ADDRESS, 'addresstype', [
            'type' => 'varchar',
            'input' => 'text',
            'label' => 'Custom Address Attribute ("billing" or "shipping")',
            'visible' => true,
            'required' => true,
            'user_defined' => true,
            'system' => false,
            'group' => 'General',
            'global' => true,
            'visible_on_front' => true,
            'default' => 'shipping'
        ]);

        $customAttribute = $this->eavConfig->getAttribute(AddressMetadataInterface::ENTITY_TYPE_ADDRESS, 'addresstype');
        $customAttribute->setData(
            'used_in_forms',
            ['adminhtml_customer_address', 'customer_address_edit', 'customer_register_address']
        );
        $customAttribute->save();

        $contentTerms = addslashes(file_get_contents(__dir__ . '/../files/terms.html'));
        try {
            $sql = "insert into cms_page(title,page_layout,identifier,content_heading,content) values('Regulamin','1column','terms','Regulamin','$contentTerms');";
            $this->connection->query($sql);
            $sql = "insert into amasty_gdpr_consents(name,consent_code) values('Regulamin','terms_checkbox');";
            $this->connection->query($sql);
            $sql = "insert into amasty_gdpr_consents_scope (store_id, consent_entity_id, is_enabled, is_required, log_the_consent, hide_the_consent_after_user_left_the_consent, consent_location, link_type, cms_page_id, consent_text, countries, visibility, sort_order) VALUES (0, 2, 1, 1, 1, 0, 'registration,checkout,contactus,subscription', 1, 5, 'Przeczytałem/am i akceptuję <a href=\"#\" class=\"link-underline\">regulamin</a>', null, 0, 0);";
            $this->connection->query($sql);
            $sql = "INSERT INTO tax_calculation (tax_calculation_id, tax_calculation_rate_id, tax_calculation_rule_id, customer_tax_class_id, product_tax_class_id) VALUES (1, 3, 1, 3, 2);";
            $this->connection->query($sql);
        } catch (Exception) {
        }
    }
}
