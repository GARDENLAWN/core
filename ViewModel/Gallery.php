<?php
declare(strict_types=1);

namespace GardenLawn\Core\ViewModel;

use GardenLawn\Core\Utils\Utils;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Gallery implements ArgumentInterface
{
    /**
     * @return array
     */
    public function getWorksInfo(): array
    {
        try {
            $works = Utils::getWorksMediaGalleryAsset();
            $galleries = [];
            foreach ($works as $work) {
                $galleries[] = $work['name'];
            }
            $galleries = array_unique($galleries);
        } catch (NoSuchEntityException $e) {
            return [];
        }

        $worksInfo = [];
        $j = 1;
        foreach ($galleries as $key) {
            $images = [];
            foreach ($works as $k => $w) {
                if (str_contains($w['path'], 'gallery/' . $key . '/')) {
                    $w['thumb'] = str_replace('gallery/', '.thumbsgallery/', $w['link']);
                    $images[] = $w;
                }
            }
            $gallery = [
                'id' => $key,
                'key' => floor($j / 2) + $j % 2,
                'images' => $images
            ];
            $worksInfo[] = $gallery;
            $j++;
        }

        return $worksInfo;
    }
}
