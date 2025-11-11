<?php declare(strict_types=1);

namespace GardenLawn\Core\Utils;

use Aws\S3\S3Client;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Utils
{
    public const string Region = 'eu-central-1';
    public const string Bucket = 'gardenlawn';
    private static array $array = [];
    protected DirectoryList $directoryList;

    public static array $imageExtensionsArray = ['jpg', 'jpeg', 'png'];

    public static function isLocal(): bool
    {
        return ObjectManager::getInstance()
                ->get(ScopeConfigInterface::class)
                ->getValue(
                    'gardenlawn/core/enviroment',
                    ScopeInterface::SCOPE_STORE,
                ) == 'local';
    }

    public static function isFirefox(): bool
    {
        $browser = new Browser();
        return $browser->getBrowser() == Browser::BROWSER_FIREFOX;
    }

    public static function isImage($path, $withWebp = false): bool
    {
        $extensions = self::$imageExtensionsArray;
        if ($withWebp) {
            $extensions[] = 'webp';
        }
        return in_array(pathinfo($path, PATHINFO_EXTENSION), $extensions);
    }

    public static function getS3Client(): S3Client
    {
        return new S3Client(
            [
                'region' => self::Region,
                'bucket' => self::Bucket,
                'credentials' => [
                    'key' => 'AKIAVK4HBPORAVO6DN4V',
                    'secret' => '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'
                ]
            ]
        );
    }

    /**
     * @throws NoSuchEntityException
     */
    public static function getMediaGalleryWorks(): array
    {
        return self::getMediaGalleryAsset('wysiwyg/WorksGallery');
    }

    /**
     * @throws NoSuchEntityException
     */
    public static function getImg(string $key): string
    {
        $attributes = Utils::getMediaGalleryAsset($key)[0];
        return '<img class="img-content"
            src="' . ($attributes['link'] ?? '') . '"
            alt="' . ($attributes['title'] ?? '') . '"
            height="' . ($attributes['height'] ?? '') . '"
            width="' . ($attributes['width'] ?? '') . '" />';
    }

    /**
     * @throws NoSuchEntityException
     */
    public static function getFirstProductMediaGalleryAsset(string $sku, bool $thumb = false): array
    {
        return Utils::getProductMediaGalleryAsset($sku, $thumb)[0] ?? [
            'main' => '',
            'link' => '',
            'path' => '',
            'title' => '',
            'height' => '',
            'width' => ''
        ];
    }

    /**
     * @throws NoSuchEntityException
     */
    public static function getProductMediaGalleryAsset(string $sku, bool $thumb = false): array
    {
        $objectManager = ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $sku = str_replace('-', '_', $sku);
        $sql = "
        SELECT DISTINCT
            a.path,
            a.description,
            a.height,
            a.width,
            a.size,
            a.title
        FROM
            catalog_product_entity p JOIN
            catalog_product_entity_media_gallery_value_to_entity v ON p.entity_id = v.entity_id JOIN
            catalog_product_entity_media_gallery g ON v.value_id = g.value_id LEFT JOIN
            media_gallery_asset a ON REGEXP_REPLACE(a.path, '[.].*', '') LIKE CONCAT('%', REGEXP_REPLACE(g.value, '[.].*', ''))
        WHERE
            REPLACE(p.sku, '-', '_') = '$sku' AND
            a.enabled = 1 AND
            a.path " . ($thumb ? "LIKE" : "NOT LIKE") . " '%.thumbs%'
        ORDER BY
            a.sortorder,
            a.title
        ";

        $result = $connection->fetchAll($sql);
        $return = [];
        $imageExt = Utils::getImageExtForBrowser();
        $first = 'first';

        foreach ($result as $item) {
            if (str_contains($item['path'], $imageExt)) {
                $item['main'] = $first;
                $item['link'] = Utils::getMediaUrl() . $item['path'];
                $item['path'] = Utils::getMediaUrl() . $item['path'];
                $return [] = $item;
                $first = '';
            }
        }

        return $return;
    }

    /**
     * @throws NoSuchEntityException
     */
    public static function getMediaGalleryAsset(string $key = "", $catalog = "catalog", bool $thumb = false): array
    {
        $objectManager = ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $key = str_replace('-', '_', $key);
        $sql = "
        SELECT DISTINCT
            a.path,
            a.description,
            a.height,
            a.width,
            a.size,
            a.title
        FROM
            media_gallery_asset a
        WHERE
            REPLACE(a.path, '-', '_') LIKE '%$key%' AND
            a.enabled = 1
        ORDER BY
            a.sortorder,
            a.title
        ";
        $result = $connection->fetchAll($sql);
        $return = [];
        $imageExt = Utils::getImageExtForBrowser();
        $first = 'first';
        foreach ($result as $item) {
            if (str_contains($item['path'], $imageExt)) {
                $item['main'] = $first;
                $item['link'] = $thumb ? str_replace('/' . $catalog . '/', '/.thumbs' . $catalog . '/', Utils::getMediaUrl() . $item['path']) : Utils::getMediaUrl() . $item['path'];
                $return [] = $item;
                $first = '';
            }
        }
        return $return;
    }

    public static function getImageExtForBrowser(): string
    {
        $info = print_r(getallheaders(), true);
        //if (str_contains($info, 'image/avif')) return '.avif';
        if (str_contains($info, 'image/webp')) {
            return '.webp';
        }
        if (str_contains($info, 'image/apng')) {
            return '.png';
        } else {
            return '.webp';
        }
    }

    /**
     * @throws NoSuchEntityException
     */
    public static function getMediaUrl(): string
    {
        $objectManager = ObjectManager::getInstance();
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        return $storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
    }

    /**
     * @throws NoSuchEntityException
     */
    public static function getBaseUrl(): string
    {
        $objectManager = ObjectManager::getInstance();
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        return $storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);
    }

    /**
     * @throws NoSuchEntityException
     */
    public static function getFileMediaUrl($filePath): string
    {
        return Utils::getFileUrl($filePath) . Utils::getImageExtForBrowser();
    }

    /**
     * @throws NoSuchEntityException
     */
    public static function getFileUrl($filePath): string
    {
        return Utils::getMediaUrl() . str_replace(' ', '+', $filePath);
    }

    public static function getSpImg(array $asset): string
    {
        return '<img class="sp-image"' .
            ($asset['main'] == 'first' ? 'src="' : 'data-src="') . $asset['link'] . '"
            alt="' . $asset['title'] . '"
            height="' . $asset['height'] . '"
            width="' . $asset['width'] . '" />';
    }

    public static function getSpThumb(array $asset): string
    {
        return '<img class="sp-thumbnail"
            src="' . $asset['link'] . '"
            alt="' . $asset['title'] . '"
            height="' . $asset['height'] . '"
            width="' . $asset['width'] . '" />';
    }

    /**
     * @throws NoSuchEntityException
     */
    public static function getMediaAttributes(string $key): string
    {
        $attributes = Utils::getFirstProductMediaGalleryAsset($key);
        return 'src="' . $attributes['path'] . '" alt="' . $attributes['title'] . '" height="' . ''/*$attributes['height']*/ . '" width="' . ''/*$attributes['width']*/ . '"';
    }

    /**
     * @throws NoSuchEntityException
     */
    public static function getFirstMediaGalleryPath(string $key): string
    {
        return Utils::getProductMediaGalleryAsset($key)[0]['path'];
    }

    /**
     * @throws NoSuchEntityException
     */
    public static function getFirstMediaGalleryWidth(string $key): string
    {
        return Utils::getProductMediaGalleryAsset($key)[0]['width'];
    }

    /**
     * @throws NoSuchEntityException
     */
    public static function getFirstMediaGalleryHeigth(string $key): string
    {
        return Utils::getProductMediaGalleryAsset($key)[0]['height'];
    }

    /**
     * @throws NoSuchEntityException
     */
    public static function getFirstMediaGalleryTitle(string $key): string
    {
        return Utils::getProductMediaGalleryAsset($key)[0]['title'];
    }

    /**
     * @throws NoSuchEntityException
     */
    public static function getMediaGalleryAssetPaths(): array
    {
        $result = Utils::getMediaGalleryAsset();
        $paths = [];
        foreach ($result as $item) {
            $paths[] = $item['path'];
        }
        return $paths;
    }

    public static function getMediaGalleryAssetWithoutMediaGallery(): array
    {
        $objectManager = ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('media_gallery_asset');
        $sql = "SELECT * FROM " . $tableName . " WHERE mediagallery_id IS NULL";
        return $connection->fetchAll($sql);
    }

    /**
     * @throws NoSuchEntityException
     */
    public static function getWorksMediaGalleryAsset(): array
    {
        $objectManager = ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $sql = "SELECT
                    REPLACE(mg.name, 'gallery/', '') AS name,
                    mga.path,
                    mga.title,
                    mga.height,
                    mga.width
                FROM
                    gardenlawn_mediagallery mg JOIN
                    media_gallery_asset mga on mg.id = mga.mediagallery_id
                WHERE
                    mg.name LIKE 'gallery/%' AND
                    mga.path LIKE '%.webp' AND
                    mg.enabled = 1 AND
                    mga.enabled = 1
                ORDER BY
                    mg.sortorder, mg.name, mga.sortorder, mga.path;";
        $result = $connection->fetchAll($sql);
        $return = [];
        $first = 'first';
        foreach ($result as $item) {
            $item['main'] = $first;
            $item['link'] = Utils::getMediaUrl() . str_replace(' ', '+', $item['path']);
            $return [] = $item;
            $first = '';
        }
        return $return;
    }

    public static function getAllFiles($dir, $asc = true): array
    {
        Utils::$array = [];
        $dirToArray = Utils::dirToArray($dir, $asc);
        array_walk_recursive($dirToArray, 'GardenLawn\Core\Utils\Utils::returnValue');
        return Utils::$array;
    }

    public static function dirToArray($dir, $asc = true): array
    {
        $result = [];
        $scanDir = scandir($dir, sorting_order: $asc ? SCANDIR_SORT_ASCENDING : SCANDIR_SORT_DESCENDING);
        foreach ($scanDir as $value) {
            if (!in_array($value, [".", ".."])) {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $value)) {
                    $result[$value] = Utils::dirToArray($dir . DIRECTORY_SEPARATOR . $value, $asc);
                } else {
                    $result[] = $dir . DIRECTORY_SEPARATOR . $value;
                }
            }
        }
        return $result;
    }

    public static function getCeidgData($nip = '9910356075'): bool|string
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, "https://dane.biznes.gov.pl/api/ceidg/v2/firmy?nip=" . $nip);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BEARER);
        curl_setopt($curl, CURLOPT_XOAUTH2_BEARER, 'eyJraWQiOiJjZWlkZyIsImFsZyI6IkhTNTEyIn0.eyJnaXZlbl9uYW1lIjoiUmFmYcWCIiwicGVzZWwiOiI4NzA2MTUxMTkxMyIsImlhdCI6MTcyMDAzMzUwNCwiZmFtaWx5X25hbWUiOiJQaWVjaG90YSIsImNsaWVudF9pZCI6IlVTRVItODcwNjE1MTE5MTMtUkFGQcWBLVBJRUNIT1RBIn0.cEcG_lWVHDqWD5_VWp4cqjo-cteNUhmdoWcOCD4phuUp17_F1C27o9q9Ejq1FG5x6Hedl_s4jFB6oS7Fww-KEQ');

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;
    }

    /**
     * @throws NoSuchEntityException
     */
    public static function getMediaDirTmp(): string
    {
        return Utils::getMediaUrl() . 'tmp' . DIRECTORY_SEPARATOR;
    }

    public static function getDirTmp(): string
    {
        return 'var/tmp/';
    }

    public static function trimPolish(string $str): string
    {
        return strtr(mb_convert_encoding(ltrim(rtrim($str)), 'ISO-8859-2'), mb_convert_encoding('ąćęłńóśźżĄĆĘŁŃÓŚŹŻ', 'ISO-8859-2'), 'acelnoszzACELNOSZZ');
    }

    private static function returnValue($item, $key): void
    {
        Utils::$array[] = $item;
    }

    public static function groupBy($array, $key): array
    {
        $return = [];
        foreach ($array as $val) {
            $return[$val[$key]][] = $val;
        }
        return $return;
    }

    /**
     * Get all values from specific key in a multidimensional array
     *
     * @param $key string
     * @param $arr array
     * @return array|mixed|null
     */
    public static function arrayValueRecursive(string $key, array $arr): mixed
    {
        $val = [];
        array_walk_recursive($arr, function ($v, $k) use ($key, &$val) {
            if ($k == $key) {
                $val[] = $v;
            }
        });
        return count($val) > 1 ? $val : array_pop($val);
    }

    public static function replaceExtensionWithWebp(string $filePath): string
    {
        // The regex looks for:
        // 1. A dot (.)
        // 2. Followed by 1 to 5 alphanumeric characters (the extension)
        // 3. At the end of the string ($)
        // 4. The 'i' modifier makes the match case-insensitive (e.g., .JPG or .jPeG)

        $pattern = '/\.[a-z0-9]{1,5}$/i';

        // Check if a standard extension exists
        if (preg_match($pattern, $filePath)) {
            // If an extension exists, replace it with .webp
            return preg_replace($pattern, '.webp', $filePath);
        }

        // If no extension is found, or if the string is just a filename,
        // simply append .webp (e.g., 'image' becomes 'image.webp')
        return $filePath . '.webp';
    }
}
