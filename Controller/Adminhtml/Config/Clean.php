<?php
/**
 * Copyright © GardenLawn. All rights reserved.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Controller\Adminhtml\Config;

use GardenLawn\Core\Model\Config\Cleaner;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;

class Clean extends Action
{
    /** @var RedirectFactory */
    protected $resultRedirectFactory;

    /** @var ManagerInterface */
    protected $messageManager;

    /** @var Cleaner */
    private Cleaner $cleaner;

    public function __construct(
        Context $context,
        RedirectFactory $resultRedirectFactory,
        ManagerInterface $messageManager,
        Cleaner $cleaner
    ) {
        parent::__construct($context);
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->cleaner = $cleaner;
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GardenLawn_Core::run_cleaner');
    }

    public function execute(): Redirect
    {
        try {
            $result = $this->cleaner->cleanRedundantConfig();
            $message = __(
                "Cleanup finished. Found and deleted %1 entries. Full log available in gardenlawn_core_debug.log.",
                $result['deleted_count']
            );
            // Dodanie komunikatu sukcesu do sesji
            $this->messageManager->addSuccessMessage($message);
        } catch (\Exception $e) {
            // Dodanie komunikatu błędu do sesji
            $this->messageManager->addErrorMessage(__('An error occurred during cleanup: %1', $e->getMessage()));
        }

        // Przekierowanie z powrotem do sekcji konfiguracji 'gardenlawn_core'
        $resultRedirect = $this->resultRedirectFactory->create();

        // Używamy 'adminhtml/system_config/edit' (co jest domyślnym działaniem dla '*/*/index'),
        // ale z sekcją i parametrem '_current' aby zachować kontekst i klucz bezpieczeństwa.
        return $resultRedirect->setPath(
            'adminhtml/system_config/edit',
            [
                'section' => 'gardenlawn_core',
                '_current' => true // Zachowuje current store/website scope i klucz bezpieczeństwa
            ]
        );
    }
}
