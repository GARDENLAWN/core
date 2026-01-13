<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Payment;

use Aurora\Santander\ViewModel\Installment;
use Aurora\Santander\ViewModel\Rates;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Checkout\Model\Session as CheckoutSession;

class SantanderInstallmentViewModelPlugin
{
    /**
     * @var Session
     */
    private Session $customerSession;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * @var Rates
     */
    private Rates $ratesViewModel;

    /**
     * @param Session $customerSession
     * @param ScopeConfigInterface $scopeConfig
     * @param CheckoutSession $checkoutSession
     * @param Rates $ratesViewModel
     */
    public function __construct(
        Session $customerSession,
        ScopeConfigInterface $scopeConfig,
        CheckoutSession $checkoutSession,
        Rates $ratesViewModel
    ) {
        $this->customerSession = $customerSession;
        $this->scopeConfig = $scopeConfig;
        $this->checkoutSession = $checkoutSession;
        $this->ratesViewModel = $ratesViewModel;
    }

    /**
     * Adjust calculated installment for B2B customers (remove 0% offers)
     *
     * @param Installment $subject
     * @param void $result
     * @param Product $product
     * @return void
     */
    public function afterCalculateInstallment(Installment $subject, $result, $product)
    {
        // Check if B2B
        $b2bGroups = $this->scopeConfig->getValue(
            'gardenlawn_core/b2b/customer_groups',
            ScopeInterface::SCOPE_STORE
        );

        if (empty($b2bGroups)) {
            return;
        }

        $b2bGroupsArray = explode(',', $b2bGroups);

        $customerGroupId = null;
        try {
            if ($this->checkoutSession->getQuoteId()) {
                $customerGroupId = $this->checkoutSession->getQuote()->getCustomerGroupId();
            }
        } catch (\Exception $e) {
            // Ignore
        }

        if ($customerGroupId === null && $this->customerSession->isLoggedIn()) {
            $customerGroupId = $this->customerSession->getCustomer()->getGroupId();
        }

        if ($customerGroupId === null || !in_array((string)$customerGroupId, $b2bGroupsArray)) {
            return;
        }

        // If current calculated installment is 0%, try to find another one
        if ($subject->percent == 0) {
            // Get available options (filtered by our other plugin)
            $options = $this->ratesViewModel->getAvailableInstallmentOptions([$product]);

            if (!empty($options)) {
                // Take the first available option (which should be > 0%)
                $firstOption = reset($options);
                $subject->qty = (int)$firstOption['qty'];
                $subject->percent = (float)str_replace(',', '.', (string)$firstOption['percent']);
            } else {
                // No options available for B2B, hide the block
                $subject->qty = null;
                $subject->percent = null;
            }
        }
    }
}
