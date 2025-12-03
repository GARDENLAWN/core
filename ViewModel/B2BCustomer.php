<?php
declare(strict_types=1);

namespace GardenLawn\Core\ViewModel;

use GardenLawn\Core\Helper\Data;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class B2BCustomer implements ArgumentInterface
{
    private Data $data;

    public function __construct(
        Data $data
    ) {
        $this->data = $data;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function isCustomerB2B(): bool
    {
        return $this->data->isCustomerB2B();
    }
}
