<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Payment;

use Aurora\Santander\ViewModel\Rates;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Customer\Model\Context as CustomerContext;

class SantanderRatesViewModelPlugin
{
    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var HttpContext
     */
    private HttpContext $httpContext;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param HttpContext $httpContext
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        HttpContext $httpContext
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->httpContext = $httpContext;
    }

    /**
     * Filter out 0% installment options for B2B customer groups
     *
     * @param Rates $subject
     * @param array $result
     * @return array
     */
    public function afterGetAvailableInstallmentOptions(Rates $subject, array $result): array
    {
        if (empty($result)) {
            return $result;
        }

        $b2bGroups = $this->scopeConfig->getValue(
            'gardenlawn_core/b2b/customer_groups',
            ScopeInterface::SCOPE_STORE
        );

        if (empty($b2bGroups)) {
            return $result;
        }

        $b2bGroupsArray = explode(',', $b2bGroups);

        // Use HttpContext to get customer group ID (works with FPC)
        $customerGroupId = $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);

        // If group ID is not set in context (e.g. not logged in), it usually returns 0 (NOT_LOGGED_IN)
        // However, when logging out, sometimes the context might still hold the old value or 0.
        // If it is null, we assume NOT_LOGGED_IN (0).
        if ($customerGroupId === null) {
            $customerGroupId = 0;
        }

        // Ensure we are comparing strings
        if (in_array((string)$customerGroupId, $b2bGroupsArray)) {
            foreach ($result as $key => $option) {
                $percent = isset($option['percent']) ? (float)str_replace(',', '.', (string)$option['percent']) : 0.0;
                if ($percent == 0) {
                    unset($result[$key]);
                }
            }
        }

        return $result;
    }
}
