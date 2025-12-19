<?php
/**
 * Copyright © GardenLawn. All rights reserved.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Wishlist\CustomerData;

use Magento\Wishlist\CustomerData\Wishlist;
use Magento\Wishlist\Helper\Data as WishlistHelper;
use Magento\Catalog\Model\Product\Configuration\Item\ItemResolverInterface;

class WishlistPlugin
{
    /**
     * @var WishlistHelper
     */
    private $wishlistHelper;

    /**
     * @var ItemResolverInterface
     */
    private $itemResolver;

    /**
     * @param WishlistHelper $wishlistHelper
     * @param ItemResolverInterface $itemResolver
     */
    public function __construct(
        WishlistHelper $wishlistHelper,
        ItemResolverInterface $itemResolver
    ) {
        $this->wishlistHelper = $wishlistHelper;
        $this->itemResolver = $itemResolver;
    }

    /**
     * Add call_for_price attribute to wishlist item data
     *
     * @param Wishlist $subject
     * @param array $result
     * @return array
     */
    public function afterGetSectionData(Wishlist $subject, array $result)
    {
        if (empty($result['items'])) {
            return $result;
        }

        $wishlist = $this->wishlistHelper->getWishlist();
        $items = $wishlist->getItemCollection();

        foreach ($result['items'] as &$itemData) {
            // Find the corresponding item in the collection
            // Note: This is a bit inefficient (O(n^2)), but wishlist sizes are usually small.
            // A better approach would be to map by ID if possible, but section data doesn't always expose item ID cleanly in the same way.
            // However, section data items usually have 'image' url which contains product ID or we can try to match by product ID if exposed.

            // Let's try to find the item by product ID which is usually part of the add_to_cart_params
            // "add_to_cart_params": "{\"action\":\".../checkout/cart/add/uenc/.../product/2876/\",\"data\":{\"product\":\"2876\",\"uenc\":\"...\"}}"

            $productId = null;
            if (isset($itemData['add_to_cart_params'])) {
                $params = json_decode($itemData['add_to_cart_params'], true);
                if (isset($params['data']['product'])) {
                    $productId = $params['data']['product'];
                }
            }

            if ($productId) {
                foreach ($items as $wishlistItem) {
                    if ($wishlistItem->getProductId() == $productId) {
                        $product = $this->itemResolver->getFinalProduct($wishlistItem);
                        $itemData['call_for_price'] = (bool)$product->getData('call_for_price');
                        $itemData['product_sku'] = $product->getSku(); // Useful for contact form
                        break;
                    }
                }
            }

            if (!isset($itemData['call_for_price'])) {
                $itemData['call_for_price'] = false;
            }
        }

        return $result;
    }
}
