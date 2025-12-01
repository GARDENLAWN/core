<?php
namespace GardenLawn\Core\Observer;

use Exception;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Customer\Api\CustomerRepositoryInterface;

class CopyTaxVat implements ObserverInterface
{
    protected CustomerRepositoryInterface $customerRepository;

    public function __construct(CustomerRepositoryInterface $customerRepository) {
        $this->customerRepository = $customerRepository;
    }

    public function execute(Observer $observer): void
    {
        $address = $observer->getEvent()->getCustomerAddress();

        if ($address->getCustomerId()) {
            try {
                $customer = $this->customerRepository->getById($address->getCustomerId());
                if ($customer->getTaxvat()) {
                    $address->setVatId($customer->getTaxvat());
                }
            } catch (Exception) {
            }
        }
    }
}
