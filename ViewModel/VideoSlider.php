<?php
declare(strict_types=1);

namespace GardenLawn\Core\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

class VideoSlider implements ArgumentInterface
{
    private array $videoData = [];

    public function setVideoData(array $videoData): self
    {
        $this->videoData = $videoData;
        return $this;
    }

    public function getVideoData(): array
    {
        return $this->videoData;
    }

    public function getJsonVideoData(): string
    {
        return json_encode($this->videoData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
