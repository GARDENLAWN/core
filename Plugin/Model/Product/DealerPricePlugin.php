<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Model\Product;

use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class DealerPricePlugin
{
    private const XML_PATH_DEALER_GROUPS = 'gardenlawn_core/b2b/customer_groups';

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param CustomerSession $customerSession
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        CustomerSession $customerSession,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->customerSession = $customerSession;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Apply dealer price if the customer belongs to a configured B2B group.
     * This affects the base price calculation.
     *
     * @param Product $subject
     * @param float $result
     * @return float
     */
    public function afterGetPrice(Product $subject, $result)
    {
        return $this->getDealerPrice($subject, (float)$result);
    }

    /**
     * Apply dealer price to the final price calculation.
     * This ensures the price is correct in cart, checkout, and product views.
     *
     * @param Product $subject
     * @param float $result
     * @return float
     */
    public function afterGetFinalPrice(Product $subject, $result)
    {
        return $this->getDealerPrice($subject, (float)$result);
    }

    /**
     * Helper method to calculate dealer price.
     *
     * @param Product $subject
     * @param float $currentPrice
     * @return float
     */
    private function getDealerPrice(Product $subject, float $currentPrice): float
    {
        // Avoid infinite recursion or unnecessary checks if price is 0 (e.g. bundle items sometimes)
        // But dealer price might be 0? Unlikely.

        $customerGroupId = (int) $this->customerSession->getCustomerGroupId();
        $dealerGroups = $this->getDealerGroups();

        if (in_array($customerGroupId, $dealerGroups, true)) {
            $dealerPrice = $subject->getData('dealer_price');
            if ($dealerPrice !== null && (float)$dealerPrice > 0) {
                // Return the lower of the two prices? Or always dealer price?
                // Usually dealer price is fixed, so we return it directly.
                // If you want to ensure dealer price is only used if it's lower than regular price:
                // return min((float)$dealerPrice, $currentPrice);

                // Assuming dealer price overrides everything:
                return (float)$dealerPrice;
            }
        }

        return $currentPrice;
    }

    /**
     * Get configured B2B customer groups.
     *
     * @return int[]
     */
    private function getDealerGroups(): array
    {
        $groups = $this->scopeConfig->getValue(
            self::XML_PATH_DEALER_GROUPS,
            ScopeInterface::SCOPE_STORE
        );

        if (empty($groups)) {
            return [];
        }

        return array_map('intval', explode(',', $groups));
    }
}
