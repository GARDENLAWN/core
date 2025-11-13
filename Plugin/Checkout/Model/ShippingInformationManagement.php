<?php
namespace GardenLawn\Core\Plugin\Checkout\Model;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Quote\Model\QuoteRepository;
use Psr\Log\LoggerInterface;

class ShippingInformationManagement{

    protected QuoteRepository $quoteRepository;
    protected LoggerInterface $logger;

    public function __construct(QuoteRepository $quoteRepository,  LoggerInterface $logger) {
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
    }

    public function beforeSaveAddressInformation(
        \Magento\Checkout\Model\ShippingInformationManagement $subject,
                                                              $cartId,
        ShippingInformationInterface $addressInformation
    ): void
    {
        $shippingAddress = $addressInformation->getShippingAddress();
        $extensionAttributes = $shippingAddress->getExtensionAttributes();

        if ($extensionAttributes) {
            if ($extensionAttributes->getAddresstype()) {
                $addresstype = $extensionAttributes->getAddresstype();
                $shippingAddress->setaddresstype($addresstype);
            } else {
                $shippingAddress->setAddresstype('');
            }
        }
    }
}
