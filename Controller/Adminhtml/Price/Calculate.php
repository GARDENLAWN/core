<?php
declare(strict_types=1);

namespace GardenLawn\Core\Controller\Adminhtml\Price;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use GardenLawn\Core\Model\PriceCalculator;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\CurrencyFactory;

class Calculate extends Action
{
    /** @var JsonFactory */
    private $jsonFactory;
    /** @var ProductRepositoryInterface */
    private $productRepository;
    /** @var PriceCalculator */
    private $priceCalculator;
    /** @var StoreManagerInterface */
    private $storeManager;
    /** @var CurrencyFactory */
    private $currencyFactory;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        ProductRepositoryInterface $productRepository,
        PriceCalculator $priceCalculator,
        StoreManagerInterface $storeManager,
        CurrencyFactory $currencyFactory
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->productRepository = $productRepository;
        $this->priceCalculator = $priceCalculator;
        $this->storeManager = $storeManager;
        $this->currencyFactory = $currencyFactory;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $sku = $this->getRequest()->getParam('sku');

        if (!$sku) {
            return $result->setData(['error' => 'Please provide a SKU.']);
        }

        try {
            $product = $this->productRepository->get($sku);
            $dealerPriceEur = (float)$product->getData('dealer_price');

            if ($dealerPriceEur <= 0) {
                return $result->setData(['error' => 'Product has no dealer_price set.']);
            }

            $conversionRate = $this->getEurToPlnRate();
            if (!$conversionRate) {
                return $result->setData(['error' => 'Could not determine EUR to PLN rate.']);
            }

            $dealerPricePln = round($dealerPriceEur * $conversionRate, 2);
            $calculation = $this->priceCalculator->calculateFinalPrice($dealerPricePln);

            return $result->setData([
                'success' => true,
                'product_name' => $product->getName(),
                'dealer_price_eur' => $dealerPriceEur,
                'dealer_price_pln' => $dealerPricePln,
                'rate' => $conversionRate,
                'calculation' => $calculation
            ]);

        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $result->setData(['error' => 'Product not found.']);
        } catch (\Exception $e) {
            return $result->setData(['error' => $e->getMessage()]);
        }
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
