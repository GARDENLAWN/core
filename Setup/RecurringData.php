<?php

namespace GardenLawn\Core\Setup;

use Aws\S3\S3Client;
use Exception;
use GardenLawn\Core\Utils\Logger;
use GardenLawn\Core\Utils\Utils;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Validation\ValidationException;
use Magento\Indexer\Model\Indexer\State;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventorySales\Model\GetAssignedStockIdForWebsiteInterface;
use Magento\InventorySourceDeductionApi\Model\GetSourceItemBySourceCodeAndSku;
use Magento\Store\Model\WebsiteFactory;
use Navigate\AllowSvgWebpAvifImage\Model\Image\Adapter\Gd2Rewrite;

class RecurringData implements InstallDataInterface
{
    private mixed $s3productImages;

    protected S3Client $s3client;
    protected AdapterInterface $connection;
    protected ProductFactory $productFactory;
    private State $state;
    private SourceItemsSaveInterface $sourceItemsSaveInterface;
    private DefaultSourceProviderInterface $defaultSourceProvider;
    private GetSourceItemBySourceCodeAndSku $getSourceItemBySourceCodeAndSku;
    private WebsiteFactory $websiteFactory;
    private AttributeSetFactory $attributeSetFactory;
    private Config $config;
    private Product $product;

    public function __construct(
        State                           $state,
        SourceItemsSaveInterface        $sourceItemsSaveInterface,
        DefaultSourceProviderInterface  $defaultSourceProvider,
        GetSourceItemBySourceCodeAndSku $getSourceItemBySourceCodeAndSku,
        WebsiteFactory                  $websiteFactory,
        AttributeSetFactory             $attributeSetFactory,
        Config                          $config,
        Product                         $product,
        ProductFactory                  $productFactory
    )
    {
        $this->state = $state;
        $this->sourceItemsSaveInterface = $sourceItemsSaveInterface;
        $this->defaultSourceProvider = $defaultSourceProvider;
        $this->getSourceItemBySourceCodeAndSku = $getSourceItemBySourceCodeAndSku;
        $objectManager = ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $this->connection = $resource->getConnection();
        $this->websiteFactory = $websiteFactory;
        $this->attributeSetFactory = $attributeSetFactory;
        $this->config = $config;
        $this->product = $product;
        $this->productFactory = $productFactory;
        $this->s3client = Utils::getS3Client();
        $contents = $this->s3client->listObjectsV2([
            'Bucket' => Utils::Bucket,
            'Prefix' => 'pub/media/catalog/product'
        ]);
        $this->s3productImages = $contents['Contents'] != null ? $contents['Contents'] : [];
    }

