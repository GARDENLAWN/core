<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Hyva\Checkout\Magewire\Checkout\AddressView\BillingDetails;

use GardenLawn\Company\Helper\Data as CompanyHelper;
use Hyva\Checkout\Magewire\Checkout\AddressView\BillingDetails\AddressList;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class AddressListPlugin
{
    /**
     * @var CompanyHelper
     */
    private CompanyHelper $companyHelper;

    /**
     * @var AddressRepositoryInterface
     */
    private AddressRepositoryInterface $addressRepository;

    /**
     * @var SessionCheckout
     */
    private SessionCheckout $sessionCheckout;

    /**
     * @param CompanyHelper $companyHelper
     * @param AddressRepositoryInterface $addressRepository
     * @param SessionCheckout $sessionCheckout
     */
    public function __construct(
        CompanyHelper $companyHelper,
        AddressRepositoryInterface $addressRepository,
        SessionCheckout $sessionCheckout
    ) {
        $this->companyHelper = $companyHelper;
        $this->addressRepository = $addressRepository;
        $this->sessionCheckout = $sessionCheckout;
    }

    /**
     * @param AddressList $subject
     * @param int $id
     * @return array
     * @throws LocalizedException
     */
    public function beforeActivateAddress(AddressList $subject, $id): array
    {
        $quote = $this->sessionCheckout->getQuote();
        $customerId = (int) $quote->getCustomerId();

        // Check if the current customer is a B2B customer
        if (in_array($this->companyHelper->getCurrentCustomerGroupId(), $this->companyHelper->getB2bCustomerGroups())) {
            try {
                $address = $this->addressRepository->getById($id);

                // Ensure the address belongs to the current customer
                if ((int) $address->getCustomerId() !== $customerId) {
                    throw new LocalizedException(__('The selected address is not valid for this customer.'));
                }

                // If it's not the default billing address, prevent activation
                if (!$address->isDefaultBilling()) {
                    throw new LocalizedException(__('B2B customers can only use their default billing address for billing.'));
                }
            } catch (NoSuchEntityException $e) {
                throw new LocalizedException(__('The selected billing address does not exist.'));
            }
        }

        return [$id];
    }
}
