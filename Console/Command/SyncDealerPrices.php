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

class SyncDealerPrices extends Command
{
    private const XML_PATH_DEALER_GROUPS = 'gardenlawn_core/b2b/customer_groups';

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

    public function __construct(
        AppState $appState,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        StoreManagerInterface $storeManager,
        ProductTierPriceManagementInterface $tierPriceManagement,
        ProductTierPriceInterfaceFactory $tierPriceFactory,
        ScopeConfigInterface $scopeConfig,
        CurrencyFactory $currencyFactory
    ) {
        $this->appState = $appState;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->storeManager = $storeManager;
        $this->tierPriceManagement = $tierPriceManagement;
        $this->tierPriceFactory = $tierPriceFactory;
        $this->scopeConfig = $scopeConfig;
        $this->currencyFactory = $currencyFactory;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('gardenlawn:dealer:sync-prices')
            ->setDescription('Syncs dealer_price attribute to tier prices for B2B customer groups.');
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
            $output->writeln('<error>No B2B customer groups configured. Aborting.</error>');
            return 1;
        }
        $output->writeln('Target customer groups: ' . implode(', ', $dealerGroups));

        $conversionRate = $this->getEurToPlnRate();
        if (!$conversionRate) {
            $output->writeln('<error>Could not determine EUR to PLN conversion rate. Please check Currency Rates.</error>');
            return 1;
        }
        $output->writeln('Calculated EUR -> PLN rate: ' . $conversionRate);

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('dealer_price', 0, 'gt')
            ->create();
        $products = $this->productRepository->getList($searchCriteria)->getItems();

        if (empty($products)) {
            $output->writeln('<comment>No products with dealer_price found.</comment>');
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

                // Use ProductRepository to save which is more reliable for tier prices
                $productToSave = $this->productRepository->get($product->getSku(), true, null, true);
                $existingTierPrices = $productToSave->getTierPrices() ?? [];

                // Filter out old dealer prices
                $newTierPrices = [];
                foreach ($existingTierPrices as $price) {
                    if (!in_array((int)$price->getCustomerGroupId(), $dealerGroups)) {
                        $newTierPrices[] = $price;
                    }
                }

                // Add new dealer prices
                foreach ($dealerGroups as $groupId) {
                    $tierPrice = $this->tierPriceFactory->create();
                    $tierPrice->setCustomerGroupId($groupId);
                    $tierPrice->setQty(1);
                    $tierPrice->setValue($dealerPricePln);
                    $newTierPrices[] = $tierPrice;
                }

                $productToSave->setTierPrices($newTierPrices);
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
        return $groups ? array_map('intval', explode(',', $groups)) : [];
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
}