    /**
     * @throws NoSuchEntityException
     * @throws FileSystemException
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws Exception
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context): void
    {
        try {
            $this->state->setAreaCode(Area::AREA_GLOBAL);
        } catch (LocalizedException) {
        }

        $installer = $setup;
        $installer->startSetup();

        //$this->setConfigs();
        //$this->copyModifiedFilesToVendor();

        if (version_compare($context->getVersion(), '1.0.0', '<=')) {
            echo "Start install recurring v1.0.0\n";
            //$this->install_v_1_0_0($setup);
            echo "End install recurring v1.0.0\n";
        }

        $installer->endSetup();
    }

    private function setConfigs(): void
    {
        echo "Start setting configs\n";
        $configs = json_decode(file_get_contents(__DIR__ . '/../Configs/core_config_data.json'));
        foreach ($configs as $key => $item) {
            /*if (Utils::isLocal()) {
                if ((str_contains($item->path, 'base_url') || str_contains($item->path, 'base_link_url'))) {
                    $item->value = 'http://localhost:88/';
                }
                if ($item->path == 'web/secure/use_in_frontend'
                    || $item->path == 'web/secure/use_in_adminhtml'
                    || $item->path == 'admin/security/use_form_key') {
                    $item->value = '0';
                }
            } else {
                if ($item->path == 'gardenlawn/core/enviroment') {
                    $item->value = 'production';
                }
            }*/
            $this->config->saveConfig($item->path, $item->value, $item->scope, $item->scope_id);
        }

        $contentPolicy = addslashes(file_get_contents(__dir__ . '/../files/privatepolicy.html'));
        $sql = "update amasty_gdpr_privacy_policy set content = '$contentPolicy' where policy_version = 'v1.0';";
        $this->connection->query($sql);
        $sql = "update cms_page set content = '$contentPolicy' where identifier = 'privacy-policy-cookie-restriction-mode';";
        $this->connection->query($sql);
        $sql = "update amasty_gdpr_consents set name = 'Polityka prywatności i cookie' where consent_id = 1;";
        $this->connection->query($sql);
        $sql = "update amasty_gdpr_consents_scope set store_id = 0, consent_entity_id = 1, is_enabled = 1, is_required = 1, log_the_consent = 1, hide_the_consent_after_user_left_the_consent = 0, consent_location = 'registration,checkout,contactus,subscription', link_type = 1, cms_page_id = 4, consent_text = 'Przeczytałem/am i akceptuję <a href=\"#\" class=\"link-underline\">politykę prywatności</a>', countries = null, visibility = 0, sort_order = 0 WHERE id = 1;";
        $this->connection->query($sql);

        $contentTerms = addslashes(file_get_contents(__dir__ . '/../files/terms.html'));
        $sql = "update cms_page set content = '$contentTerms' where identifier = 'terms';";
        $this->connection->query($sql);
        $sql = "UPDATE tax_calculation SET tax_calculation_rate_id = 3, tax_calculation_rule_id = 1, customer_tax_class_id = 3, product_tax_class_id = 2 WHERE tax_calculation_id = 1;";
        $this->connection->query($sql);

        echo "End setting configs\n";
    }

    /**
     * @throws LocalizedException
     * @throws FileSystemException
     */
    private function install_v_1_0_0($setup): void
    {
        $attributeSetFactory = $this->attributeSetFactory->create();
        $attrId = $attributeSetFactory->load(9)->getId() ?? 0;

        if ($attrId == 9) {
            $this->saveProducts(
                9,
                'gardenlawn_source',
                0,
                1,
                Utils::getFileUrl('equipment/'),
                'pub/media/equipment',
                [
                    ['code' => 'custom_design', 'value' => 6],
                    ['code' => 'category_ids', 'value' => 6]
                ]
            );
        }

        $attributeSetFactory = $this->attributeSetFactory->create();
        $attrId = $attributeSetFactory->load(10)->getId() ?? 0;

        if ($attrId == 10) {
            $this->saveProducts(
                10,
                'gardenlawn_source',
                20.0,
                99999999,
                Utils::getFileUrl('grass/'),
                'pub/media/grass',
                [
                    ['code' => 'manufacturer', 'value' => 4],
                    ['code' => 'custom_design', 'value' => 6],
                    ['code' => 'category_ids', 'value' => 7]
                ]
            );

            $this->addTierPriceToGrassInRoll();
        }
    }

    /**
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException|LocalizedException
     */
    private function addTierPriceToGrassInRoll(): void
    {
        $tierPrices[] = [
            'website_id' => 0,
            'cust_group' => 32000,
            'price_qty' => 1,
            'price' => 16.00
        ];

        /*$tierPrices[] = [
            'website_id' => 0,
            'cust_group' => 32000,
            'price_qty' => 100,
            'price' => 18.00
        ];

        $tierPrices[] = [
            'website_id' => 0,
            'cust_group' => 32000,
            'price_qty' => 300,
            'price' => 17.00
        ];

        $tierPrices[] = [
            'website_id' => 0,
            'cust_group' => 32000,
            'price_qty' => 500,
            'price' => 16.00
        ];

        $tierPrices[] = [
            'website_id' => 0,
            'cust_group' => 4,
            'price_qty' => 1,
            'price' => 16.00
        ];*/

        $params = $_SERVER;
        $bootstrap = Bootstrap::create(BP, $params);
        $objectManager = $bootstrap->getObjectManager();
        $appState = $objectManager->get('\Magento\Framework\App\State');
        $appState->setAreaCode('global');

        $product_id = $this->product->getIdBySku('Trawa-w-rolce');
        $product = $objectManager->create('Magento\Catalog\Model\Product')
            ->load($product_id)
            ->setTierPrice($tierPrices)
            ->save();
    }

    /**
     * @throws Exception
     */
    private function saveProducts(
        int    $attributeSetId,
        string $source,
        float  $price,
        int    $qty,
        string $dir,
        string $s3Prefix,
        array  $attributes = [],
        string $parentCategoryName = 'GardenLawn Category'
    ): void
    {
        try {
            $website = $this->websiteFactory->create();
            $webSiteId = $website->load('gardenlawn_website')->getId();

            echo 'Start saving ' . $dir;
            echo "\n";

            $contents = $this->s3client->listObjectsV2([
                'Bucket' => Utils::Bucket,
                'Prefix' => $s3Prefix
            ]);

            $objectManager = ObjectManager::getInstance();
            $product = $objectManager->get('\Magento\Catalog\Model\Product');
            if ($contents['Contents'] != null) {
                $key = '';
                $gallery = [];
                $name = '';
                $sku = '';
                $description = '';
                foreach ($contents['Contents'] as $content) {
                    $path = str_replace('media/', '', $content['Key']);
                    $fullPath = Utils::getFileUrl($path);
                    $tokens = explode('/', $fullPath);
                    $sku = str_replace('_', '-', str_replace('+', '-', $tokens[sizeof($tokens) - 2]));
                    $productId = $product->getIdBySku($sku) ?? 0;
                    if ($productId == 0) {
                        if ($key != '' && $key != $tokens[sizeof($tokens) - 2]) {
                            $name = str_replace('+', ' ', $key);
                            $sku = str_replace('_', '-', str_replace(' ', '-', $name));
                            echo $sku, "\n";
                            echo print_r($gallery, true), "\n";
                            $this->saveProduct($name, $sku, $webSiteId, $attributeSetId, $description, $source, $price, $qty, $gallery, $attributes);
                            $key = $tokens[sizeof($tokens) - 2];
                            $gallery = [];
                            if ((str_contains($fullPath, '.jp') || str_contains($fullPath, '.png') || str_contains($fullPath, '.svg')) &&
                                !str_contains($fullPath, '.thumbs') &&
                                !str_contains($fullPath, 'cache')) {
                                $gallery [] = $fullPath;
                            }
                        } else {
                            $key = $tokens[sizeof($tokens) - 2];
                            if ((str_contains($fullPath, '.jp') || str_contains($fullPath, '.png') || str_contains($fullPath, '.svg')) &&
                                !str_contains($fullPath, '.thumbs') &&
                                !str_contains($fullPath, 'cache')) {
                                $gallery [] = $fullPath;
                            }
                        }
                    }
                }
                if ($gallery != []) {
                    $name = str_replace('+', ' ', $key);
                    $sku = str_replace('_', '-', str_replace(' ', '-', $name));
                    echo $sku, "\n";
                    echo print_r($gallery, true), "\n";
                    $this->saveProduct($name, $sku, $webSiteId, $attributeSetId, $description, $source, $price, $qty, $gallery, $attributes);
                }

                echo 'End saving ' . $dir;
                echo "\n";
            }
        } catch (Exception $e) {
            echo 'End saving with error';
            echo "\n";
            Logger::writeLog($e);
        }
    }

    /**
     * @throws FileSystemException
     * @throws LocalizedException
     * @throws Exception
     */
    private function saveProduct(
        string $name,
        string $sku,
        int    $websiteId,
        int    $attributeSetId,
        string $description,
        string $source,
        float  $price,
        int    $qty,
        array  $gallery,
        array  $attributes = []
    ): void
    {
        if (!count($gallery)) {
            return;
        }

        $this->connection->beginTransaction();

        try {
            $objectManager = ObjectManager::getInstance();
            $appState = $objectManager->get('Magento\Framework\App\State');

            try {
                $appState->setAreaCode('frontend');
            } catch (Exception) {
            }

            $product = $objectManager->get('\Magento\Catalog\Model\Product');

            $productId = $product->getIdBySku($sku) ?? 0;

            $params = $_SERVER;
            $bootstrap = Bootstrap::create(BP, $params);
            $objectManager = $bootstrap->getObjectManager();
            $state = $objectManager->get('Magento\Framework\App\State');
            try {
                $state->setAreaCode('frontend');
            } catch (Exception) {
            }
            $productCreate = $objectManager->create('Magento\Catalog\Model\Product');
            echo 'Start saving sku: ' . $sku;
            echo "\n";

            if ($productId == 0) {
                $productCreate->setSku($sku);
                $productCreate->setName($name);
                $productCreate->setDescription($description ?? '');
                $productCreate->setAttributeSetId($attributeSetId);
                $productCreate->setStatus(Status::STATUS_ENABLED);
                $productCreate->setWeight(1);
                $productCreate->setVisibility(Visibility::VISIBILITY_BOTH);
                $productCreate->setTaxClassId(2);
                $productCreate->setTypeId(Type::TYPE_SIMPLE);
                $productCreate->setPrice($price);
                $productCreate->setWebsiteIds([$websiteId]);
                $productCreate->setStockData(array(
                        'use_config_manage_stock' => 0,
                        'manage_stock' => 1,
                        'min_sale_qty' => 1,
                        'is_in_stock' => 1,
                        'qty' => $qty
                    )
                );

                $productCreate->save();
                $productId = $productCreate->getId();

                $productRepository = $objectManager->get('\Magento\Catalog\Api\ProductRepositoryInterface');
                $product = $productRepository->getById($productId);
                foreach ($attributes as $key => $attribute) {
                    $product->setData($attribute['code'], $attribute['value']);
                }
                Logger::writeLog(print_r($product, true));
                $productRepository->save($product);
                $product = $objectManager->create('Magento\Catalog\Model\Product')->load($productId);
                asort($gallery);
                foreach ($gallery as $key => $img) {
                    if ((str_contains($img, '.svg') || str_contains($img, '.jp') || str_contains($img, '.png')) && !str_contains($img, '.thumbs')) {
                        $this->saveImage($product, $img);
                    }
                }
                $imagePath = $product->getMediaGalleryImages()->getFirstItem()->getFile();
                if ($imagePath) {
                    $objectManager->get('Magento\Catalog\Model\Product\Action')
                        ->updateAttributes(
                            [0 => $product->getId()],
                            array("thumbnail" => $imagePath, "small_image" => $imagePath, "image" => $imagePath), 0
                        );
                }
                $objectManager = ObjectManager::getInstance();
                $sourceItem = $objectManager->create('Magento\InventoryCatalog\Model\BulkSourceAssign');
                echo $sku, ' ', $source, "\n";
                $sourceItem->execute([$sku], [$source]);
                $this->updateProductSource($sku, $qty, $source);

                echo 'End saving product SKU: ' . $sku;
                echo "\n";
            }
            $this->connection->commit();
        } catch (Exception $e) {
            Logger::writeLog($e);
            $this->connection->rollBack();
        }
    }

    /**
     * @throws NoSuchEntityException
     */
    private function saveImage($product, $imageUrl): void
    {
        $newFileName = $product->getName() . ' ' . baseName($imageUrl);

        $tokens = explode('/', $imageUrl);
        $key = str_replace('+', '_', $tokens[sizeof($tokens) - 2]) . '_' . basename($imageUrl);

        foreach ($this->s3productImages as $content) {
            if (str_contains($content['Key'], $key) && !str_contains($content['Key'], 'cache')) {
                $this->s3client->deleteObject([
                    'Bucket' => Utils::Bucket,
                    'Key' => $content['Key']
                ]);
            }
        }

        $this->s3client->copyObject([
            'Bucket' => Utils::Bucket,
            'CopySource' => Utils::Bucket . '/pub/media/' . str_replace(Utils::getMediaUrl(), '', $imageUrl),
            'Key' => 'pub/media/tmp/' . $newFileName
        ]);

        $imgUrl = str_replace('%20', ' ', $this->s3client->getObjectUrl(
            Utils::Bucket,
            'pub/media/tmp/' . $newFileName
        ));

        $product->addImageToMediaGallery($imgUrl, null, true, false);
        $product->save();
    }

    /**
     * @throws NoSuchEntityException
     * @throws ValidationException
     * @throws CouldNotSaveException
     * @throws InputException
     */
    private function updateProductSource(string $sku, float $qty, string $source = null): void
    {
        //In case we want to update a specific or the default source
        if ($source) {
            $sourceItem = $this->getSourceItemBySourceCodeAndSku->execute($source, $sku);
        } else {
            $sourceItem = $this->getSourceItemBySourceCodeAndSku->execute($this->defaultSourceProvider->getCode(), $sku);
        }

        $sourceItem->setQuantity($qty);
        $sourceItem->setStatus($qty > 0 ? SourceItemInterface::STATUS_IN_STOCK : SourceItemInterface::STATUS_OUT_OF_STOCK);
        $this->sourceItemsSaveInterface->execute([$sourceItem]);
    }

    private function copyModifiedFilesToVendor(): void
    {
        $files = Utils::getAllFiles(realpath(__DIR__ . "/../CopyToVendor"));
        foreach ($files as $key => $file) {
            $destination = '/var/www/html/magento' . str_replace(realpath(__DIR__ . "/../CopyToVendor/"), '', $file);
            Logger::writeLog("Copy $file => $destination");
            copy($file, $destination);
        }
    }
}
