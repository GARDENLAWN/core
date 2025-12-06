<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Cms\Model\Wysiwyg\Images;

use Magento\Cms\Model\Wysiwyg\Images\Storage;

class StoragePlugin
{
    /**
     * After get allowed extensions
     *
     * @param Storage $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetAllowedExtensions(Storage $subject, array $result): array
    {
        $newExtensions = ['webp', 'avif', 'svg'];
        return array_merge($result, $newExtensions);
    }
}
