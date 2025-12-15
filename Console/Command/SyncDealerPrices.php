<?php
declare(strict_types=1);

namespace GardenLawn\Core\Console\Command;

use Exception;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\State as AppState;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\Currency;
use Magento\Catalog\Api\ScopedProductTierPriceManagementInterface;
use Magento\Catalog\Api\Data\ProductTierPriceInterfaceFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class SyncDealerPrices extends Command
{
    private const string XML_PATH_DEALER_GROUPS = 'gardenlawn_core/b2b/customer_groups';

    private AppState $appState;
    private ProductRepositoryInterface $productRepository;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private StoreManagerInterface $storeManager;
    private ScopedProductTierPriceManagementInterface $tierPriceManagement;
    private ProductTierPriceInterfaceFactory $tierPriceFactory;
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        AppState $appState,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        StoreManagerInterface $storeManager,
        ScopedProductTierPriceManagementInterface $tierPriceManagement,
        ProductTierPriceInterfaceFactory $tierPriceFactory,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->appState = $appState;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->storeManager = $storeManager;
        $this->tierPriceManagement = $tierPriceManagement;
        $this->tierPriceFactory = $tierPriceFactory;
        $this->scopeConfig = $scopeConfig;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('gardenlawn:dealer:sync-prices')
            ->setDescription('Syncs dealer_price attribute to tier prices for B2B customer groups.');
        parent::configure();
    }

    /**
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     * @throws LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->appState->setAreaCode('adminhtml');
        $output->writeln('<info>Starting dealer price synchronization...</info>');

        $dealerGroups = $this->getDealerGroups();
        if (empty($dealerGroups)) {
            $output->writeln('<error>No B2B customer groups configured. Aborting.</error>');
            return 1;
        }
        $output->writeln('Target customer groups: ' . implode(', ', $dealerGroups));

        $conversionRate = $this->getCurrencyRate('EUR', 'PLN');
        if (!$conversionRate) {
            $output->writeln('<error>Could not retrieve EUR to PLN conversion rate. Aborting.</error>');
            return 1;
        }
        $output->writeln('Current EUR -> PLN rate: ' . $conversionRate);

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('dealer_price', 0, 'gt')
            ->create();
        $products = $this->productRepository->getList($searchCriteria)->getItems();

        if (empty($products)) {
            $output->writeln('<comment>No products with dealer_price found.</comment>');
            return 0;
        }

        $progressBar = new ProgressBar($output, count($products));
        $progressBar->start();

        foreach ($products as $product) {
            $dealerPriceEur = (float)$product->getData('dealer_price');
            $dealerPricePln = round($dealerPriceEur * $conversionRate, 2);

            // Get existing tier prices to avoid duplicates
            $existingTierPrices = $product->getTierPrices();

            foreach ($dealerGroups as $groupId) {
                // Remove old tier price for this group if it exists
                foreach ($existingTierPrices as $key => $price) {
                    if ((int)$price->getCustomerGroupId() === $groupId) {
                        $this->tierPriceManagement->remove($product->getSku(), $price->getTierPriceId());
                    }
                }

                // Add new tier price
                $tierPrice = $this->tierPriceFactory->create();
                $tierPrice->setCustomerGroupId($groupId);
                $tierPrice->setQty(1);
                $tierPrice->setValue($dealerPricePln);

                $this->tierPriceManagement->add($product->getSku(), $tierPrice);
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $output->writeln('');
        $output->writeln('<info>Dealer price synchronization completed successfully.</info>');
        $output->writeln('<comment>Please run "bin/magento indexer:reindex catalog_product_price" to apply changes.</comment>');

        return 0;
    }

    private function getDealerGroups(): array
    {
        $groups = $this->scopeConfig->getValue(self::XML_PATH_DEALER_GROUPS, ScopeInterface::SCOPE_STORE);
        return $groups ? array_map('intval', explode(',', $groups)) : [];
    }

    private function getCurrencyRate(string $from, string $to): ?float
    {
        try {
            $rate = $this->storeManager->getStore()->getBaseCurrency()->getRate($to);
            return $rate ? (float)$rate : null;
        } catch (Exception $e) {
            return null;
        }
    }
}
