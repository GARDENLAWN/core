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

class Cleandry extends Action
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
    )
    {
        parent::__construct($context);
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->cleaner = $cleaner;
    }

    public function execute(): Redirect
    {
        try {
            // Wywołanie metody czyszczenia w trybie testowym (dry run: true)
            $result = $this->cleaner->cleanRedundantConfig(true);
            $message = __(
                "Test run complete. Redundant entries that would be removed: %1.",
                $result['deleted_count']
            );
            // Użycie NoticeMessage (żółte tło) dla testowego przebiegu
            $this->messageManager->addNoticeMessage($message);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred during test run: %1', $e->getMessage()));
        }

        // Przekierowanie z powrotem do sekcji konfiguracji 'gardenlawn_core'
        $resultRedirect = $this->resultRedirectFactory->create();

        // Poprawne przekierowanie do konkretnej sekcji konfiguracji
        return $resultRedirect->setPath(
            'adminhtml/system_config/edit',
            [
                'section' => 'gardenlawn_core',
                '_current' => true // Zachowuje aktualny zakres (store/website) i klucz bezpieczeństwa
            ]
        );
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GardenLawn_Core::run_cleaner_test');
    }
}
