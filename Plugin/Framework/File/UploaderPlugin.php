<?php
namespace GardenLawn\Core\Plugin\Framework\File;

use Magento\Framework\File\Uploader;

class UploaderPlugin
{
    /**
     * @param Uploader $subject
     * @param array $extensions
     * @return array
     */
    public function beforeSetAllowedExtensions(Uploader $subject, array $extensions = []): array
    {
        $extensions = array_merge($extensions, ['webp', 'avif', 'svg']);
        return [$extensions];
    }
}
