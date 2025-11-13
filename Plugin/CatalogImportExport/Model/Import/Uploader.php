<?php
/**
 * Plugin to extend allowed file extensions (WebP, AVIF, SVG) for Catalog Import Uploader.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\CatalogImportExport\Model\Import;

use Magento\CatalogImportExport\Model\Import\Uploader as ImportUploader;

class Uploader
{
    private const array NEW_EXTENSIONS = ['webp', 'avif', 'svg'];

    /**
     * Add WebP, AVIF, and SVG to the list of allowed extensions for the Import Uploader.
     *
     * @param ImportUploader $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetAllowedExtensions(
        ImportUploader $subject,
        array $result
    ): array {
        return array_unique(array_merge($result, self::NEW_EXTENSIONS));
    }
}
