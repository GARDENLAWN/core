<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Hyva\Checkout\Model\Form\EntityFormSaveService;

use GardenLawn\Company\Helper\Data as CompanyHelper;
use Hyva\Checkout\Model\Form\EntityFormInterface;
use Hyva\Checkout\Model\Form\EntityFormSaveService\EavAttributeBillingAddress;
use Magento\Framework\Exception\LocalizedException;

class EavAttributeBillingAddressPlugin
{
    /**
     * @var CompanyHelper
     */
    private CompanyHelper $companyHelper;

    /**
     * @param CompanyHelper $companyHelper
     */
    public function __construct(
        CompanyHelper $companyHelper
    ) {
        $this->companyHelper = $companyHelper;
    }

    /**
     * @param EavAttributeBillingAddress $subject
     * @param EntityFormInterface $form
     * @return array
     * @throws LocalizedException
     */
    public function beforeSave(
        EavAttributeBillingAddress $subject,
        EntityFormInterface $form
    ): array {
        $data = $form->toArray();

        if (isset($data['id']) && in_array($this->companyHelper->getCurrentCustomerGroupId(), $this->companyHelper->getB2bCustomerGroups())) {
            throw new LocalizedException(__('B2B customers cannot change their billing address.'));
        }

        return [$form];
    }
}
