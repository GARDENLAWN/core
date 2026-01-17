<?php
namespace GardenLawn\Core\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Psr\Log\LoggerInterface;

class EmailSender extends AbstractHelper
{
    protected $transportBuilder;
    protected $inlineTranslation;
    protected $storeManager;
    protected $logger;
    protected $timezone;

    // Fallback email if config is missing
    const FALLBACK_EMAIL = 'marcin.piechota@gardenlawn.pl';

    public function __construct(
        Context $context,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        TimezoneInterface $timezone
    ) {
        parent::__construct($context);
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->timezone = $timezone;
    }

    public function sendTokenRefreshError($errorMessage)
    {
        $recipientEmail = $this->scopeConfig->getValue('trans_eu/general/notification_email');

        if (!$recipientEmail) {
            $recipientEmail = self::FALLBACK_EMAIL;
            $this->logger->warning('Trans.eu: Notification email not configured. Using fallback: ' . $recipientEmail);
        }

        try {
            $this->inlineTranslation->suspend();

            $sender = [
                'name' => 'Trans.eu Integration',
                'email' => $this->scopeConfig->getValue('trans_email/ident_general/email') ?? 'marcin.piechota@gardenlawn.pl'
            ];

            // Get current date in store timezone (or specific locale if needed)
            $date = $this->timezone->date()->format('Y-m-d H:i:s');

            $transport = $this->transportBuilder
                ->setTemplateIdentifier('trans_eu_token_error')
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_ADMINHTML,
                    'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID,
                ])
                ->setTemplateVars([
                    'error_message' => $errorMessage,
                    'date' => $date
                ])
                ->setFrom($sender)
                ->addTo($recipientEmail)
                ->getTransport();

            $transport->sendMessage();
            $this->inlineTranslation->resume();

            $this->logger->info('Trans.eu: Error notification sent to ' . $recipientEmail);

        } catch (\Exception $e) {
            $this->logger->error('Trans.eu: Failed to send error notification: ' . $e->getMessage());
            $this->inlineTranslation->resume();

            // Ostatnia deska ratunku: mail() PHP
            if (function_exists('mail')) {
                mail($recipientEmail, 'CRITICAL: Trans.eu Token Error', $errorMessage . "\nDate: " . $date);
            }
        }
    }
}
