<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\ViewModel\Santander;

use Aurora\Santander\ViewModel\Rates as Subject;
use Magento\Checkout\Model\Session as CheckoutSession;

class RatesPlugin
{
    private CheckoutSession $checkoutSession;

    public function __construct(CheckoutSession $checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Przechwytuje metodę getShopId, aby zwrócić numer sklepu wybrany w checkout.
     *
     * @param Subject $subject
     * @param callable $proceed
     * @param mixed ...$args
     * @return int
     */
    public function aroundGetShopId(Subject $subject, callable $proceed, ...$args): int
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            $payment = $quote->getPayment();

            // Sprawdzamy, czy metoda płatności to Santander, aby uniknąć problemów
            if ($payment->getMethod() === 'eraty_santander') {
                $shopNumber = $payment->getAdditionalInformation('santander_shop_number');

                if ($shopNumber) {
                    return (int)$shopNumber;
                }
            }
        } catch (\Exception $e) {
            // W razie błędu (np. brak sesji), pozwól działać oryginalnej metodzie
        }

        // Jeśli nic nie znaleziono w sesji lub inna metoda płatności, uruchom oryginalną logikę
        return $proceed(...$args);
    }
}
