<?php

namespace GardenLawn\Core\Controller\Cart;

use GardenLawn\Shippingquote\Model\GridFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;

class ShippingQuoteRequest extends Action
{
    protected GridFactory $gridFactory;
    protected Session $customerSession;

    public function __construct(
        Context     $context,
        GridFactory $gridFactory,
        Session     $customerSession
    )
    {
        $this->gridFactory = $gridFactory;
        $this->customerSession = $customerSession;
        return parent::__construct($context);
    }

    public function execute(): ResultInterface|ResponseInterface|Redirect
    {
        $shippingQuote = $this->gridFactory->create();
        $shippingQuote->setCustomerId($this->customerSession->getCustomerId());
        $shippingQuote->setCreatedAt(time());
        $shippingQuote->setPublishDate(time());
        $shippingQuote->setIsActive(0);

        $address = $this->customerSession->getCustomer()->getDefaultShippingAddress();

        if ($address) {
            $addressValue =
                '|postcode:"' . $address->getPostcode() .
                '",city:"' . $address->getCity() .
                //  '",street:"' . $address->getStreet() .
                '"|';

            $shippingQuote->setAddress($addressValue);
        } else {
            $shippingQuote->setAddress('||');
        }

        $objectManager = ObjectManager::getInstance();
        $cart = $objectManager->get('\Magento\Checkout\Model\Cart');

        $items = $cart->getQuote()->getAllVisibleItems();

        $products = '';
        foreach ($items as $item) {
            $products .= '|sku:"' . $item->getSku() . '",qty:"' . $item->getQty() . '"|';
        }

        $shippingQuote->setProducts($products);

        $saveData = $shippingQuote->save();
        if ($saveData) {
            $this->messageManager->addSuccess(__('A request for a shipping quote has been sent. After the quote, you will receive an email with information'));
        }

        return $this->resultRedirectFactory->create()->setUrl($this->_redirect->getRefererUrl());
    }
}
