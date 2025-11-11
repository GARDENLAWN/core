<?php
/**
 * Plugin to extend allowed file extensions (WebP, AVIF, SVG) for EAV and Theme Image Uploaders.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\View;

use Magento\Eav\Model\File\Uploader as EavUploader;
use Magento\Framework\View\Design\Theme\Image\Uploader as ThemeImageUploader;

class UploaderExtensions
{
    private const array NEW_EXTENSIONS = ['webp', 'avif', 'svg'];

    /**
     * Add WebP, AVIF, and SVG to the list of allowed extensions for Theme Image Uploader.
     *
     * @param ThemeImageUploader $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetAllowedExtensionsTheme(
        ThemeImageUploader $subject,
        array $result
    ): array {
        return array_unique(array_merge($result, self::NEW_EXTENSIONS));
    }

    /**
     * Add WebP, AVIF, and SVG to the list of allowed extensions for EAV File Uploader.
     *
     * @param EavUploader $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetAllowedExtensionsEav(
        EavUploader $subject,
        array $result
    ): array {
        return array_unique(array_merge($result, self::NEW_EXTENSIONS));
    }
}
