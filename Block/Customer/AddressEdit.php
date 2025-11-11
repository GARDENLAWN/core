<?php
namespace GardenLawn\Core\Block\Customer;

use Magento\Framework\View\Element\Template;
use GardenLawn\Core\Block\Customer\Widget\Addresstype;

class AddressEdit extends Template
{
    /**
     * To html
     *
     * @return string
     */
    protected function _toHtml()
    {
        $gstinWidgetBlock = $this->getLayout()->createBlock(Addresstype::class);
        return $gstinWidgetBlock->toHtml();
    }
}
