<?php

namespace GardenLawn\Core\Model\ItemProvider;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sitemap\Model\ItemProvider\ItemProviderInterface;
use Magento\Sitemap\Model\SitemapItemFactory;

class Pages implements ItemProviderInterface
{
    protected array $sitemapItems = [];
    private PagesConfigReader $configReader;
    private SitemapItemFactory $itemFactory;

    /**
     * ExamplePages constructor.
     * @param PagesConfigReader $configReader
     * @param SitemapItemFactory $itemFactory
     */
    public function __construct(
        PagesConfigReader  $configReader,
        SitemapItemFactory $itemFactory
    )
    {
        $this->configReader = $configReader;
        $this->itemFactory = $itemFactory;
    }

    /**
     * @param int $storeId
     * @return array
     * @throws NoSuchEntityException|LocalizedException
     */
    public function getItems($storeId): array
    {
        $pages = [
            /*[
                'name' => 'Kompleksowe zakładanie ogrodów',
                'id' => 'gardeninstallation',
                'url' => 'kompleksowe-zakladanie-ogrodow'
            ],
            [
                'name' => 'Projektowanie ogrodów',
                'id' => 'gardendesign',
                'url' => 'projektowanie-ogrodow'
            ],
            [
                'name' => 'Projektowanie systemów nawadniania',
                'id' => 'irrigationdesign',
                'url' => 'projektowanie-systemow-nawadniania'
            ],
            [
                'name' => 'Systemy nawadniania',
                'id' => 'irrigation',
                'url' => 'systemy-nawadniania'
            ],
            [
                'name' => 'Zakładanie trawników z siewu',
                'id' => 'lawnsseed',
                'url' => 'zakladanie-trawnikow-z-siewu'
            ],
            [
                'name' => 'Zakładanie trawników z rolki',
                'id' => 'lawnsroll',
                'url' => 'zakladanie-trawnikow-z-rolki'
            ],
            [
                'name' => 'Siatka przeciw kretom',
                'id' => 'antimolenet',
                'url' => 'siatka-przeciw-kretom'
            ],
            [
                'name' => 'Roboty koszące',
                'id' => 'robots',
                'url' => 'roboty-koszace'
            ],
            [
                'name' => 'Roboty basenowe',
                'id' => 'poolrobots',
                'url' => 'roboty-basenowe'
            ],
            [
                'name' => 'Pielęgnacja ogrodów',
                'id' => 'gardencare',
                'url' => 'pielegnacja-ogrodow'
            ],
            [
                'name' => 'Projektowanie oraz montaż oświetlenia',
                'id' => 'gardenlighting',
                'url' => 'projektowanie-oraz-montaz-oswietlenia'
            ],
            [
                'name' => 'Wycinka drzew oraz karczowanie terenów',
                'id' => 'fellingtrees',
                'url' => 'wycinka-drzew-oraz-karczowanie-terenow'
            ],
            [
                'name' => 'Nasadzenia drzew oraz roślin',
                'id' => 'planting',
                'url' => 'nasadzanie-drzew-oraz-roslin'
            ],
            [
                'name' => 'Koszenie terenów zielonych',
                'id' => 'mowing',
                'url' => 'koszenie-terenow-zielonych'
            ],
            [
                'name' => 'Prace ziemne niwelacja terenów',
                'id' => 'earthworks',
                'url' => 'prace-ziemne-niwelacja-terenow'
            ],
            [
                'name' => 'Obrzeża, geokraty, palisady',
                'id' => 'edging',
                'url' => 'obrzeza-geokraty-palisady'
            ],
            [
                'name' => 'Krawędziowanie obrzeży trawnika',
                'id' => 'edginglawnedges',
                'url' => 'krawedziowanie-obrzezy-trawnika'
            ],
            [
                'name' => 'Prace brukarskie',
                'id' => 'pavingworks',
                'url' => 'prace-brukarskie'
            ],
            [
                'name' => 'Zbiornik na deszczówkę, szamba',
                'id' => 'rainwater',
                'url' => 'zbiornik-na-deszczowke'
            ],
            [
                'name' => 'Stacje uzdatniania wody, odżelaziacze',
                'id' => 'treatment',
                'url' => 'stacje-uzdatniania-wody-odzelaziacze'
            ],
            [
                'name' => 'Odśnieżanie',
                'id' => 'snowremoval',
                'url' => 'odsniezanie'
            ],
            [
                'name' => 'Galeria',
                'id' => 'gallery',
                'url' => 'galeria'
            ],
            [
                'name' => 'Cennik',
                'id' => 'pricelist',
                'url' => 'cennik'
            ],
            [
                'name' => 'Kontakt',
                'id' => 'contact',
                'url' => 'kontakt'
            ]*/
        ];

        foreach ($pages as $page) {
            $this->sitemapItems[] = $this->itemFactory->create(
                [
                    'url' => $page['url'],
                    'updatedAt' => date("Y-m-d H:i:s"),
                    'priority' => /*$this->getPriority($storeId) ?? */ 1.0,
                    'changeFrequency' => $this->getChangeFrequency($storeId)
                ]
            );
        }

        return $this->sitemapItems;
    }

    /**
     * @param int $storeId
     *
     * @return string
     *
     */
    private function getChangeFrequency(int $storeId): string
    {
        return $this->configReader->getChangeFrequency($storeId);
    }

    /**
     * @param int $storeId
     *
     * @return string
     *
     */
    private function getPriority(int $storeId): string
    {
        return $this->configReader->getPriority($storeId);
    }

}
