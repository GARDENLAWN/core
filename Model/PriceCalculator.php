<?php
declare(strict_types=1);

namespace GardenLawn\Core\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Serialize\Serializer\Json;

class PriceCalculator
{
    private const XML_PATH_PRICE_FACTOR = 'gardenlawn_core/price_calculation/factor';
    private const XML_PATH_TAX_RATE = 'gardenlawn_core/price_calculation/tax_rate';
    private const XML_PATH_ROUNDING_RULES = 'gardenlawn_core/price_calculation/rounding_rules';

    /** @var ScopeConfigInterface */
    private $scopeConfig;
    /** @var Json */
    private $jsonSerializer;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Json $jsonSerializer
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->jsonSerializer = $jsonSerializer;
    }

    public function calculateFinalPrice(float $dealerPricePln): array
    {
        $factor = (float)$this->scopeConfig->getValue(self::XML_PATH_PRICE_FACTOR, ScopeInterface::SCOPE_STORE);
        $taxRate = (float)$this->scopeConfig->getValue(self::XML_PATH_TAX_RATE, ScopeInterface::SCOPE_STORE);
        $roundingRules = $this->getRoundingRules();

        $netBase = $dealerPricePln * $factor;
        $grossBase = $netBase * (1 + $taxRate / 100);

        $grossRounded = $this->applyRounding($grossBase, $roundingRules);

        $netFinal = $grossRounded / (1 + $taxRate / 100);
        $netFinal = round($netFinal, 4);

        return [
            'factor' => $factor,
            'tax_rate' => $taxRate,
            'net_base' => $netBase,
            'gross_base' => $grossBase,
            'gross_rounded' => $grossRounded,
            'net_final' => $netFinal
        ];
    }

    private function getRoundingRules(): array
    {
        $json = $this->scopeConfig->getValue(self::XML_PATH_ROUNDING_RULES, ScopeInterface::SCOPE_STORE);
        if (!$json) {
            return [];
        }
        try {
            $rules = $this->jsonSerializer->unserialize($json);
            if (!is_array($rules)) {
                return [];
            }
            usort($rules, function ($a, $b) {
                return $a['price_limit'] <=> $b['price_limit'];
            });
            return $rules;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function applyRounding(float $price, array $rules): float
    {
        foreach ($rules as $rule) {
            if ($price <= (float)$rule['price_limit']) {
                $target = (float)$rule['rounding_target'];
                $target = $target - floor($target);
                if ($target < 0) $target = 0.99;

                $method = $rule['rounding_method'] ?? 'nearest';
                $floor = floor($price);

                $lower = $floor - 1 + $target;
                $middle = $floor + $target;
                $upper = $floor + 1 + $target;

                if ($method === 'up') {
                    if ($lower >= $price) return $lower;
                    if ($middle >= $price) return $middle;
                    return $upper;
                } elseif ($method === 'down') {
                    if ($upper <= $price) return $upper;
                    if ($middle <= $price) return $middle;
                    return $lower;
                } else {
                    // Nearest
                    $candidates = [$lower, $middle, $upper];
                    $best = $middle;
                    $minDiff = abs($price - $middle);

                    foreach ($candidates as $cand) {
                        $diff = abs($price - $cand);
                        if ($diff < $minDiff) {
                            $minDiff = $diff;
                            $best = $cand;
                        }
                    }
                    return max(0, $best);
                }
            }
        }
        return round($price, 2);
    }
}
