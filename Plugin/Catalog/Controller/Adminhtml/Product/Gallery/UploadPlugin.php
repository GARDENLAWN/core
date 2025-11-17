<?php
namespace GardenLawn\Core\Plugin\Catalog\Controller\Adminhtml\Product\Gallery;

class UploadPlugin
{
    /**
     * @param \Magento\Catalog\Controller\Adminhtml\Product\Gallery\Upload $subject
     * @param array $allowed
     * @return array
     */
    public function afterGetAllowedExtensions(\Magento\Catalog\Controller\Adminhtml\Product\Gallery\Upload $subject, array $allowed): array
    {
        return array_merge($allowed, ['webp', 'avif', 'svg']);
    }
}
