<?php
declare(strict_types=1);

namespace GardenLawn\Core\Block\Adminhtml\Customer\Edit;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class ForceConfirmButton implements ButtonProviderInterface
{
    private RequestInterface $request;
    private CustomerRepositoryInterface $customerRepository;
    private UrlInterface $urlBuilder;

    public function __construct(
        RequestInterface $request,
        CustomerRepositoryInterface $customerRepository,
        UrlInterface $urlBuilder
    ) {
        $this->request = $request;
        $this->customerRepository = $customerRepository;
        $this->urlBuilder = $urlBuilder;
    }

    public function getButtonData(): array
    {
        $customerId = $this->request->getParam('id');
        if (!$customerId) {
            return [];
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            if (!$customer->getConfirmation()) {
                return [];
            }
        } catch (\Exception) {
            return [];
        }

        return [
            'label' => __('Force Confirm Account'),
            'class' => 'primary',
            'on_click' => sprintf("location.href = '%s';", $this->getConfirmationUrl()),
            'sort_order' => 26,
        ];
    }

    private function getConfirmationUrl(): string
    {
        $customerId = $this->request->getParam('id');
        return $this->urlBuilder->getUrl('gardenlawn_company/index/confirm', ['id' => $customerId]);
    }
}
