<?php
declare(strict_types=1);

namespace GardenLawn\Core\Model\Customer\DataProvider;

use Exception;
use GardenLawn\Company\Helper\Data as CompanyHelper;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Ui\DataProvider\Modifier\ModifierInterface;

class Modifier implements ModifierInterface
{
    private CompanyHelper $companyHelper;
    private RequestInterface $request;
    private CustomerRepositoryInterface $customerRepository;
    private UrlInterface $urlBuilder;

    public function __construct(
        CompanyHelper $companyHelper,
        RequestInterface $request,
        CustomerRepositoryInterface $customerRepository,
        UrlInterface $urlBuilder
    ) {
        $this->companyHelper = $companyHelper;
        $this->request = $request;
        $this->customerRepository = $customerRepository;
        $this->urlBuilder = $urlBuilder;
    }

    public function modifyData(array $data): array
    {
        $customerId = $this->request->getParam('id');
        if (!$customerId) {
            return $data;
        }
        // The button doesn't have a data scope, but we need to provide the URL to the UI component
        if (isset($data[$customerId])) {
            $data[$customerId]['links']['confirmUrl'] = $this->urlBuilder->getUrl('gardenlawn_company/index/confirm', ['id' => $customerId]);
        }
        return $data;
    }

    public function modifyMeta(array $meta): array
    {
        $customerId = $this->request->getParam('id');
        if (!$customerId) {
            return $meta;
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            $groupId = (int)$customer->getGroupId();

            if (in_array($groupId, $this->companyHelper->getB2bCustomerGroups())) {
                $meta['address']['children']['address']['children']['b2b_message'] = [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'formElement' => 'container',
                                'componentType' => 'container',
                                'component' => 'Magento_Ui/js/form/components/html',
                                'additionalClasses' => 'admin__fieldset-note',
                                'content' => __('The billing address for this B2B customer is synchronized with CEIDG. The shipping address can be managed from the customer\'s account in the storefront.'),
                                'sortOrder' => 0,
                            ],
                        ],
                    ],
                ];
            }
        } catch (Exception) {
            // Customer not found or other error, do nothing to the meta
        }

        return $meta;
    }
}
