<?php
/**
 * Copyright 2011 Adobe
 * All Rights Reserved.
 *
 * NOTE: This is an overridden class to support modern image formats (WebP, AVIF, SVG).
 */
declare(strict_types=1);

namespace GardenLawn\Core\Plugin\Catalog\Controller\Adminhtml\Product\Gallery;

use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;

/**
 * The product gallery upload controller
 */
class Upload extends \Magento\Backend\App\Action implements HttpPostActionInterface
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Magento_Catalog::products';

    /**
     * @var \Magento\Framework\Controller\Result\RawFactory
     */
    protected $resultRawFactory;

    /**
     * @var array
     */
    private $allowedMimeTypes = [
        'jpg' => 'image/jpg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'png' => 'image/png',
        // --- DODANO WSPARCIE DLA NOWYCH FORMATÓW ---
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'svg' => 'image/svg+xml' // Standardowy MIME dla SVG
    ];

    /**
     * @var \Magento\Framework\Image\AdapterFactory
     */
    private $adapterFactory;

    /**
     * @var \Magento\Framework\Filesystem
     */
    private $filesystem;

    /**
     * @var \Magento\Catalog\Model\Product\Media\Config
     */
    private $productMediaConfig;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Controller\Result\RawFactory $resultRawFactory
     * @param \Magento\Framework\Image\AdapterFactory $adapterFactory
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Catalog\Model\Product\Media\Config $productMediaConfig
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        ?\Magento\Framework\Image\AdapterFactory $adapterFactory = null,
        ?\Magento\Framework\Filesystem $filesystem = null,
        ?\Magento\Catalog\Model\Product\Media\Config $productMediaConfig = null
    ) {
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
        $this->adapterFactory = $adapterFactory ?: ObjectManager::getInstance()
            ->get(\Magento\Framework\Image\AdapterFactory::class);
        $this->filesystem = $filesystem ?: ObjectManager::getInstance()
            ->get(\Magento\Framework\Filesystem::class);
        $this->productMediaConfig = $productMediaConfig ?: ObjectManager::getInstance()
            ->get(\Magento\Catalog\Model\Product\Media\Config::class);
    }

    /**
     * Upload image(s) to the product gallery.
     *
     * @return \Magento\Framework\Controller\Result\Raw
     */
    public function execute()
    {
        try {
            $uploader = $this->_objectManager->create(
                \Magento\MediaStorage\Model\File\Uploader::class,
                ['fileId' => 'image']
            );
            $uploader->setAllowedExtensions($this->getAllowedExtensions());

            // Image adapter validation will now correctly handle WebP/AVIF/SVG
            // thanks to the plugins you implemented earlier.
            $imageAdapter = $this->adapterFactory->create();
            $uploader->addValidateCallback('catalog_product_image', $imageAdapter, 'validateUploadFile');

            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);
            $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            $result = $uploader->save(
                $mediaDirectory->getAbsolutePath($this->productMediaConfig->getBaseTmpMediaPath())
            );
            $this->_eventManager->dispatch(
                'catalog_product_gallery_upload_image_after',
                ['result' => $result, 'action' => $this]
            );

            if (is_array($result)) {
                unset($result['tmp_name']);
                unset($result['path']);

                $result['url'] = $this->productMediaConfig->getTmpMediaUrl($result['file']);
                // WAŻNE: Oryginalny plik dodaje .tmp, aby tymczasowo oznaczyć plik.
                // Zachowujemy tę logikę.
                $result['file'] = $result['file'] . '.tmp';
            } else {
                $result = ['error' => 'Something went wrong while saving the file(s).7'];
            }
        } catch (LocalizedException $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        } catch (\Throwable $e) {
            // Logowanie błędu, aby ułatwić debugowanie w przypadku nieznanych wyjątków
            $this->_objectManager->get(\Psr\Log\LoggerInterface::class)->error($e);
            $result = ['error' => 'Something went wrong while saving the file(s).8', 'errorcode' => 0];
        }

        /** @var \Magento\Framework\Controller\Result\Raw $response */
        $response = $this->resultRawFactory->create();
        $response->setHeader('Content-type', 'text/plain');
        $response->setContents(json_encode($result));
        return $response;
    }

    /**
     * Get the set of allowed file extensions.
     *
     * @return array
     */
    private function getAllowedExtensions()
    {
        return array_keys($this->allowedMimeTypes);
    }
}
