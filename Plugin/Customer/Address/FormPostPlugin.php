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

        if ($addressId && in_array($this->companyHelper->getCurrentCustomerGroupId(), $this->companyHelper->getB2bCustomerGroups())) {
            try {
                $address = $this->addressRepository->getById($addressId);
                if ((int) $address->getCustomerId() === $customerId && $address->isDefaultBilling()) {
                    throw new LocalizedException(__('B2B customers cannot change their default billing address.'));
                }
            } catch (NoSuchEntityException $e) {
                // Address not found, proceed without validation (e.g., new address)
            }
        }
    }
}
