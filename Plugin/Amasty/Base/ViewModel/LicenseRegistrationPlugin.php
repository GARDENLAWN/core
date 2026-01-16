<?php

declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Amasty\Base\ViewModel;

use Amasty\Base\ViewModel\LicenseRegistration;
use Magento\Framework\App\RequestInterface;

class LicenseRegistrationPlugin
{
    /**
     * @var RequestInterface
     */
    private $request;

    public function __construct(
        RequestInterface $request
    ) {
        $this->request = $request;
    }

    /**
     * Restrict license error messages to the admin dashboard only.
     *
     * @param LicenseRegistration $subject
     * @param \Amasty\Base\Model\SysInfo\Data\LicenseValidation\Message|null $result
     * @return \Amasty\Base\Model\SysInfo\Data\LicenseValidation\Message|null
     */
    public function afterGetMessage(
        LicenseRegistration $subject,
        $result
    ) {
        if ($result === null) {
            return null;
        }

        $fullActionName = $this->request->getFullActionName();

        // Allow message only on admin dashboard
        if ($fullActionName === 'adminhtml_dashboard_index') {
            return $result;
        }

        return null;
    }
}
