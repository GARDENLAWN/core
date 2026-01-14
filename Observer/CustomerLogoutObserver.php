<?php
declare(strict_types=1);

namespace GardenLawn\Core\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Customer\Model\Context as CustomerContext;

class CustomerLogoutObserver implements ObserverInterface
{
    /**
     * @var HttpContext
     */
    private HttpContext $httpContext;

    /**
     * @param HttpContext $httpContext
     */
    public function __construct(
        HttpContext $httpContext
    ) {
        $this->httpContext = $httpContext;
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        // Force reset of customer group in HTTP context on logout
        $this->httpContext->setValue(CustomerContext::CONTEXT_GROUP, 0, 0);
    }
}
