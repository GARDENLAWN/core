<?php
declare(strict_types=1);

namespace GardenLawn\Core\Helper;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Variable\Model\Variable;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    public const XML_PATH_B2B_CUSTOMER_GROUPS = 'gardenlawn_core/b2b/customer_groups';

    protected Variable $variable;
    protected Session $customerSession;

    public function __construct(
        Context $context,
        Variable $variable,
        Session $customerSession
    ) {
        $this->variable = $variable;
        $this->customerSession = $customerSession;
        parent::__construct($context);
    }

    /**
     * Get custom variable value.
     *
     * @param string $code
     * @return string|null
     */
    public function getCustomVarible(string $code): ?string
    {
        $variableData = $this->variable->loadByCode($code, 'base');
        return $variableData->getPlainValue();
    }

    /**
     * Check if current customer is a B2B customer.
     *
     * @return bool
     */
    public function isCustomerB2B(): bool
    {
        $b2bCustomerGroupIds = $this->scopeConfig->getValue(
            self::XML_PATH_B2B_CUSTOMER_GROUPS,
            ScopeInterface::SCOPE_STORE
        );

        if (!$b2bCustomerGroupIds) {
            return false;
        }

        $b2bCustomerGroupIds = explode(',', $b2bCustomerGroupIds);
        $currentCustomerGroupId = $this->customerSession->getCustomerGroupId();

        return in_array((string)$currentCustomerGroupId, $b2bCustomerGroupIds);
    }
}
