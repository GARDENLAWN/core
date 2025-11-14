<?php
declare(strict_types=1);

namespace GardenLawn\Core\Controller\Adminhtml\Config;

use GardenLawn\Core\Model\Config\Cleaner;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

class Cleanup extends Action
{
    public const ADMIN_RESOURCE = 'GardenLawn_Core::run_cleanup'; // Updated ACL resource

    /**
     * @var JsonFactory
     */
    protected JsonFactory $resultJsonFactory;

    /**
     * @var Cleaner
     */
    protected Cleaner $cleaner;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param Cleaner $cleaner
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Cleaner $cleaner
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->cleaner = $cleaner;
    }

    /**
     * Execute cleanup action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $resultJson = $this->resultJsonFactory->create();
        $isDryRun = (bool)$this->getRequest()->getParam('is_dry_run', false);

        try {
            $result = $this->cleaner->cleanRedundantConfig($isDryRun);
            return $resultJson->setData([
                'success' => true,
                'message' => $isDryRun ? __('Dry run completed.') : __('Cleanup completed.'),
                'messages' => $result['messages']
            ]);
        } catch (LocalizedException $e) {
            return $resultJson->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            return $resultJson->setData(['success' => false, 'message' => __('An unexpected error occurred: %1', $e->getMessage())]);
        }
    }
}
