<?php
use Magento\Framework\App\Bootstrap;
use Magento\Framework\Component\ComponentRegistrar;

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
    $state->setAreaCode('frontend');
} catch (\Exception $e) {}

// Arguments: 1=To, 2=Subject, 3=Body (HTML)
if ($argc < 4) {
    echo "Usage: php send_email.php <to> <subject> <body>\n";
    exit(1);
}

$toEmail = $argv[1];
$subject = $argv[2];
$bodyHtml = $argv[3];

// Load Header and Footer templates dynamically using ComponentRegistrar
$registrar = new ComponentRegistrar();
$themePath = $registrar->getPath(ComponentRegistrar::THEME, 'adminhtml/GardenLawn/Admintheme');

$headerContent = '';
$footerContent = '';

if ($themePath) {
    $headerFile = $themePath . '/Magento_Email/email/header.html';
    $footerFile = $themePath . '/Magento_Email/email/footer.html';

    if (file_exists($headerFile)) {
        $headerContent = file_get_contents($headerFile);
    }
    if (file_exists($footerFile)) {
        $footerContent = file_get_contents($footerFile);
    }
} else {
    $fallbackPath = BP . '/app/design/adminhtml/GardenLawn/Admintheme/Magento_Email/email';
    if (file_exists($fallbackPath . '/header.html')) {
        $headerContent = file_get_contents($fallbackPath . '/header.html');
    }
    if (file_exists($fallbackPath . '/footer.html')) {
        $footerContent = file_get_contents($fallbackPath . '/footer.html');
    }
}

// Wrap body with header and footer
$fullBodyHtml = $headerContent . $bodyHtml . $footerContent;

if (empty($headerContent) && strpos($bodyHtml, '<html') === false) {
    $fullBodyHtml = "<html><body>" . $bodyHtml . "</body></html>";
}

try {
    /** @var \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig */
    $scopeConfig = $obj->get('Magento\Framework\App\Config\ScopeConfigInterface');

    // Get sender info from config
    $senderName = $scopeConfig->getValue('trans_email/ident_general/name') ?: 'System Monitor';
    $senderEmail = $scopeConfig->getValue('trans_email/ident_general/email') ?: 'no-reply@gardenlawn.pl';

    /** @var \Magento\Store\Model\StoreManagerInterface $storeManager */
    $storeManager = $obj->get('Magento\Store\Model\StoreManagerInterface');

    try {
        $store = $storeManager->getStore('gardenlawn');
    } catch (\Exception $e) {
        $store = $storeManager->getStore();
    }
    $storeId = $store->getId();

    /** @var \Magento\Store\Model\App\Emulation $emulation */
    $emulation = $obj->get('Magento\Store\Model\App\Emulation');

    $emulation->startEnvironmentEmulation($storeId, \Magento\Framework\App\Area::AREA_FRONTEND, true);

    try {
        // Use Email Template Model instead of raw Filter
        /** @var \Magento\Email\Model\Template $emailTemplate */
        $emailTemplate = $obj->create('Magento\Email\Model\Template');

        $emailTemplate->setTemplateText($fullBodyHtml);
        $emailTemplate->setDesignConfig([
            'area' => 'frontend',
            'store' => $storeId
        ]);

        $emailTemplate->setVariables([
            'store' => $store,
            'logo_url' => $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'logo/default/' . $scopeConfig->getValue('design/header/logo_src', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId),
            'logo_alt' => $scopeConfig->getValue('design/header/logo_alt', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId),
            'date' => date('Y-m-d H:i:s'),
            'base_url' => $store->getBaseUrl()
        ]);

        $processedHtml = $emailTemplate->getProcessedTemplate();

    } catch (\Exception $e) {
        echo "Warning: Template processing failed: " . $e->getMessage() . ". Using raw HTML.\n";
        $processedHtml = $fullBodyHtml;
    } finally {
        $emulation->stopEnvironmentEmulation();
    }

    /** @var \Magento\Framework\Mail\TransportInterfaceFactory $transportFactory */
    $transportFactory = $obj->get('Magento\Framework\Mail\TransportInterfaceFactory');

    // 1. Create MIME Part using Factory
    /** @var \Magento\Framework\Mail\MimePartInterfaceFactory $mimePartFactory */
    $mimePartFactory = $obj->get('Magento\Framework\Mail\MimePartInterfaceFactory');

    $mimePart = $mimePartFactory->create([
        'content' => $processedHtml,
        'type' => 'text/html',
        'charset' => 'utf-8'
    ]);

    // 2. Create MIME Message using Factory
    /** @var \Magento\Framework\Mail\MimeMessageInterfaceFactory $mimeMessageFactory */
    $mimeMessageFactory = $obj->get('Magento\Framework\Mail\MimeMessageInterfaceFactory');

    $mimeMessage = $mimeMessageFactory->create(['parts' => [$mimePart]]);

    // 3. Create Address Objects
    /** @var \Magento\Framework\Mail\AddressFactory $addressFactory */
    $addressFactory = $obj->get('Magento\Framework\Mail\AddressFactory');

    $senderAddress = $addressFactory->create([
        'email' => $senderEmail,
        'name' => $senderName
    ]);

    $toAddress = $addressFactory->create([
        'email' => $toEmail,
        'name' => $toEmail
    ]);

    // 4. Create Email Message
    /** @var \Magento\Framework\Mail\EmailMessageInterfaceFactory $emailMessageFactory */
    $emailMessageFactory = $obj->get('Magento\Framework\Mail\EmailMessageInterfaceFactory');

    $emailMessage = $emailMessageFactory->create([
        'body' => $mimeMessage,
        'subject' => $subject,
        'sender' => $senderAddress,
        'to' => [$toAddress]
    ]);

    $transport = $transportFactory->create(['message' => $emailMessage]);
    $transport->sendMessage();

    echo "Mail sent successfully via Magento Transport.\n";

} catch (\Exception $e) {
    echo "Error sending mail: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
