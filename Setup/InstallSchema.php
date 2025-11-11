<?php

namespace GardenLawn\Core\Setup;

use Aws\S3\S3Client;
use Exception;
use GardenLawn\Core\Utils\Utils;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Validation\ValidationException;
use Magento\Indexer\Model\Indexer\State;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\Data\SourceInterfaceFactory;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Magento\InventorySales\Model\GetAssignedStockIdForWebsiteInterface;
use Magento\Store\Model\GroupFactory;
use Magento\Store\Model\ResourceModel\Group;
use Magento\Store\Model\ResourceModel\Store;
use Magento\Store\Model\ResourceModel\Website;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\WebsiteFactory;
use Magento\UrlRewrite\Model\UrlRewriteFactory;
use Navigate\AllowSvgWebpAvifImage\Model\Image\Adapter\Gd2Rewrite;
use Psr\Log\LoggerInterface;
use Zend_Db_Exception;

/**
 * Class InstallSchema
 * @package PandaGroup\MyAdminController\Setup
 */
class InstallSchema implements InstallSchemaInterface
{
    protected S3Client $s3client;
    protected AdapterInterface $connection;
    protected LoggerInterface $logger;
    protected ProductFactory $productFactory;
    private Config $config;
    private State $state;
    private WebsiteFactory $websiteFactory;
    private Website $websiteResourceModel;
    private UrlRewriteFactory $urlRewriteFactory;
    private Group $groupResourceModel;
    private Store $storeResourceModel;
    private GroupFactory $groupFactory;
    private ManagerInterface $eventManager;
    private SourceInterfaceFactory $sourceInterfaceFactory;
    private SourceRepositoryInterface $sourceRepositoryInterface;
    private DataObjectHelper $dataObjectHelper;
    private EavSetupFactory $eavSetupFactory;
    private Attribute $attributeFactory;
    private AttributeSetFactory $attributeSetFactory;
    private ResourceConnection $resourceConnection;
    private \Magento\Store\Model\Store $store;
    private \Magento\Store\Model\Group $group;

    protected StoreManagerInterface $storeManager;

    protected CollectionFactory $categoryCollectionFactory;

    protected CategoryRepository $categoryRepository;

    public function __construct(
        Config                     $config,
        State                      $state,
        WebsiteFactory             $websiteFactory,
        Website                    $websiteResourceModel,
        UrlRewriteFactory          $urlRewriteFactory,
        Group                      $groupResourceModel,
        GroupFactory               $groupFactory,
        ManagerInterface           $eventManager,
        Store                      $storeResourceModel,
        SourceInterfaceFactory     $sourceInterfaceFactory,
        SourceRepositoryInterface  $sourceRepositoryInterface,
        DataObjectHelper           $dataObjectHelper,
        LoggerInterface            $logger,
        ProductFactory             $productFactory,
        EavSetupFactory            $eavSetupFactory,
        Attribute                  $attributeFactory,
        AttributeSetFactory        $attributeSetFactory,
        ResourceConnection         $resourceConnection,
        \Magento\Store\Model\Store $store,
        \Magento\Store\Model\Group $group,
        StoreManagerInterface      $storeManager,
        CollectionFactory          $categoryCollectionFactory,
        CategoryRepository         $categoryRepository
    )
    {
        $this->config = $config;
        $this->state = $state;
        $this->websiteFactory = $websiteFactory;
        $this->websiteResourceModel = $websiteResourceModel;
        $this->urlRewriteFactory = $urlRewriteFactory;
        $this->eventManager = $eventManager;
        $this->groupFactory = $groupFactory;
        $this->groupResourceModel = $groupResourceModel;
        $this->storeResourceModel = $storeResourceModel;
        $this->sourceInterfaceFactory = $sourceInterfaceFactory;
        $this->sourceRepositoryInterface = $sourceRepositoryInterface;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->logger = $logger;
        $this->productFactory = $productFactory;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->attributeFactory = $attributeFactory;
        $this->storeManager = $storeManager;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->categoryRepository = $categoryRepository;
        $this->s3client = Utils::getS3Client();
        $this->resourceConnection = $resourceConnection;
        $this->connection = $this->resourceConnection->getConnection();
        $this->attributeSetFactory = $attributeSetFactory;
        $this->store = $store;
        $this->group = $group;
    }

    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @throws Zend_Db_Exception|AlreadyExistsException
     * @throws Exception
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        try {
            $this->state->setAreaCode(Area::AREA_GLOBAL);
        } catch (LocalizedException) {
        }

