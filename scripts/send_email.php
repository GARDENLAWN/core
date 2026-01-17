<?php
use Magento\Framework\App\Bootstrap;

require __DIR__ . '/../../../../../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();

$state = $obj->get('Magento\Framework\App\State');
try {
    $state->setAreaCode('adminhtml'); // or frontend
} catch (\Exception $e) {}

// Arguments: 1=To, 2=Subject, 3=Body (HTML)
if ($argc < 4) {
    echo "Usage: php send_email.php <to> <subject> <body>\n";
    exit(1);
}

$to = $argv[1];
$subject = $argv[2];
$bodyHtml = $argv[3];

try {
    /** @var \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder */
    $transportBuilder = $obj->get('Magento\Framework\Mail\Template\TransportBuilder');

    /** @var \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig */
    $scopeConfig = $obj->get('Magento\Framework\App\Config\ScopeConfigInterface');

    // Get sender info from config
    $senderName = $scopeConfig->getValue('trans_email/ident_general/name') ?: 'System Monitor';
    $senderEmail = $scopeConfig->getValue('trans_email/ident_general/email') ?: 'no-reply@gardenlawn.pl';

    $transport = $transportBuilder
        ->setTemplateIdentifier('design_email_header_template') // Use a default template or create one
        ->setTemplateOptions([
            'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
            'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID,
        ])
        ->setTemplateVars(['content' => $bodyHtml]) // We might need a custom template that accepts 'content' variable
        ->setFromByScope(['name' => $senderName, 'email' => $senderEmail])
        ->addTo($to)
        ->getTransport();

    // Since we are passing full HTML body, we might want to bypass template logic and set body directly
    // But TransportBuilder is tied to templates.
    // Alternative: Use Laminas\Mail\Message directly if available, or a simple Transport.

    // Let's try a simpler approach for raw HTML content without Magento templates overhead
    /** @var \Magento\Framework\Mail\TransportInterfaceFactory $transportFactory */
    $transportFactory = $obj->get('Magento\Framework\Mail\TransportInterfaceFactory');

    $message = new \Laminas\Mail\Message();
    $message->setSubject($subject);
    $message->setFrom($senderEmail, $senderName);
    $message->addTo($to);

    // Create HTML body
    $bodyPart = new \Laminas\Mime\Message();
    $htmlPart = new \Laminas\Mime\Part($bodyHtml);
    $htmlPart->type = "text/html";
    $htmlPart->charset = "utf-8";
    $bodyPart->setParts([$htmlPart]);

    $message->setBody($bodyPart);

    $transport = $transportFactory->create(['message' => $message]);
    $transport->sendMessage();

    echo "Mail sent successfully via Magento Transport.\n";

} catch (\Exception $e) {
    echo "Error sending mail: " . $e->getMessage() . "\n";
    exit(1);
}
