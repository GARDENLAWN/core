<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Payment;

use Aurora\Santander\ViewModel\Installment;
use Aurora\Santander\ViewModel\Rates;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Customer\Model\Context as CustomerContext;

class SantanderInstallmentViewModelPlugin
{
    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var Rates
     */
    private Rates $ratesViewModel;

    /**
     * @var HttpContext
     */
    private HttpContext $httpContext;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Rates $ratesViewModel
     * @param HttpContext $httpContext
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Rates $ratesViewModel,
        HttpContext $httpContext
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->ratesViewModel = $ratesViewModel;
        $this->httpContext = $httpContext;
    }

    /**
     * Adjust calculated installment for B2B customers (remove 0% offers)
     *
     * @param Installment $subject
     * @param void $result
     * @param Product $product
     * @return void
     */
    public function afterCalculateInstallment(Installment $subject, $result, $product)
    {
        // Check if B2B
        $b2bGroups = $this->scopeConfig->getValue(
            'gardenlawn_core/b2b/customer_groups',
            ScopeInterface::SCOPE_STORE
        );

        if (empty($b2bGroups)) {
            return;
        }

        $b2bGroupsArray = explode(',', $b2bGroups);

        // Use HttpContext to get customer group ID (works with FPC)
        $customerGroupId = $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);

        if ($customerGroupId === null || !in_array((string)$customerGroupId, $b2bGroupsArray)) {
            return;
        }

        // If current calculated installment is 0%, try to find another one
        if ($subject->percent == 0) {
            // Get available options (filtered by our other plugin)
            $options = $this->ratesViewModel->getAvailableInstallmentOptions([$product]);

            if (!empty($options)) {
                // Take the first available option (which should be > 0%)
                $firstOption = reset($options);
                $subject->qty = (int)$firstOption['qty'];
                $subject->percent = (float)str_replace(',', '.', (string)$firstOption['percent']);
            } else {
                // No options available for B2B, hide the block
                $subject->qty = null;
                $subject->percent = null;
            }
        }
    }
}
