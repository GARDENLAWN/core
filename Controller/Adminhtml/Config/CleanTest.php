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

class CleanTest extends Action
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

    public function execute(): ResponseInterface
    {
        try {
            $result = $this->cleaner->cleanRedundantConfig(true);
            $message = __(
                "Test run complete. Redundant entries that would be removed: %1.",
                $result['deleted_count']
            );
            // Użycie NoticeMessage dla testowego przebiegu
            $this->messageManager->addNoticeMessage($message);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred during test run: %1', $e->getMessage()));
        }

        // Przekierowanie z powrotem do strony konfiguracji
        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath('*/*/index');
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GardenLawn_Core::run_cleaner_test');
    }
}
