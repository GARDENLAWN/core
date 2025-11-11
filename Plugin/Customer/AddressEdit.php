<?php

namespace GardenLawn\Core\Plugin\Customer;

use GardenLawn\Core\Block\Customer\AddressEdit as CustomerAddress;
use Magento\Customer\Block\Address\Edit;
use Magento\Framework\View\LayoutInterface;

class AddressEdit
{
    /**
     * @var LayoutInterface
     */
    private LayoutInterface $layout;

    /**
     * Constructor Initialize
     *
     * @param LayoutInterface $layout
     * @return void
     */
    public function __construct(
        LayoutInterface $layout
    ) {
        $this->layout = $layout;
    }

    /**
     * Append gst field
     *
     * @param Edit $edit
     * @param string $result
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetNameBlockHtml(
        Edit   $edit,
        string $result
    ): string {
        $customBlock = $this->layout->createBlock(
            CustomerAddress::class,
            'addresstype'
        );

        return $result . $customBlock->toHtml();
    }
}
