<?php
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Amasty\SeoHtmlSitemap\Block;

use Amasty\SeoHtmlSitemap\Block\Sitemap;

class SitemapPlugin
{
    /**
     * Plugin to remove inline styles from the Amasty HTML Sitemap module.
     * The inline styles conflict with the Tailwind CSS grid layout.
     *
     * @param Sitemap $subject
     * @param string $result
     * @return string
     */
    public function afterRenderChunks(Sitemap $subject, string $result): string
    {
        // Remove the inline style="width:XX%;" which is added by the block
        // The classes for the inner grid are now controlled directly in the .phtml template
        // by modifying the output of renderChunks. This gives more control.
        // Example of what could be done here if needed:
        // $result = str_replace(
        //     'class="amasty-sitemap-chunk',
        //     'class="amasty-sitemap-chunk grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-x-6',
        //     $result
        // );

        return preg_replace('/style="width:([0-9.]+)%;"/', '', $result);
    }
}
