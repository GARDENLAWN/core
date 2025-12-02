<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Hyva\Checkout\ViewModel\Checkout\AddressView\AddressList;

use GardenLawn\Company\Helper\Data as CompanyHelper;
use Hyva\Checkout\ViewModel\Checkout\AddressView\AddressList\AddressListBilling;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class AddressListBillingPlugin
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
     * @var AddressRepositoryInterface
     */
    private AddressRepositoryInterface $addressRepository;

    /**
     * @param CompanyHelper $companyHelper
     * @param SessionCheckout $sessionCheckout
     * @param AddressRepositoryInterface $addressRepository
     */
    public function __construct(
        CompanyHelper $companyHelper,
        SessionCheckout $sessionCheckout,
        AddressRepositoryInterface $addressRepository
    ) {
        $this->companyHelper = $companyHelper;
        $this->sessionCheckout = $sessionCheckout;
        $this->addressRepository = $addressRepository;
    }

    /**
     * @param AddressListBilling $subject
     * @param callable $proceed
     * @return AddressInterface[]
     */
    public function aroundGetAddressListItems(
        AddressListBilling $subject,
        callable $proceed
    ): array {
        $quote = $this->sessionCheckout->getQuote();
        $customerId = (int) $quote->getCustomerId();

        // If not a B2B customer or no customer logged in, proceed with original logic
        if (!$customerId || !in_array($this->companyHelper->getCurrentCustomerGroupId(), $this->companyHelper->getB2bCustomerGroups())) {
            return $proceed();
        }

        // For B2B customers, only return the default billing address
        try {
            $customer = $quote->getCustomer();
            if ($customer && $customer->getDefaultBilling()) {
                $defaultBillingAddress = $this->addressRepository->getById($customer->getDefaultBilling());
                return [$defaultBillingAddress];
            }
        } catch (NoSuchEntityException $e) {
            // Default billing address not found, return empty array
        }

        return [];
    }
}
