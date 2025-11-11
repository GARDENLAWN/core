<?php
namespace GardenLawn\Core\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Image\AdapterFactory;

class WebpConverter
{
    protected Filesystem $fileSystem;
    protected Filesystem\Directory\WriteInterface $mediaDirectory;
    protected AdapterFactory $imageAdapterFactory;

    /**
     * @throws FileSystemException
     */
    public function __construct(
        Filesystem $fileSystem,
        AdapterFactory $imageAdapterFactory
    ) {
        $this->fileSystem = $fileSystem;
        $this->mediaDirectory = $fileSystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->imageAdapterFactory = $imageAdapterFactory;
    }

    /**
     * @throws FileSystemException
     */
    public function convertAndSave($sourceFilePath, $baseDir, $quality = 80): array|false|string
    {
        // 1. Definicja ścieżek
        $localTempPath = $this->mediaDirectory->getAbsolutePath('webp_temp' . $sourceFilePath); // Pobierz do temp
        $localWebpPath = $this->mediaDirectory->getAbsolutePath('webp_temp' . str_replace(['.jpg', '.png', '.jpeg'], '.webp', $sourceFilePath));
        $s3WebpPath = str_replace(['.jpg', '.png', '.jpeg'], '.webp', $sourceFilePath);

        // 2. Pobranie pliku z S3 do lokalnego katalogu tymczasowego 'media/webp_temp/'
        // Jeśli plik jest w S3, Magento używa metody copyFrom jako proxy do S3
        $this->mediaDirectory->copyFile($sourceFilePath, $localTempPath);

        try {
            // 3. Konwersja lokalna za pomocą Image Adapter (bezpieczniejsza niż czyste GD)
            $imageAdapter = $this->imageAdapterFactory->create();
            $imageAdapter->open($localTempPath);

            // Konwersja na WebP (część implementacji GD/Imagick)
            $imageAdapter->save($localWebpPath, 'image/webp', $quality);

            // 4. Wgranie pliku WebP z lokalnego temp do S3
            // W tym momencie, ze względu na aktywny S3 w konfiguracji, Magento automatycznie
            // użyje adaptera S3 do wgrania pliku.
            $this->mediaDirectory->copyFile($localWebpPath, $s3WebpPath);

            $success = true;
        } catch (\Exception $e) {
            $success = false;
            // Tutaj logowanie błędu konwersji
        }

        // 5. Czyszczenie lokalnych plików tymczasowych
        if ($this->mediaDirectory->isExist($localTempPath)) {
            $this->mediaDirectory->delete($localTempPath);
        }
        if ($this->mediaDirectory->isExist($localWebpPath)) {
            $this->mediaDirectory->delete($localWebpPath);
        }

        return $success ? $s3WebpPath : false;
    }
}
