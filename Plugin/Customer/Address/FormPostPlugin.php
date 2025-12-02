<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Customer\Address;

use GardenLawn\Company\Helper\Data as CompanyHelper;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Controller\Address\FormPost;
use Magento\Customer\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class FormPostPlugin
{
    /**
     * @var CompanyHelper
     */
    private CompanyHelper $companyHelper;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var AddressRepositoryInterface
     */
    private AddressRepositoryInterface $addressRepository;

    /**
     * @var Session
     */
    private Session $customerSession;

    /**
     * @param CompanyHelper $companyHelper
     * @param RequestInterface $request
     * @param AddressRepositoryInterface $addressRepository
     * @param Session $customerSession
     */
    public function __construct(
        CompanyHelper $companyHelper,
        RequestInterface $request,
        AddressRepositoryInterface $addressRepository,
        Session $customerSession
    ) {
        $this->companyHelper = $companyHelper;
        $this->request = $request;
        $this->addressRepository = $addressRepository;
        $this->customerSession = $customerSession;
    }

    /**
     * @param FormPost $subject
     * @throws LocalizedException
     */
    public function beforeExecute(FormPost $subject): void
    {
        $addressId = (int) $this->request->getParam('id');
        $customerId = (int) $this->customerSession->getCustomerId();
        $isB2BCustomer = in_array($this->companyHelper->getCurrentCustomerGroupId(), $this->companyHelper->getB2bCustomerGroups());
        $isDefaultBillingInRequest = (bool) $this->request->getParam('default_billing');

        if ($isB2BCustomer) {
            // Scenario 1: Attempting to add a new address
            if ($addressId === 0) {
                // If it's a new address and marked as default billing, prevent it.
                if ($isDefaultBillingInRequest) {
                    throw new LocalizedException(__('B2B customers cannot add new billing addresses from the customer account panel.'));
                }
            } else {
                // Scenario 2: Attempting to edit an existing address
                try {
                    $address = $this->addressRepository->getById($addressId);
                    // If the existing address is the default billing address, or if the request tries to make it default billing, prevent it.
                    if ((int) $address->getCustomerId() === $customerId && ($address->isDefaultBilling() || $isDefaultBillingInRequest)) {
                        throw new LocalizedException(__('B2B customers cannot change their default billing address.'));
                    }
                } catch (NoSuchEntityException $e) {
                    // If an address ID was provided but the address doesn't exist,
                    // Magento's core will handle the error later.
                }
            }
        }
    }
}
