<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Payment;

use Aurora\Santander\ViewModel\Rates;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Checkout\Model\Session as CheckoutSession;

class SantanderRatesViewModelPlugin
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
     * @param Session $customerSession
     * @param ScopeConfigInterface $scopeConfig
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        Session $customerSession,
        ScopeConfigInterface $scopeConfig,
        CheckoutSession $checkoutSession
    ) {
        $this->customerSession = $customerSession;
        $this->scopeConfig = $scopeConfig;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Filter out 0% installment options for B2B customer groups
     *
     * @param Rates $subject
     * @param array $result
     * @return array
     */
    public function afterGetAvailableInstallmentOptions(Rates $subject, array $result): array
    {
        if (empty($result)) {
            return $result;
        }

        $b2bGroups = $this->scopeConfig->getValue(
            'gardenlawn_core/b2b/customer_groups',
            ScopeInterface::SCOPE_STORE
        );

        if (empty($b2bGroups)) {
            return $result;
        }

        $b2bGroupsArray = explode(',', $b2bGroups);

        // Try to get customer group from checkout session quote first, then customer session
        $customerGroupId = null;
        try {
            if ($this->checkoutSession->getQuoteId()) {
                $customerGroupId = $this->checkoutSession->getQuote()->getCustomerGroupId();
            }
        } catch (\Exception $e) {
            // Ignore if quote not available
        }

        if ($customerGroupId === null && $this->customerSession->isLoggedIn()) {
            $customerGroupId = $this->customerSession->getCustomer()->getGroupId();
        }

        // If we still don't have a group ID (e.g. guest), assume NOT B2B (unless guest is configured as B2B which is rare)
        if ($customerGroupId === null) {
            return $result;
        }

        if (in_array((string)$customerGroupId, $b2bGroupsArray)) {
            foreach ($result as $key => $option) {
                $percent = isset($option['percent']) ? (float)str_replace(',', '.', (string)$option['percent']) : 0.0;
                if ($percent == 0) {
                    unset($result[$key]);
                }
            }
        }

        return $result;
    }
}
