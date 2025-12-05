<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Catalog\Model\View\Asset;

use Magento\Catalog\Model\View\Asset\ImageFactory;

class ImageFactoryPlugin
{
    public function beforeCreate(ImageFactory $subject, array $data = []): array
    {
        if (isset($data['filePath']) && preg_match('/\.(jpg|jpeg|png)$/i', $data['filePath'])) {
            $data['filePath'] = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $data['filePath']);
        }
        return [$data];
    }
}
