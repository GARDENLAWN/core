<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Catalog\Model\Product;

use Magento\Catalog\Model\Product\Image;

class ImagePlugin
{
    public function afterGetUrl(Image $subject, string $result): string
    {
        // Check if the original URL is for a JPG, JPEG, or PNG image
        if (preg_match('/\.(jpg|jpeg|png)$/i', $result)) {
            // Replace the extension with .webp
            return preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $result);
        }
        return $result;
    }
}
