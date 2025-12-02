<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Hyva\Checkout\Model\ConfigData\HyvaThemes;

use GardenLawn\Company\Helper\Data as CompanyHelper;
use Hyva\Checkout\Model\ConfigData\HyvaThemes\SystemConfigBilling;
use Magento\Checkout\Model\Session as SessionCheckout;

class SystemConfigBillingPlugin
{
    /**
     * @var CompanyHelper
     */
    private CompanyHelper $companyHelper;

    /**
     * @var SessionCheckout
     */
    private SessionCheckout $sessionCheckout;

    /**
     * @param CompanyHelper $companyHelper
     * @param SessionCheckout $sessionCheckout
     */
    public function __construct(
        CompanyHelper $companyHelper,
        SessionCheckout $sessionCheckout
    ) {
        $this->companyHelper = $companyHelper;
        $this->sessionCheckout = $sessionCheckout;
    }

    /**
     * @param SystemConfigBilling $subject
     * @param bool $result
     * @return bool
     */
    public function afterCanApplyShippingAsBillingAddress(
        SystemConfigBilling $subject,
        bool $result
    ): bool {
        $quote = $this->sessionCheckout->getQuote();
        $customerId = (int) $quote->getCustomerId();

        // If not a B2B customer or no customer logged in, proceed with original result
        if (!$customerId || !in_array($this->companyHelper->getCurrentCustomerGroupId(), $this->companyHelper->getB2bCustomerGroups())) {
            return $result;
        }

        // For B2B customers, always return false to hide the "same as billing" checkbox
        return false;
    }
}
