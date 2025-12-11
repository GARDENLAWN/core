<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Model\Santander;

use Aurora\Santander\Model\Santander as Subject;
use Aurora\Santander\ViewModel\Rates as RatesViewModel;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\Data\CartInterface;

class AvailabilityPlugin
{
    private RatesViewModel $ratesViewModel;
    private CheckoutSession $checkoutSession;

    public function __construct(
        RatesViewModel $ratesViewModel,
        CheckoutSession $checkoutSession
    ) {
        $this->ratesViewModel = $ratesViewModel;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Sprawdza dostępność metody płatności Santander na podstawie dostępnych opcji ratalnych.
     *
     * @param Subject $subject
     * @param bool $result
     * @return bool
     */
    public function afterIsAvailable(Subject $subject, bool $result): bool
    {
        // Jeśli metoda płatności jest już niedostępna z innych powodów, nie zmieniamy tego
        if (!$result) {
            return false;
        }

        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote || !$quote->getId()) {
                return false; // Brak aktywnego koszyka
            }

            $availableOptions = $this->ratesViewModel->getAvailableInstallmentOptions($quote->getAllVisibleItems());

            // Metoda płatności jest dostępna tylko, jeśli są jakieś opcje ratalne
            return !empty($availableOptions);

        } catch (\Exception $e) {
            // W razie błędu, logujemy go i zwracamy false, aby nie wyświetlać metody płatności
            // Możesz dodać tutaj logowanie błędu, np. $this->logger->error($e->getMessage());
            return false;
        }
    }
}
