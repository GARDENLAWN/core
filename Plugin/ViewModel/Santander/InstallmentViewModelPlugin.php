<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\ViewModel\Santander;

use Aurora\Santander\ViewModel\Installment as Subject;
use Magento\Checkout\Helper\Cart;

class InstallmentViewModelPlugin
{
    private Cart $cart;

    public function __construct(Cart $cart)
    {
        $this->cart = $cart;
    }

    /**
     * Zastępuje oryginalną metodę, aby użyć ceny wysyłki brutto.
     *
     * @param Subject $subject
     * @param callable $proceed
     * @return float
     */
    public function aroundGetFinalPrices(Subject $subject, callable $proceed): float
    {
        // Wywołaj oryginalną metodę, aby obliczyć sumę cen produktów
        $finalPrice = $proceed();

        // Odejmij od wyniku niepoprawną cenę wysyłki (netto)
        $shippingPriceNet = $this->cart->getQuote()->getShippingAddress()->getShippingAmount();
        $finalPrice -= $shippingPriceNet;

        // Dodaj poprawną cenę wysyłki (brutto)
        $shippingPriceGross = $this->cart->getQuote()->getShippingAddress()->getShippingInclTax();
        $finalPrice += $shippingPriceGross;

        return (float)$finalPrice;
    }
}
