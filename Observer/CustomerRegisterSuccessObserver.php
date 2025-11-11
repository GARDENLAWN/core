<?php
declare(strict_types=1);

namespace GardenLawn\Core\Observer;

use Exception;
use GardenLawn\Company\Api\Data\CeidgService;
use GardenLawn\Company\Model\Grid;
use GardenLawn\Core\Helper\Data;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;

class CustomerRegisterSuccessObserver implements ObserverInterface
{
    protected const string COUNTRY_CODE = 'PL';

    protected RequestInterface $request;

    protected ManagerInterface $messageManager;
    protected ResponseFactory $responseFactory;
    protected UrlInterface $url;
    protected Data $dataHelper;
    protected AddressInterfaceFactory $addressDataFactory;
    protected AddressRepositoryInterface $addressRepository;
    protected CustomerRepositoryInterface $customerRepository;

    public function __construct(
        RequestInterface            $request,
        Data                        $dataHelper,
        ManagerInterface            $messageManager,
        ResponseFactory             $responseFactory,
        UrlInterface                $url,
        AddressRepositoryInterface  $addressRepository,
        AddressInterfaceFactory     $addressDataFactory,
        CustomerRepositoryInterface $customerRepository
    )
    {
        $this->request = $request;
        $this->dataHelper = $dataHelper;
        $this->messageManager = $messageManager;
        $this->responseFactory = $responseFactory;
        $this->url = $url;
        $this->addressRepository = $addressRepository;
        $this->addressDataFactory = $addressDataFactory;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        $customer = $observer->getEvent()->getCustomer();

        try {
            $this->createCustomerAddresses($customer);
        } catch
        (Exception $e) {
            $this->createEmptyAddresses($customer);
        }
    }

    /**
     * @throws LocalizedException
     */
    public function createCustomerAddresses($customer, ?Grid $company = null): void
    {
        $data = CeidgService::getDataByNip($customer->getTaxvat());

        if (property_exists($data, 'firmy')) {
            $owner = $data->firmy[0]->wlasciciel;

            $customer->setFirstname($owner->imie);
            $customer->setLastname($owner->nazwisko);
            $this->customerRepository->save($customer);

            $addressCompany = $data->firmy[0]->adresDzialalnosci;
            $region = strtolower($addressCompany->wojewodztwo);

            $objectManager = ObjectManager::getInstance();
            $regionInterface = $objectManager->get('Magento\Customer\Api\Data\RegionInterface');
            $regionInterface->setRegion($region);

            $addressBilling = $this->addressDataFactory->create();
            $addressBilling
                ->setCustomAttribute('addresstype', 'billing')
                ->setFirstname($customer->getFirstname())
                ->setLastname($customer->getLastname())
                ->setCountryId($this::COUNTRY_CODE)
                ->setRegion($regionInterface)
                ->setVatId($customer->getTaxvat())
                ->setTelephone($company?->getPhone() ?? '')
                ->setCompany($data->firmy[0]->nazwa)
                ->setCity($addressCompany->miasto)
                ->setPostcode($addressCompany->kod)
                ->setCustomerId($customer->getId())
                ->setStreet(array(($addressCompany->ulica . ' ' ?? ($addressCompany->miasto . " ")) . $addressCompany->budynek))
                ->setIsDefaultBilling(true)
                ->setIsDefaultShipping(false);

            $this->addressRepository->save($addressBilling);

            $addressShipping = $this->addressDataFactory->create();
            $addressShipping
                ->setCustomAttribute('addresstype', 'shipping')
                ->setFirstname($customer->getFirstname())
                ->setLastname($customer->getLastname())
                ->setCountryId($this::COUNTRY_CODE)
                ->setRegion($regionInterface)
                ->setVatId($customer->getTaxvat())
                ->setTelephone($company?->getPhone() ?? '')
                ->setCompany($data->firmy[0]->nazwa)
                ->setCity($addressCompany->miasto)
                ->setPostcode($addressCompany->kod)
                ->setCustomerId($customer->getId())
                ->setStreet(array(($addressCompany->ulica . ' ' ?? ($addressCompany->miasto . " ")) . $addressCompany->budynek))
                ->setIsDefaultBilling(false)
                ->setIsDefaultShipping(true);

            $this->addressRepository->save($addressShipping);
        } else {
            $this->createEmptyAddresses($customer);
        }
    }

    public function createEmptyAddresses($customer): void
    {
        //Nie tworzymy pustych adresów
        /*
        $customer = $observer->getEvent()->getCustomer();
        $nip = $customer->getTaxvat();

        $addressBilling = $this->addressDataFactory->create();
        $addressBilling
            ->setCustomAttribute('custom_address_attribute', 'billing')
            ->setFirstname($customer->getFirstname())
            ->setLastname($customer->getLastname())
            ->setCountryId($this::COUNTRY_CODE)
            ->setVatId($nip)
            ->setTelephone('')
            ->setCompany('')
            ->setCity('')
            ->setPostcode('')
            ->setCustomerId($customer->getId())
            ->setStreet(array(''))
            ->setIsDefaultBilling(true)
            ->setIsDefaultShipping(false);

        $this->addressRepository->save($addressBilling);

        $addressShipping = $this->addressDataFactory->create();
        $addressShipping
            ->setCustomAttribute('custom_address_attribute', 'shipping')
            ->setFirstname($customer->getFirstname())
            ->setLastname($customer->getLastname())
            ->setCountryId($this::COUNTRY_CODE)
            ->setVatId($nip)
            ->setTelephone('')
            ->setCompany('')
            ->setCity('')
            ->setPostcode('')
            ->setCustomerId($customer->getId())
            ->setStreet(array(''))
            ->setIsDefaultBilling(false)
            ->setIsDefaultShipping(true);

        $this->addressRepository->save($addressShipping);
        */
    }
}
