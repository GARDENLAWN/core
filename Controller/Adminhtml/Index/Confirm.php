<?php
declare(strict_types=1);

namespace GardenLawn\Core\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

class Confirm extends Action
{
    private CustomerRepositoryInterface $customerRepository;
    protected $resultRedirectFactory;

    public function __construct(
        Context $context,
        CustomerRepositoryInterface $customerRepository
    ) {
        parent::__construct($context);
        $this->customerRepository = $customerRepository;
        $this->resultRedirectFactory = $context->getResultRedirectFactory();
    }

    public function execute(): ResultInterface|ResponseInterface|Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $customerId = $this->getRequest()->getParam('id');

        if (!$customerId) {
            $this->messageManager->addErrorMessage(__('Customer ID is missing.'));
            return $resultRedirect->setPath('customer/index/index');
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            if ($customer->getConfirmation()) {
                $customer->setConfirmation(null);
                $this->customerRepository->save($customer);
                $this->messageManager->addSuccessMessage(__('The customer account has been confirmed.'));
            } else {
                $this->messageManager->addWarningMessage(__('The customer account is already confirmed.'));
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while confirming the account.'));
        }

        return $resultRedirect->setPath('customer/index/edit', ['id' => $customerId]);
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Magento_Customer::customer');
    }
}
