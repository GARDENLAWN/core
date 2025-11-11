<?php
declare(strict_types=1);

namespace GardenLawn\Core\Observer;

use GardenLawn\Core\Helper\Data;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;

class CustomerEditSaveObserver implements ObserverInterface
{
    protected RequestInterface $request;

    protected ManagerInterface $messageManager;
    protected ResponseFactory $responseFactory;
    protected UrlInterface $url;
    protected Data $dataHelper;

    public function __construct(
        RequestInterface $request,
        Data                                    $dataHelper,
        ManagerInterface                        $messageManager,
        ResponseFactory                         $responseFactory,
        UrlInterface                            $url
    )
    {
        $this->request = $request;
        $this->dataHelper = $dataHelper;
        $this->messageManager = $messageManager;
        $this->responseFactory = $responseFactory;
        $this->url = $url;
    }

    public function execute(Observer $observer): void
    {
        $moduleName = $this->request->getModuleName();
        $controllerName = $this->request->getControllerName();
        $actionName = $this->request->getActionName();

        if ($moduleName == 'customer' && $controllerName = 'account' && $actionName == 'editPost') {
            /*if ($this->dataHelper->isCustomerB2B()) {
                $message = "Nie można edytować klienta B2B";
                $this->messageManager->addError($message);
                $url = $this->url->getUrl('customer/account/edit');
                $this->responseFactory->create()->setRedirect($url)->sendResponse();
                exit;
            }*/
        }
    }
}
