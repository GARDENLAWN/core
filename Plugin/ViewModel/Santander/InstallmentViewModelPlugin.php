<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\ViewModel\Santander;

use Aurora\Santander\ViewModel\Installment as Subject;
use GardenLawn\Core\Utils\Logger;
use Magento\Checkout\Model\Session as CheckoutSession;

class InstallmentViewModelPlugin
{
    private CheckoutSession $checkoutSession;

    public function __construct(CheckoutSession $checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Całkowicie zastępuje oryginalną, błędną logikę obliczania ceny.
     * Zwraca pełną kwotę brutto koszyka (grand_total), która jest jedyną poprawną wartością dla symulatora.
     *
     * @param Subject $subject
     * @param callable $proceed Oryginalna metoda (nie będzie wywoływana)
     * @return float
     */
    public function aroundGetFinalPrices(Subject $subject, callable $proceed): float
    {
        try {
            $quote = $this->checkoutSession->getQuote();

            // getGrandTotal() zwraca ostateczną kwotę brutto do zapłaty, uwzględniającą produkty, wysyłkę, podatki i zniżki.
            // Jest to jedyna prawidłowa wartość dla symulatora.
            $grandTotal = $quote->getGrandTotal();
Logger::writeLog('suma: '.$grandTotal);
            return (float)$grandTotal;

        } catch (\Exception $e) {
            // W razie błędu (np. brak sesji), pozwól działać oryginalnej metodzie jako fallback.
            // To zapobiegnie awarii, chociaż wynik może być niepoprawny.
            return $proceed();
        }
    }
}
