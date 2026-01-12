<?php
declare(strict_types=1);

namespace GardenLawn\Core\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\State as AppState;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Catalog\Api\ProductTierPriceManagementInterface;
use Magento\Catalog\Api\Data\ProductTierPriceInterfaceFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Exception\LocalizedException;
use GardenLawn\Core\Model\PriceCalculator;

class SyncDealerPrices extends Command
{
    private const XML_PATH_DEALER_GROUPS = 'gardenlawn_core/dealer_price/customer_groups';
    private const XML_PATH_ATTRIBUTE_SETS = 'gardenlawn_core/dealer_price/attribute_sets';

    /** @var AppState */
    private $appState;
    /** @var ProductRepositoryInterface */
    private $productRepository;
    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;
    /** @var StoreManagerInterface */
    private $storeManager;
    /** @var ProductTierPriceManagementInterface */
    private $tierPriceManagement;
    /** @var ProductTierPriceInterfaceFactory */
    private $tierPriceFactory;
    /** @var ScopeConfigInterface */
    private $scopeConfig;
    /** @var CurrencyFactory */
    private $currencyFactory;
    /** @var PriceCalculator */
    private $priceCalculator;

    public function __construct(
        AppState $appState,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        StoreManagerInterface $storeManager,
        ProductTierPriceManagementInterface $tierPriceManagement,
        ProductTierPriceInterfaceFactory $tierPriceFactory,
        ScopeConfigInterface $scopeConfig,
        CurrencyFactory $currencyFactory,
        PriceCalculator $priceCalculator
    ) {
        $this->appState = $appState;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->storeManager = $storeManager;
        $this->tierPriceManagement = $tierPriceManagement;
        $this->tierPriceFactory = $tierPriceFactory;
        $this->scopeConfig = $scopeConfig;
        $this->currencyFactory = $currencyFactory;
        $this->priceCalculator = $priceCalculator;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('gardenlawn:dealer:sync-prices')
            ->setDescription('Syncs dealer_price attribute to tier prices for B2B customer groups and updates main price.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->appState->setAreaCode('adminhtml');
        } catch (LocalizedException $e) {
            // Area code already set
        }

        $output->writeln('<info>Starting dealer price synchronization...</info>');

        $dealerGroups = $this->getDealerGroups();
        if (empty($dealerGroups)) {
            $output->writeln('<error>No Dealer customer groups configured. Aborting.</error>');
            return 1;
        }
        $output->writeln('Target customer groups: ' . implode(', ', $dealerGroups));

        $conversionRate = $this->getEurToPlnRate();
        if (!$conversionRate) {
            $output->writeln('<error>Could not determine EUR to PLN conversion rate. Please check Currency Rates.</error>');
            return 1;
        }
        $output->writeln('Calculated EUR -> PLN rate: ' . $conversionRate);

        // Filter by Attribute Sets
        $attributeSetIds = $this->getAttributeSetIds();
        if (!empty($attributeSetIds)) {
            $this->searchCriteriaBuilder->addFilter('attribute_set_id', $attributeSetIds, 'in');
            $output->writeln('Filtering by Attribute Sets: ' . implode(', ', $attributeSetIds));
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('dealer_price', 0, 'gt')
            ->create();
        $products = $this->productRepository->getList($searchCriteria)->getItems();

        if (empty($products)) {
            $output->writeln('<comment>No products with dealer_price found (matching criteria).</comment>');
            return 0;
        }

        $progressBar = new \Symfony\Component\Console\Helper\ProgressBar($output, count($products));
        $progressBar->start();

        foreach ($products as $product) {
            try {
                $dealerPriceEur = (float)$product->getData('dealer_price');
                $dealerPricePln = round($dealerPriceEur * $conversionRate, 2);

                if ($dealerPricePln <= 0) {
                    continue;
                }

                // Calculate Main Price using shared logic
                $calculationResult = $this->priceCalculator->calculateFinalPrice($dealerPricePln);
                $finalMainPriceNet = $calculationResult['net_final'];

                // Use ProductRepository to save which is more reliable for tier prices
                $productToSave = $this->productRepository->get($product->getSku(), true, null, true);
                $existingTierPrices = $productToSave->getTierPrices() ?? [];
                $currentMainPrice = (float)$productToSave->getPrice();

                // Filter out ALL existing dealer prices (qty = 1)
                $newTierPrices = [];
                foreach ($existingTierPrices as $price) {
                    if ((float)$price->getQty() != 1) {
                        $newTierPrices[] = $price;
                    }
                }

                // Add new dealer prices for currently configured groups
                foreach ($dealerGroups as $groupId) {
                    $tierPrice = $this->tierPriceFactory->create();
                    $tierPrice->setCustomerGroupId($groupId);
                    $tierPrice->setQty(1);
                    $tierPrice->setValue($dealerPricePln);
                    $newTierPrices[] = $tierPrice;
                }

                // Optimization: Check if data actually changed before saving
                $tierPricesChanged = !$this->areTierPricesEqual($existingTierPrices, $newTierPrices);
                $mainPriceChanged = abs($currentMainPrice - $finalMainPriceNet) > 0.0001;

                if (!$tierPricesChanged && !$mainPriceChanged) {
                    $progressBar->advance();
                    continue;
                }

                $productToSave->setTierPrices($newTierPrices);
                $productToSave->setPrice($finalMainPriceNet);
                $this->productRepository->save($productToSave);

            } catch (\Exception $e) {
                $output->writeln('');
                $output->writeln('<error>Error processing product SKU ' . $product->getSku() . ': ' . $e->getMessage() . '</error>');
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $output->writeln('');
        $output->writeln('<info>Dealer price synchronization completed.</info>');
        $output->writeln('<comment>Please run "bin/magento indexer:reindex catalog_product_price" to apply changes.</comment>');

        return 0;
    }

    private function getDealerGroups(): array
    {
        $groups = $this->scopeConfig->getValue(self::XML_PATH_DEALER_GROUPS, ScopeInterface::SCOPE_STORE);
        if (!$groups) {
            return [];
        }
        $groupsArray = explode(',', $groups);
        return array_filter(array_map('intval', $groupsArray));
    }

    private function getAttributeSetIds(): array
    {
        $sets = $this->scopeConfig->getValue(self::XML_PATH_ATTRIBUTE_SETS, ScopeInterface::SCOPE_STORE);
        if (!$sets) {
            return [];
        }
        $setsArray = explode(',', $sets);
        return array_filter(array_map('intval', $setsArray));
    }

    private function getEurToPlnRate(): ?float
    {
        try {
            $baseCurrency = $this->storeManager->getStore()->getBaseCurrency();
            $baseCode = $baseCurrency->getCode();

            if ($baseCode === 'PLN') {
                $plnToEur = $baseCurrency->getRate('EUR');
                if ($plnToEur > 0) {
                    return 1 / $plnToEur;
                }
            }

            if ($baseCode === 'EUR') {
                return (float)$baseCurrency->getRate('PLN');
            }

            $eurCurrency = $this->currencyFactory->create()->load('EUR');
            $rate = $eurCurrency->getRate('PLN');

            return $rate ? (float)$rate : null;

        } catch (\Exception $e) {
            return null;
        }
    }

    private function areTierPricesEqual(array $existing, array $new): bool
    {
        if (count($existing) !== count($new)) {
            return false;
        }

        $generateKey = function ($price) {
            return sprintf(
                '%s-%s-%s',
                (int)$price->getCustomerGroupId(),
                (float)$price->getQty(),
                (float)$price->getValue()
            );
        };

        $existingKeys = array_map($generateKey, $existing);
        $newKeys = array_map($generateKey, $new);

        sort($existingKeys);
        sort($newKeys);

        return $existingKeys === $newKeys;
    }
}
