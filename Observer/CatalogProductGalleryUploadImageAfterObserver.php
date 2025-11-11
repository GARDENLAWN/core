<?php
declare(strict_types=1);

namespace GardenLawn\Core\Observer;

use GardenLawn\Core\Cron\AwsS3Sync;
use GardenLawn\Core\Helper\Data;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;

class CatalogProductGalleryUploadImageAfterObserver implements ObserverInterface
{
    protected RequestInterface $request;

    protected ManagerInterface $messageManager;
    protected ResponseFactory $responseFactory;
    protected UrlInterface $url;
    protected Data $dataHelper;
    protected AwsS3Sync $awsS3Sync;

    public function __construct(
        RequestInterface $request,
        Data             $dataHelper,
        ManagerInterface $messageManager,
        ResponseFactory  $responseFactory,
        UrlInterface     $url,
        AwsS3Sync        $awsS3Sync
    ) {
        $this->request = $request;
        $this->dataHelper = $dataHelper;
        $this->messageManager = $messageManager;
        $this->responseFactory = $responseFactory;
        $this->url = $url;
        $this->awsS3Sync = $awsS3Sync;
    }

    public function execute(Observer $observer): void
    {
        $this->awsS3Sync->deleteTmpImages();
    }
}
