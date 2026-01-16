<?php
namespace GardenLawn\Core\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class EmailSender extends AbstractHelper
{
    protected $transportBuilder;
    protected $inlineTranslation;
    protected $storeManager;
    protected $logger;

    public function __construct(
        Context $context,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    public function sendTokenRefreshError($errorMessage)
    {
        $recipientEmail = $this->scopeConfig->getValue('trans_eu/general/notification_email');

        if (!$recipientEmail) {
            $this->logger->warning('Trans.eu: Notification email not configured. Skipping alert.');
            return;
        }

        try {
            $this->inlineTranslation->suspend();

            $sender = [
                'name' => 'Trans.eu Integration',
                'email' => $this->scopeConfig->getValue('trans_email/ident_general/email') ?? 'noreply@example.com'
            ];

            $transport = $this->transportBuilder
                ->setTemplateIdentifier('trans_eu_token_error')
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_ADMINHTML,
                    'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID,
                ])
                ->setTemplateVars([
                    'error_message' => $errorMessage,
                    'date' => date('Y-m-d H:i:s')
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
        }
    }
}
