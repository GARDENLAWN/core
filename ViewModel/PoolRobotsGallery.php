<?php
declare(strict_types=1);

namespace GardenLawn\Core\ViewModel;

use GardenLawn\Core\Utils\Utils;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class PoolRobotsGallery implements ArgumentInterface
{
    public function getImages(): array
    {
        return [
            ['imgId' => 'image1', 'src' => Utils::getFileMediaUrl('poolrobots/1')],
            ['imgId' => 'image2', 'src' => Utils::getFileMediaUrl('poolrobots/2')],
            ['imgId' => 'image3', 'src' => Utils::getFileMediaUrl('poolrobots/3')],
            ['imgId' => 'image4', 'src' => Utils::getFileMediaUrl('poolrobots/4')],
            ['imgId' => 'image5', 'src' => Utils::getFileMediaUrl('poolrobots/5')],
            ['imgId' => 'image6', 'src' => Utils::getFileMediaUrl('poolrobots/6')],
            ['imgId' => 'image7', 'src' => Utils::getFileMediaUrl('poolrobots/7')],
            ['imgId' => 'image8', 'src' => Utils::getFileMediaUrl('poolrobots/8')],
            ['imgId' => 'image9', 'src' => Utils::getFileMediaUrl('poolrobots/9')],
            ['imgId' => 'image10', 'src' => Utils::getFileMediaUrl('poolrobots/10')],
            ['imgId' => 'image11', 'src' => Utils::getFileMediaUrl('poolrobots/11')],
            ['imgId' => 'image12', 'src' => Utils::getFileMediaUrl('poolrobots/12')],
            ['imgId' => 'image13', 'src' => Utils::getFileMediaUrl('poolrobots/13')],
            ['imgId' => 'image14', 'src' => Utils::getFileMediaUrl('poolrobots/14')],
            ['imgId' => 'image15', 'src' => Utils::getFileMediaUrl('poolrobots/15')]
        ];
    }
}
