<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Hyva\Checkout\Model\Form\EntityFormSaveService;

use GardenLawn\Company\Helper\Data as CompanyHelper;
use Hyva\Checkout\Model\Form\EntityFormInterface;
use Hyva\Checkout\Model\Form\EntityFormSaveService\EavAttributeBillingAddress;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class EavAttributeBillingAddressPlugin
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
     * @param EavAttributeBillingAddress $subject
     * @param EntityFormInterface $form
     * @return array
     * @throws LocalizedException
     */
    public function beforeSave(
        EavAttributeBillingAddress $subject,
        EntityFormInterface $form
    ): array {
        $data = $form->toArray();
        $quote = $this->sessionCheckout->getQuote();
        $customerId = (int) $quote->getCustomerId();

        // Check if the current customer is a B2B customer
        if (in_array($this->companyHelper->getCurrentCustomerGroupId(), $this->companyHelper->getB2bCustomerGroups())) {
            // Case 1: Adding a new address (no 'id' in data)
            if (!isset($data['id'])) {
                throw new LocalizedException(__('B2B customers cannot add new billing addresses from the cart.'));
            }

            // Case 2: Selecting an existing address (has 'id' in data)
            try {
                $addressId = (int) $data['id'];
                $address = $this->addressRepository->getById($addressId);

                // Ensure the address belongs to the current customer
                if ((int) $address->getCustomerId() !== $customerId) {
                    throw new LocalizedException(__('The selected address is not valid for this customer.'));
                }

                // If it's not the default billing address, block it.
                if (!$address->isDefaultBilling()) {
                    throw new LocalizedException(__('B2B customers can only use their default billing address for billing.'));
                }
                // If it is the default billing address, allow it to proceed.
                // The previous logic for preventing changes to the default billing address from the customer account panel
                // is handled by the FormPostPlugin. This plugin focuses on checkout specific restrictions.

            } catch (NoSuchEntityException $e) {
                // If address ID is provided but not found, it's an invalid request.
                throw new LocalizedException(__('The selected billing address does not exist.'));
            }
        }

        return [$form];
    }
}
