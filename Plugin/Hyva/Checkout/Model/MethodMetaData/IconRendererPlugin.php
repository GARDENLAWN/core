<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Hyva\Checkout\Model\MethodMetaData;

use Exception;
use Hyva\Checkout\Model\MethodMetaData\IconRenderer;
use Magento\Framework\Escaper;
use Magento\Framework\View\Asset\Repository as AssetRepository;

class IconRendererPlugin
{
    private AssetRepository $assetRepository;
    private Escaper $escaper;

    public function __construct(
        AssetRepository $assetRepository,
        Escaper $escaper
    ) {
        $this->assetRepository = $assetRepository;
        $this->escaper = $escaper;
    }

    public function aroundRenderAsImage(
        IconRenderer $subject,
        callable $proceed,
        string $url,
        array $attributes = []
    ): string {
        if (!isset($attributes['src-mobile'])) {
            return $proceed($url, $attributes);
        }

        try {
            $desktopUrl = $this->assetRepository->getUrl($url);
            $mobileUrl = $this->assetRepository->getUrl($attributes['src-mobile']);
        } catch (Exception $exception) {
            return '';
        }

        $html = '<picture>';
        $html .= '<source media="(max-width: 767px)" srcset="' . $this->escaper->escapeUrl($mobileUrl) . '">';
        $html .= '<source media="(min-width: 768px)" srcset="' . $this->escaper->escapeUrl($desktopUrl) . '">';

        unset($attributes['src-mobile']);

        $html .= '<img src="' . $this->escaper->escapeUrl($desktopUrl) . '"';

        foreach ($attributes as $name => $value) {
            $html .= ' ' . $this->escaper->escapeHtml($name) . '="' . $this->escaper->escapeHtmlAttr($value) . '"';
        }

        $html .= '/>';
        $html .= '</picture>';

        return $html;
    }
}
