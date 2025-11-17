<?php
namespace GardenLawn\Core\Plugin\Framework\File;

use Closure;
use Magento\Framework\File\Uploader;

class UploaderPlugin
{
    /**
     * @var array
     */
    private array $allowedExtensions = ['webp', 'avif', 'svg'];

    /**
     * @param Uploader $subject
     * @param Closure $proceed
     * @param string $extension
     * @return bool
     */
    public function aroundCheckAllowedExtension(Uploader $subject, Closure $proceed, string $extension): bool
    {
        if (in_array(strtolower($extension), $this->allowedExtensions)) {
            return true;
        }
        return $proceed($extension);
    }
}
