<?php
namespace GardenLawn\Core\Block\Customer\Widget;

use Magento\Customer\Model\AddressFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Addresstype extends Template
{
    /**
     * @var AddressFactory
     */
    protected AddressFactory $_addressFactory;

    /**
     * Constructor Initialize
     *
     * @param Context $context
     * @param AddressFactory $addressFactory
     * @param array $data
     * @return void
     */
    public function __construct(
        Context $context,
        AddressFactory $addressFactory,
        array $data = []
    ) {
        $this->_addressFactory = $addressFactory;
        parent::__construct($context, $data);
    }

    /**
     * Set custom template
     *
     * @return void
     */
    public function _construct(): void
    {
        parent::_construct();

        // default template location
        $this->setTemplate('GardenLawn_Core::widget/addresstype.phtml');
    }

    /**
     * Return gstin number from address
     *
     * @return string|null
     */
    public function getValue(): ?string
    {
        $addressId = $this->getRequest()->getParam('id');
        if ($addressId) {
            $addressCollection = $this->_addressFactory->create()->load($addressId);
            $gstin = $addressCollection->getAddresstype();
            if ($gstin) {
                return $gstin;
            }
        }
        return null;
    }
}
