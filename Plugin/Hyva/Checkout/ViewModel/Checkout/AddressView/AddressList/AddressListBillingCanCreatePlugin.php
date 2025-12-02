<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Hyva\Checkout\ViewModel\Checkout\AddressView\AddressList;

use GardenLawn\Company\Helper\Data as CompanyHelper;
use Hyva\Checkout\ViewModel\Checkout\AddressView\AddressList\AddressListBilling;
use Magento\Checkout\Model\Session as SessionCheckout;

class AddressListBillingCanCreatePlugin
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
     * @param AddressListBilling $subject
     * @param bool $result
     * @return bool
     */
    public function afterCanCreateAddresses(
        AddressListBilling $subject,
        bool $result
    ): bool {
        $quote = $this->sessionCheckout->getQuote();
        $customerId = (int) $quote->getCustomerId();

        // If not a B2B customer or no customer logged in, proceed with original result
        if (!$customerId || !in_array($this->companyHelper->getCurrentCustomerGroupId(), $this->companyHelper->getB2bCustomerGroups())) {
            return $result;
        }

        // For B2B customers, always return false to prevent adding new addresses
        return false;
    }
}
