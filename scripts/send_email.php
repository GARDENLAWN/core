<?php
use Magento\Framework\App\Bootstrap;

// Try to find bootstrap.php in common locations
$bootstrapPaths = [
    __DIR__ . '/../../../../../app/bootstrap.php', // app/code/Vendor/Module/scripts/
    __DIR__ . '/../../../../app/bootstrap.php',    // vendor/vendor/module/scripts/
];

$bootstrapLoaded = false;
foreach ($bootstrapPaths as $path) {
    if (file_exists($path)) {
        require $path;
        $bootstrapLoaded = true;
        break;
    }
}

if (!$bootstrapLoaded) {
    echo "Error: Could not find app/bootstrap.php\n";
    exit(1);
}

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
    /** @var \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig */
    $scopeConfig = $obj->get('Magento\Framework\App\Config\ScopeConfigInterface');

    // Get sender info from config
    $senderName = $scopeConfig->getValue('trans_email/ident_general/name') ?: 'System Monitor';
    $senderEmail = $scopeConfig->getValue('trans_email/ident_general/email') ?: 'no-reply@gardenlawn.pl';

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