        $installer = $setup;
        $installer->startSetup();

        if (version_compare($context->getVersion(), '1.0.0', '<=')) {
            echo "Start install schema v1.0.0\n";
            //$this->install_v_1_0_0($setup);
            echo "End install schema v1.0.0\n";
        }

        $installer->endSetup();
    }

    /**
     * @throws ValidationException
     * @throws CouldNotSaveException
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws Exception
     */
    private function install_v_1_0_0($setup): void
    {
        $this->config->saveConfig('catalog/seo/product_url_suffix', '');
        $this->config->saveConfig('catalog/seo/category_url_suffix', '');

        $attribute = [
            'website_code' => 'gardenlawn_website',
            'website_name' => 'GardenLawn Website',
            'group_code' => 'gardenlawn',
            'group_name' => 'Usługi ogrodnicze',
            'store_code' => 'gardenlawn',
            'store_name' => 'Usługi ogrodnicze',
            'is_active' => '1'
        ];

        $rootPages = [
            ['code' => 'grassinfo1', 'pl' => 'grassinfo1'],
            ['code' => 'grassinfo2', 'pl' => 'grassinfo2'],
            ['code' => 'grassinfo3', 'pl' => 'grassinfo3'],
            ['code' => 'grassinfo4', 'pl' => 'grassinfo4'],
            ['code' => 'grassinfo5', 'pl' => 'grassinfo5'],
            ['code' => 'grassinfo6', 'pl' => 'grassinfo6'],
            ['code' => 'grassinfo7', 'pl' => 'grassinfo7'],
            ['code' => 'privacypolicy', 'pl' => 'polityka-prywatnosci'],
            ['code' => 'privacypolicypopup', 'pl' => 'polityka-prywatnosci-popup'],
        ];

        $store = $this->store->getCollection()
            ->addFilter('code', $attribute['store_code'])
            ->getFirstItem();

        $website = $this->websiteFactory->create();
        $website->load($attribute['website_code']);

        if (!$website->getId()) {
            $website = $this->websiteFactory->create();
            $website->load($attribute['website_code']);
            $website = $this->setWebId($website, $attribute);

            $group = $this->group->getCollection()
                ->addFilter('code', $attribute['group_code'])
                ->getFirstItem();

            $this->createCategory('GardenLawn Category', 1, '1/3');
            $this->createCategory('Automow', 1, '1/4');
            $this->createCategory('Finn', 1, '1/5');
            $group->setWebsiteId($website->getWebsiteId());

            $attributeInfo = $this->attributeFactory->getCollection()
                ->addFieldToFilter('attribute_code', ['eq' => "manufacturer"])
                ->getFirstItem();
            $attributeId = $attributeInfo->getAttributeId();

            $attribute_arr = ['Trawnik Producent'];
            $option = array();
            $option['attribute_id'] = $attributeInfo->getAttributeId();
            foreach ($attribute_arr as $key => $value) {
                $option['value'][$value][0] = $value;
            }
            $eavSetup = $this->eavSetupFactory->create();
            $eavSetup->addAttributeOption($option);

            $this->createAttributeSetId('my-equipment');
            $this->createAttributeSetId('grass-in-roll', true);

            $group->setName($attribute['group_name']);
            $group->setCode($attribute['group_code']);
            $group->setRootCategoryId(3);
            $this->groupResourceModel->save($group);

            $group = $this->groupFactory->create();
            $group->load($attribute['group_code'], 'code');
            $store->setCode($attribute['store_code']);
            $store->setName($attribute['store_name']);
            $store->setWebsite($website);
            $store->setGroupId($group->getId());
            $store->setData('is_active', $attribute['is_active']);
            $this->storeResourceModel->save($store);
            $this->eventManager->dispatch('store_add', ['store' => $store]);

            $this->createSource();

            $store = $this->store->getCollection()
                ->addFilter('code', $attribute['store_code'])
                ->getFirstItem();

            foreach ($rootPages as $key => $name) {
                $requestUrl = $name['pl'];
                $targetUrl = 'hyvaroot/pages/' . $name['code'];
                $this->rewriteUrl($store->getId(), $requestUrl, $targetUrl);
            }

            $this->rewriteUrl($store->getId(), 'konto', 'customer/account');
            $this->rewriteUrl($store->getId(), 'zamowienia-i-zwroty', 'sales/guest/form');
            $this->rewriteUrl($store->getId(), 'slowa-wyszukiwania', 'search/term/popular');
            $this->rewriteUrl($store->getId(), 'kontakt', 'contact');
            $this->rewriteUrl($store->getId(), 'regulamin', 'terms');
            $this->rewriteUrl($store->getId(), 'mapa-strony', 'sitemap.html');

            $sql = "insert into magento.customer_group (customer_group_code, tax_class_id) values ('Ogrodnik', 3);";
            $this->connection->query($sql);

            $sql = "INSERT INTO tax_calculation_rate (tax_country_id, tax_region_id, tax_postcode, code, rate, zip_is_range, zip_from, zip_to) VALUES ('PL', 0, '*', 'pl_PL', 8.0000, null, null, null);";
            $this->connection->query($sql);
            $sql = "INSERT INTO tax_calculation_rule (code, priority, position, calculate_subtotal) VALUES ('VAT 8%', 0, 0, 0);";
            $this->connection->query($sql);
            //$sql = "";
            //$this->connection->query($sql);

            $sql = "update amasty_gdprcookie_group set name = 'Niezbędne', description = 'Umożliwiające prawidłowe funkcjonowanie strony Sklepu Internetowego' where id = 1;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_group set name = 'Funkcjonalne/preferencyjne', description = 'Umożliwiające dostosowanie strony Sklepu Internetowego do preferencji osoby odwiedzającej stronę' where id = 2;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_group set name = 'Analityczne i wydajnościowe', description = 'Gromadzące informacje o sposobie korzystania ze strony Sklepu Internetowego' where id = 3;";
            $this->connection->query($sql);
            $sql = "insert into amasty_gdprcookie_group (name, description, is_essential, is_enabled, sort_order) values ('Marketingowe, reklamowe i społecznościowe', 'Zbierające informacje o osobie odwiedzającej stronę Sklepu Internetowego w celu wyświetlania tej osobie reklam, ich personalizacji, mierzeniu skuteczności i prowadzenia innych działań marketingowych w tym również na stronach internetowych odrębnych od strony Sklepu Internetowego, takich jak portale społecznościowe albo inne strony należące do tych samych sieci reklamowych co Sklep Internetowy', 0, 1, 0);";
            $this->connection->query($sql);

            $sql = "update amasty_gdprcookie_cookie set description = 'Aby zapisać nazwę użytkownika zalogowanego użytkownika i 128-bitowy zaszyfrowany klucz. Informacje te są wymagane, aby umożliwić użytkownikowi pozostanie zalogowanym w witrynie internetowej bez konieczności podawania nazwy użytkownika i hasła dla każdej odwiedzanej strony. Bez tego pliku cookie użytkownik nie może przejść do obszarów witryny internetowej, które wymagają uwierzytelnionego dostępu.' where id = 1;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Dodaje losowy, unikalny numer i czas do stron z treścią klienta, aby zapobiec ich buforowaniu na serwerze.' where id = 2;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Przechowuje klucz (ID) trwałego koszyka, aby umożliwić przywrócenie koszyka anonimowemu kupującemu.' where id = 3;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Środek bezpieczeństwa, który dodaje losowy ciąg do wszystkich przesłanych formularzy, aby chronić dane przed fałszerstwem żądań między witrynami (CSRF).' where id = 4;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Śledzi konkretny widok sklepu / ustawienia regionalne wybrane przez kupującego.' where id = 5;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Zachowuje stronę docelową, do której przechodził klient przed przekierowaniem do logowania.' where id = 6;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Śledzi komunikaty o błędach i inne powiadomienia wyświetlane użytkownikowi, takie jak komunikat o zgodzie na pliki cookie i różne komunikaty o błędach. Komunikat jest usuwany z pliku cookie po wyświetleniu kupującemu.' where id = 7;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Lokalne przechowywanie treści specyficznych dla odwiedzającego, które umożliwia funkcje handlu elektronicznego.' where id = 8;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Wymusza lokalne przechowywanie określonych sekcji treści, które powinny zostać unieważnione.' where id = 9;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Wartość tego pliku cookie uruchamia czyszczenie lokalnej pamięci podręcznej.' where id = 10;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Przechowuje konfigurację danych produktu związanych z ostatnio oglądanymi/porównywanymi produktami.' where id = 11;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Wskazuje, czy kupujący zezwala na zapisywanie plików cookie.' where id = 12;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Przechowuje przetłumaczoną treść na żądanie kupującego.' where id = 13;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Przechowuje wersję pliku przetłumaczonej treści.' where id = 14;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Przechowuje informacje specyficzne dla klienta związane z działaniami zainicjowanymi przez kupującego, takimi jak wyświetlanie listy życzeń, informacji o kasie itp.' where id = 15;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Przechowuje identyfikatory produktów ostatnio oglądanych produktów w celu łatwej nawigacji.' where id = 16;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Przechowuje identyfikatory produktów ostatnio oglądanych produktów w celu łatwej nawigacji.' where id = 17;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Przechowuje identyfikatory produktów ostatnio porównywanych produktów.' where id = 18;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Przechowuje identyfikatory produktów wcześniej porównywanych produktów, aby ułatwić nawigację.' where id = 19;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Służy do rozróżniania użytkowników.' where id = 20;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Służy do rozróżniania użytkowników.' where id = 21;";
            $this->connection->query($sql);
            $sql = "update amasty_gdprcookie_cookie set description = 'Służy do ograniczania szybkości żądań.' where id = 22;";
            $this->connection->query($sql);

            $this->createCategory('Sprzęt', 3, '1/3/6');
            $this->createCategory('Sprzedaż trawy w rolce', 3, '1/3/7');

            $attribute = [
                'group_code' => 'automow',
                'group_name' => 'Roboty koszące i akcesoria',
                'store_code' => 'automow',
                'store_name' => 'Automow',
                'is_active' => '1'
            ];

            $group = $this->group->getCollection()
                ->addFilter('code', $attribute['group_code'])
                ->getFirstItem();

            $group->setWebsiteId($website->getWebsiteId());

            $group->setName($attribute['group_name']);
            $group->setCode($attribute['group_code']);
            $group->setRootCategoryId(4);
            $this->groupResourceModel->save($group);

            $group = $this->groupFactory->create();
            $group->load($attribute['group_code'], 'code');
            $store->setCode($attribute['store_code']);
            $store->setName($attribute['store_name']);
            $store->setWebsite($website);
            $store->setGroupId($group->getId());
            $store->setData('is_active', $attribute['is_active']);
            $this->storeResourceModel->save($store);
            $this->eventManager->dispatch('store_add', ['store' => $store]);

            $attribute = [
                'group_code' => 'finn',
                'group_name' => 'Hydrosiewniki',
                'store_code' => 'finn',
                'store_name' => 'Finn',
                'is_active' => '1'
            ];

            $group = $this->group->getCollection()
                ->addFilter('code', $attribute['group_code'])
                ->getFirstItem();

            $group->setWebsiteId($website->getWebsiteId());

            $group->setName($attribute['group_name']);
            $group->setCode($attribute['group_code']);
            $group->setRootCategoryId(5);
            $this->groupResourceModel->save($group);

            $group = $this->groupFactory->create();
            $group->load($attribute['group_code'], 'code');
            $store->setCode($attribute['store_code']);
            $store->setName($attribute['store_name']);
            $store->setWebsite($website);
            $store->setGroupId($group->getId());
            $store->setData('is_active', $attribute['is_active']);
            $this->storeResourceModel->save($store);
            $this->eventManager->dispatch('store_add', ['store' => $store]);
        }
    }

    /**
     * @throws LocalizedException
     * @throws Exception
     */
    private function createAttributeSetId(string $attributeSetName, bool $setManufacturer = false): void
    {
        $attributeSet = $this->attributeSetFactory->create();

        $data = [
            'attribute_set_name' => $attributeSetName,
            'entity_type_id' => 4,
            'sort_order' => 0,
        ];
        $attributeSet->setData($data);
        $attributeSet->validate();
        $a = $attributeSet->save()->initFromSkeleton(4)->save();
        if ($setManufacturer) {
            $a->setCustomAttribute('manufacturer', 4)->save();
        }
    }

    /**
     * @throws AlreadyExistsException
     */
    private function setWebId($website, $attribute)
    {
        if (!$website->getId()) {
            $website->setCode($attribute['website_code']);
            $website->setName($attribute['website_name']);
            $website->setIsDefault(1);
            $this->websiteResourceModel->save($website);
        }
        return $website;
    }

    /**
     * @throws LocalizedException
     */
    public function createCategory(string $categoryName, $parentId, $path): void
    {
        $category = $this->categoryCollectionFactory->create()
            ->addAttributeToFilter('name', $categoryName)
            ->getFirstItem();

        $c = $category
            ->setPath($path)
            ->setParentId($parentId)
            ->setName($categoryName)
            ->setIsActive(true);

        $this->categoryRepository->save($c);
    }

    /**
     * @throws ValidationException
     * @throws CouldNotSaveException
     */
    private function createSource(): void
    {
        $sourceData = [
            SourceInterface::SOURCE_CODE => 'gardenlawn_source',
            SourceInterface::NAME => 'GardenLawn Source',
            SourceInterface::ENABLED => 1,
            SourceInterface::DESCRIPTION => 'GardenLawn Source',
            SourceInterface::LATITUDE => 0,
            SourceInterface::LONGITUDE => 0,
            SourceInterface::COUNTRY_ID => 'PL',
            SourceInterface::POSTCODE => '46-081',
        ];

        $source = $this->sourceInterfaceFactory->create();
        $this->dataObjectHelper->populateWithArray($source, $sourceData, SourceInterface::class);
        $this->sourceRepositoryInterface->save($source);
    }

    /**
     * @throws Exception
     */
    private function rewriteUrl($storeId, $requestUrl, $targetUrl): void
    {
        try {
            $urlRewriteModel = $this->urlRewriteFactory->create();
            $urlRewriteModel->setStoreId($storeId);
            $urlRewriteModel->setTargetPath($targetUrl);
            $urlRewriteModel->setRequestPath($requestUrl);
            $urlRewriteModel->setEntityType('custom');
            $urlRewriteModel->setRedirectType(0);
            $urlRewriteModel->save();
            echo "Rewrite url " . $targetUrl . " to " . $requestUrl . "\n";
        } catch (Exception) {
            echo "Exists rewrite url to " . $requestUrl . "\n";
        }
    }
}
