<?php
/**
 * Copyright © GardenLawn. All rights reserved.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Controller\Adminhtml\Config;

use GardenLawn\Core\Model\Config\Cleaner;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\App\ResponseInterface;
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

    public function execute(): ResponseInterface
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

        // Przekierowanie z powrotem do strony konfiguracji
        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath('*/*/index');
    }
}
