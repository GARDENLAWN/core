<?php
namespace GardenLawn\Core\Plugin\Amasty;

use Amasty\ImportCore\Import\Filesystem\FileUploader;
use Magento\Framework\Exception\LocalizedException;

class FileUploaderPlugin
{
    /**
     * @throws LocalizedException
     */
    public function aroundMove(
        FileUploader $subject,
        callable $proceed,
                     $fileName,
                     $renameFileOff = false
    ) {
        if (filter_var($fileName, FILTER_VALIDATE_URL)) {
            $headers = @get_headers($fileName);
            if (!$headers || str_contains($headers[0], '404 Not Found')) {
                // Throwing an exception here will be caught by the processRow method,
                // which will log a warning and skip the file.
                throw new LocalizedException(__('File %1 does not exist. Skipped.', $fileName));
            }
        }

        return $proceed($fileName, $renameFileOff);
    }
}
