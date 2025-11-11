<?php

namespace GardenLawn\Core\Helper;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Variable\Model\Variable;

class Data extends AbstractHelper
{

    protected Variable $variable;

    public function __construct(
        Context  $context,
        Variable $variable
    )
    {
        $this->variable = $variable;
        parent::__construct($context);
    }

    public function getCustomVarible($code)
    {
        $variableData = $this->variable->loadByCode($code, 'base'); // Here first parameter is custom-variable-code and second one is store-code
        return $variableData->getPlainValue();
    }

    public function isCustomerB2B()
    {
        $customerGroupsIdsB2b = $this->getCustomVarible('customer_groups_ids_b2b');
        $objectManager = ObjectManager::getInstance();
        $customerSession = $objectManager->get(Session::class);
        $customerGroupId = $customerSession->getCustomerGroupId();

        return str_contains($customerGroupsIdsB2b ?? '', '[' . $customerGroupId . ']');
    }
}
