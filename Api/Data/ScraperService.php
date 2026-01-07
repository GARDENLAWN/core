<?php

namespace GardenLawn\Core\Api\Data;

use DiDom\Document;
use DiDom\Exceptions\InvalidSelectorException;
use Exception;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use stdClass;

class ScraperService
{
    public static function getItem($document, $name, $find, $tag = null, $attr = null, $getText = false, $index = null): string
    {
        $item = '';
        $j = 0;
        foreach ($find as $f) {
            $ds = $document->find($f);
            $i = 0;
            foreach ($ds as $d) {
                if ($tag != null) {
                    foreach ($d->find($tag) as $e) {
                        if (!str_contains($e->attr($attr), 'data:image')) {
                            $item .= $e->attr($attr) . ';';
                        }
                    }
                    return $item;
                } elseif ($index == null || $index[$j] == null) {
                    return $getText ? $d->text() : $d->innerHtml();
                } elseif ($index[$j] == $i) {
                    return $getText ? $d->text() : $d->innerHtml();
                }
                $i++;
            }
            $j++;
        }

        return "";
    }

    /**
     * @throws InvalidSelectorException
     */
    public static function scraper($item): object
    {
        $newUrl = $item->url;

        if (empty($newUrl) && !empty($item->skuExternal)) {
            $ch = curl_init('https://am-robots.com/pl/?s=' . $item->skuExternal);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $content = curl_exec($ch);

            if (!empty($content)) {
                $document = new Document($content);

                foreach ($document->find('.woocommerce-LoopProduct-link') as $a) {
                    $newUrl = $a->attr('href');
                }
            }
            curl_close($ch);

            if ($newUrl == '') {
                echo 'Not found: ' . $item->skuExternal . '<br/>';
            }
        } else {
            return $item;
        }

        $curl = curl_init($newUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $content = curl_exec($curl);
        curl_close($curl);

        if (!empty($content)) {
            $document = new Document($content);
            $item->attribute_set_id = '11';
            $item->type_id = 'simple';
            $item->url = $newUrl;
            $att = new stdClass();
            $att->name = self::getItem($document, 'Title', ['.breadcrumb_last'], null, null, true, null);
            $att->CategoryWords = explode(", ", self::getItem($document, 'CategoryWords', ['.posted_in'], null, null, true, null));
            $words = [];
            foreach ($att->CategoryWords as $w) {
                $words[] = str_replace("Kategorie: ", "", $w);
            }
            $att->CategoryWords = $words;
            $att->Tags = explode(", ", str_replace("Tagi: ", "", self::getItem($document, 'CategoryWords', ['.tagged_as'], null, null, true, null)));
            $magentoSku = $newUrl;
            $item->sku = $magentoSku;
            $att->sku = $magentoSku;
            $att->short_description = self::getItem($document, 'ShortDescription', ['.woocommerce-product-details__short-description', '.et_pb_wc_description_0'], null, null, false, null);
            //$att->Videos = self::getItem($document, 'Videos', ['.woocommerce-product-details__short-description'], 'iframe', 'data-src-cmplz', false, null);
            //$att->Images = explode(';', self::getItem($document, 'Images', ['.woocommerce-product-gallery'], 'img', 'src', false, [0]));
            $att->description = self::getItem($document, 'Description', ['#tab-description', '.et_pb_tab_content'], null, null, false, [null, 1]);
            $att->description .= '<br/><br/>' . self::getItem($document, 'AdditionalInformation', ['#tab-additional_information', '.et_pb_tab_content'], null, null, false, [null, 2]);
            $att->description .= '<br/><br/>' . self::getItem($document, 'Catalogue', ['#tab-catalogue_tab', '.et_pb_tab_content'], null, null, false, [null, 3]);
            $att->description .= '<br/><br/>' . self::getItem($document, 'Downloads', ['#tab-downloads_tab', '.et_pb_tab_content'], null, null, false, [null, 4]);
            $att->santander_installment = 1;

            $gallery = [];
            $images = explode(';', str_replace(".webp", "", self::getItem($document, 'Images', ['.woocommerce-product-gallery'], 'img', 'src', false, [0])));

            $imagesAllowed = [];

            foreach ($images as $image) {
                if (ScraperService::checkRemoteFile($image)) {
                    $imagesAllowed [] = $image;
                }
            }

            $firstImg = '';
            $position = 1;
            foreach ($imagesAllowed as $img) {
                if (!str_contains($img, 'product-image-placeholder') && $img != '' && strlen($img) > 0) {
                    echo 't: "' . $img . '"<br/>';
                    $i = new stdClass();
                    $i->attribute_id = 90;
                    $i->value = $img;
                    if ($firstImg == '') {
                        $firstImg = $img;
                    }
                    $i->media_type = "image";
                    $i->product_id = $magentoSku;
                    $ent = new stdClass();
                    $ent->entity_id = $magentoSku;
                    $ent->position = $position;
                    $i->catalog_product_entity_media_gallery_value = [$ent];
                    $gallery[] = $i;
                    $position++;
                }
            }

            if ($firstImg != '') {
                $att->image = $firstImg;
                $att->small_image = $firstImg;
                $att->thumbnail = $firstImg;
                $att->swatch_image = $firstImg;
            }

            $item->catalog_product_attribute = [$att];
            $item->catalog_product_entity_media_gallery = $gallery;
        }
        return $item;
    }

    public static function scrapeImages($url, $sku): array
    {
        $result = ['gallery' => [], 'firstImg' => '', 'short_description' => '', 'description' => ''];
        try {
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0');
            $content = curl_exec($curl);
            curl_close($curl);

            if (!empty($content)) {
                $document = new Document($content);
                $images = explode(';', str_replace(".webp", "", self::getItem($document, 'Images', ['.woocommerce-product-gallery'], 'img', 'src', false, [0])));

                $imagesAllowed = [];
                foreach ($images as $image) {
                    if (self::checkRemoteFile($image)) {
                        $imagesAllowed[] = $image;
                    }
                }

                $firstImg = '';
                $position = 1;
                $gallery = [];

                foreach ($imagesAllowed as $img) {
                    if (!str_contains($img, 'product-image-placeholder') && $img != '' && strlen($img) > 0) {
                        $i = new stdClass();
                        $i->attribute_id = 90;
                        $i->value = $img;
                        if ($firstImg == '') {
                            $firstImg = $img;
                        }
                        $i->media_type = "image";
                        $i->product_id = $sku;
                        $ent = new stdClass();
                        $ent->entity_id = $sku;
                        $ent->position = $position;
                        $i->catalog_product_entity_media_gallery_value = [$ent];
                        $gallery[] = $i;
                        $position++;
                    }
                }
                $result['gallery'] = $gallery;
                $result['firstImg'] = $firstImg;

                $result['short_description'] = self::getItem($document, 'ShortDescription', ['.woocommerce-product-details__short-description', '.et_pb_wc_description_0'], null, null, false, null);
                $result['description'] = self::getItem($document, 'Description', ['#tab-description', '.et_pb_tab_content'], null, null, false, [null, 1]);
                $result['description'] .= '<br/><br/>' . self::getItem($document, 'AdditionalInformation', ['#tab-additional_information', '.et_pb_tab_content'], null, null, false, [null, 2]);
                $result['description'] .= '<br/><br/>' . self::getItem($document, 'Catalogue', ['#tab-catalogue_tab', '.et_pb_tab_content'], null, null, false, [null, 3]);
                $result['description'] .= '<br/><br/>' . self::getItem($document, 'Downloads', ['#tab-downloads_tab', '.et_pb_tab_content'], null, null, false, [null, 4]);
            }
        } catch (Exception $e) {
            // ignore
        }
        return $result;
    }

    /**
     * @throws InvalidSelectorException
     */
    public static function saveAutomowJsonData(): void
    {
        $csvFile = BP . "/app/code/GardenLawn/Core/Configs/AM_Processed.csv";
        $items = [];
        if (file_exists($csvFile)) {
            $handle = fopen($csvFile, "r");
            $header = fgetcsv($handle, 0, ";");

            $i = 0;
            while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
                if ($i < 2000) {
                    if (count($data) !== count($header)) {
                        continue;
                    }
                    $row = array_combine($header, $data);

                    $item = new stdClass();
                    $item->sku = $row['sku'];
                    $item->url = ($row['url'] === 'BRAK') ? '' : $row['url'];
                    $item->skuExternal = ($row['att.external_sku'] === 'BRAK') ? '' : $row['att.external_sku'];
                    $item->type_id = $row['type_id'];
                    $item->attribute_set_id = $row['att.attribute_set_id'];
                    $item->contains = $row['att.length']; // Mapowanie length na contains dla logiki wariacji

                    $att = new stdClass();
                    $att->sku = $row['att.sku'];
                    $att->name = $row['att.name'];
                    $att->price = $row['att.dealer_price'];
                    $att->dealer_price = $row['att.dealer_price'];
                    $att->distributor_price = $row['att.distributor_price'];
                    $att->dimension = $row['att.dimension'];
                    $att->weight = $row['att.weight'];
                    $att->external_sku = $item->skuExternal;
                    $att->commodity_code = $row['att.commodity_code'];
                    $att->GTIN13 = $row['att.GTIN13'];
                    $att->inpost_dimension = $row['att.inpost_dimension'] ?? null;
                    $att->CategoryWords = []; // Inicjalizacja pustej tablicy
                    $att->Tags = []; // Inicjalizacja pustej tablicy
                    $att->length = $row['att.length']; // Dodanie length do atrybutów

                    $vis = $row['att.visibility'] ?? 'Catalog, Search';
                    if (str_contains($vis, 'Not Visible')) $att->visibility = 1;
                    elseif (str_contains($vis, 'Catalog, Search')) $att->visibility = 4;
                    elseif (str_contains($vis, 'Catalog')) $att->visibility = 2;
                    elseif (str_contains($vis, 'Search')) $att->visibility = 3;
                    else $att->visibility = 4;

                    $att->short_description = '';
                    $att->description = '';

                    $gallery = [];
                    if (!empty($item->url)) {
                        $galleryData = self::scrapeImages($item->url, $item->sku);
                        if (!empty($galleryData)) {
                            $gallery = $galleryData['gallery'];
                            if (isset($galleryData['firstImg']) && !empty($galleryData['firstImg'])) {
                                $att->image = $galleryData['firstImg'];
                                $att->small_image = $galleryData['firstImg'];
                                $att->thumbnail = $galleryData['firstImg'];
                                $att->swatch_image = $galleryData['firstImg'];
                            }
                            if (isset($galleryData['short_description'])) {
                                $att->short_description = $galleryData['short_description'];
                            }
                            if (isset($galleryData['description'])) {
                                $att->description = $galleryData['description'];
                            }
                        }
                    }

                    $item->catalog_product_attribute = [$att];
                    $item->catalog_product_entity_media_gallery = $gallery;

                    $item->import_price = $row['att.dealer_price'];
                    $item->import_currency = 'EUR';
                    $item->qnty = $row['att.Iloscwdomu'] ?? 0;

                    $items[] = $item;
                }
                $i++;
            }
            fclose($handle);
        }

        $json = json_encode($items);
        file_put_contents(BP . "/Configs/automow_data.json", $json);
    }

    public static function find($skuExternal, $array)
    {
        foreach ($array as $item) {
            if ($item->sku == $skuExternal) {
                return $item;
            }
        }
        return null;
    }

    public static function prepareAutomowJsonData(): void
    {
        $categories = ScraperService::getAmRobotsCategory();

        $string = file_get_contents(BP . "/Configs/automow_data.json");
        $table = json_decode($string);
        $all = [];

        $skus = [];
        $tableSimple = [];
        $tableConfigurable = [];
        $tableDescriptions = [];

        // W nowej logice SKU jest już w pliku JSON (z AM_Processed.csv), więc nie musimy go generować.
        // Ale musimy zidentyfikować produkty konfigurowalne i proste.
        // W AM_Processed.csv mamy kolumnę type_id.

        // Grupowanie produktów prostych, które należą do konfigurowalnego
        $configurableProducts = [];
        $simpleProducts = [];

        foreach ($table as $item) {
            if ($item->type_id === 'configurable') {
                $configurableProducts[$item->sku] = $item;
            } else {
                $simpleProducts[] = $item;
            }
        }

        $rowId = 1;
        $simpleProductsMap = [];

        // Przetwarzanie produktów prostych
        foreach ($simpleProducts as $simple) {
            $simpleProductsMap[$simple->sku] = $simple;
            $simple->rowId = $rowId;
            $simple->importType = 'simple';

            // Upewnij się, że atrybuty są ustawione
            if (!isset($simple->catalog_product_attribute[0])) {
                $simple->catalog_product_attribute[0] = new stdClass();
            }
            $attr = $simple->catalog_product_attribute[0];

            // Inpost Dimension (już jest w JSON z saveAutomowJsonData, ale dla pewności)
            if (empty($attr->inpost_dimension)) {
                $inpostDimension = self::calculateInpostDimension($attr->dimension ?? '', $attr->weight ?? '');
                if ($inpostDimension) {
                    $attr->inpost_dimension = $inpostDimension;
                }
            }

            // Meta dane
            $attr->meta_title = $attr->name;
            $attr->meta_keyword = implode(',', $attr->Tags ?? []);
            $attr->url_key = self::generateUrlKey($attr->name);

            // Opisy do oddzielnego pliku
            $sd = new stdClass();
            $sd->sku = $simple->sku; // Używamy SKU produktu, a nie external_sku, bo to jest klucz w Magento
            $sd->description = $attr->description ?? '';
            $tableDescriptions[] = $sd;

            // Media
            if (isset($simple->catalog_product_entity_media_gallery)) {
                foreach ($simple->catalog_product_entity_media_gallery as $img) {
                    $img->product_id = $simple->sku;
                    if (isset($img->catalog_product_entity_media_gallery_value[0])) {
                        $img->catalog_product_entity_media_gallery_value[0]->entity_id = $simple->sku;
                    }
                }
            }

            // Inventory
            $s1 = new stdClass();
            $s1->source_code = "am_robots";
            $s1->sku = $simple->sku;
            if (isset($attr->price) && floatval($attr->price) > 0) {
                $s1->quantity = 10;
            } else {
                $s1->quantity = 0;
            }
            $s1->status = 1;

            $s2 = new stdClass();
            $s2->source_code = "gardenlawn_source";
            $s2->sku = $simple->sku;
            $s2->quantity = intval($simple->qnty ?? 0);
            $s2->status = 1;

            $simple->inventory_source_item = [$s1, $s2];

            $s3 = new stdClass();
            $s3->stock_id = 1;
            $s3->qty = 0;
            $s3->product_sku = $simple->sku;
            $simple->inventory_stock_item = [$s3];

            $s4 = new stdClass();
            $s4->stock_status = 1;
            $s4->stock_id = 2;
            $s4->product_sku = $simple->sku;
            $simple->inventory_stock_status = [$s4];

            // Kategorie
            $cat = ScraperService::find($simple->skuExternal, $categories);
            if ($cat != null) {
                $attr->compatibility = $cat->company;
                $c = new stdClass();
                $c->categories = $cat->category;
                $c->product_sku = $simple->sku;
                $simple->product_category_relation = [$c];
            }

            $tableSimple[] = $simple;
            $all[] = $simple;
            $rowId++;
        }

        // Przetwarzanie produktów konfigurowalnych
        // Musimy znaleźć dzieci dla każdego konfigurowalnego produktu.
        // W AM_Processed.csv mamy att.parent_sku dla produktów prostych, ale tutaj w JSON tego nie mamy bezpośrednio w głównym obiekcie,
        // chyba że dodaliśmy to w saveAutomowJsonData.
        // Ale w saveAutomowJsonData nie mapowaliśmy att.parent_sku.
        // Musimy to poprawić w saveAutomowJsonData lub tutaj polegać na logice nazewnictwa SKU (np. AMROBOTSC001-50m ma rodzica AMROBOTSC001).
        // Lepszym podejściem jest ponowne wczytanie CSV, aby znać relacje, LUB (prościej) założenie, że SKU proste zaczyna się od SKU rodzica + myślnik.
        // Jednak w AM_Processed.csv mamy kolumnę att.parent_sku. Dodajmy ją do saveAutomowJsonData.

        // Wróćmy do saveAutomowJsonData i dodajmy parent_sku.
        // Ale użytkownik prosił o przerobienie prepareAutomowJsonData.
        // Załóżmy, że w JSON mamy już poprawne SKU.
        // W AM_Processed.csv:
        // AMROBOTSC001-50m;simple;AMROBOTSC001;...
        // AMROBOTSC001;configurable;;...

        // Musimy wiedzieć, które simple należą do którego configurable.
        // Możemy to zrobić iterując po simple i sprawdzając czy ich SKU zaczyna się od SKU jakiegoś configurable.
        // Albo lepiej: wczytajmy mapę relacji z CSV jeszcze raz tutaj, bo w JSON tego nie ma.

        $relations = [];
        $csvFile = BP . "/app/code/GardenLawn/Core/Configs/AM_Processed.csv";
        if (file_exists($csvFile)) {
            $handle = fopen($csvFile, "r");
            $header = fgetcsv($handle, 0, ";");
            while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
                if (count($data) == count($header)) {
                    $row = array_combine($header, $data);
                    if (!empty($row['att.parent_sku'])) {
                        $relations[$row['att.parent_sku']][] = $row['sku'];
                    }
                }
            }
            fclose($handle);
        }

        foreach ($configurableProducts as $confSku => $conf) {
            $conf->rowId = $rowId;
            $conf->importType = 'configurable';
            $conf->has_options = 1;
            $conf->required_options = 1;

            if (!isset($conf->catalog_product_attribute[0])) {
                $conf->catalog_product_attribute[0] = new stdClass();
            }
            $attr = $conf->catalog_product_attribute[0];

            $attr->visibility = 4;
            $attr->has_options = 1;
            $attr->required_options = 1;
            $attr->meta_title = $attr->name;
            $attr->url_key = self::generateUrlKey($attr->name);

            // Media dla configurable (bierzemy z pierwszego dziecka lub z samego siebie jeśli ma URL)
            // W saveAutomowJsonData pobieramy zdjęcia jeśli jest URL. Configurable w CSV ma URL.
            if (isset($conf->catalog_product_entity_media_gallery)) {
                 foreach ($conf->catalog_product_entity_media_gallery as $img) {
                    $img->product_id = $conf->sku;
                    if (isset($img->catalog_product_entity_media_gallery_value[0])) {
                        $img->catalog_product_entity_media_gallery_value[0]->entity_id = $conf->sku;
                    }
                }
            }

            $collectedTags = [];
            if (isset($attr->Tags) && is_array($attr->Tags)) {
                $collectedTags = $attr->Tags;
            }

            // Relacje (dzieci)
            if (isset($relations[$confSku])) {
                $childrenSkus = $relations[$confSku];
                $descriptionSet = false;

                // Pobieranie opisu z pierwszego dziecka, które ma niepuste opisy
                foreach ($childrenSkus as $childSku) {
                    if (isset($simpleProductsMap[$childSku])) {
                        $child = $simpleProductsMap[$childSku];
                        if (isset($child->catalog_product_attribute[0])) {
                            $childAttr = $child->catalog_product_attribute[0];

                            // Zbieranie tagów z dzieci
                            if (!empty($childAttr->Tags) && is_array($childAttr->Tags)) {
                                foreach ($childAttr->Tags as $tag) {
                                    $tag = trim($tag);
                                    if ($tag !== '') {
                                        $collectedTags[] = $tag;
                                    }
                                }
                            }

                            if (!$descriptionSet) {
                                $childShortDesc = $childAttr->short_description ?? '';
                                $childDesc = $childAttr->description ?? '';

                                if (!empty($childShortDesc) || !empty($childDesc)) {
                                    $attr->short_description = $childShortDesc;
                                    $attr->description = $childDesc;
                                    $descriptionSet = true;
                                }
                            }
                        }
                    }
                }

                $s = new stdClass();
                $s->product_id = $conf->sku;
                $op = [];
                foreach ($childrenSkus as $childSku) {
                    $o = new stdClass();
                    $o->parent_id = $conf->sku;
                    $o->sku = $childSku;
                    $op[] = $o;
                }
                $s->catalog_product_super_attribute_link = $op;
                $conf->catalog_product_super_attribute = [$s];
            }

            $attr->meta_keyword = implode(',', array_unique($collectedTags));

            // Kategorie
            $cat = ScraperService::find($conf->skuExternal, $categories);
            if ($cat != null) {
                $attr->compatibility = $cat->company;
                $c = new stdClass();
                $c->categories = $cat->category;
                $c->product_sku = $conf->sku;
                $conf->product_category_relation = [$c];
            }

            $tableConfigurable[] = $conf;
            $all[] = $conf;
            $rowId++;
        }

        $json = json_encode($all);
        file_put_contents(BP . "/app/code/GardenLawn/Core/Configs/automow_prepared_data.json", $json);

        $json = json_encode($tableConfigurable);
        file_put_contents(BP . "/app/code/GardenLawn/Core/Configs/automow_prepared_configurable_data.json", $json);

        $json = json_encode($tableSimple);
        file_put_contents(BP . "/app/code/GardenLawn/Core/Configs/automow_prepared_single_data.json", $json);

        $json = json_encode($tableDescriptions);
        file_put_contents(BP . "/app/code/GardenLawn/Core/Configs/automow_prepared_description_data.json", $json);
    }

    public static function getAmRobotsCategory(): array
    {
        $reader = new Xls();
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load(BP . "/Configs/amrobots_category.xls");
        $worksheet = $spreadsheet->getActiveSheet();

        $i = 0;

        $items = [];

        foreach ($worksheet->getRowIterator() as $row) {
            if ($i != 0 && $i < 2000) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                $item = new stdClass();
                foreach ($cellIterator as $cell) {
                    switch ($cell->getColumn()) {
                        case 'A':
                            $item->sku = $cell->getCalculatedValue();
                            break;
                        case 'B':
                            $item->category = $cell->getCalculatedValue();
                            break;
                        case 'C':
                            $item->company = $cell->getCalculatedValue();
                            break;
                    }
                }
                $items[] = $item;
            }
            $i++;
        }

        return $items;
    }

    public static function checkRemoteFile($url): bool
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            // don't download content
            curl_setopt($ch, CURLOPT_NOBODY, 1);
            curl_setopt($ch, CURLOPT_FAILONERROR, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            $result = curl_exec($ch);
            curl_close($ch);
            if ($result !== false) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $ex) {
            return false;
        }
    }

    public static function calculateInpostDimension($dimension, $weight): ?string
    {
        if (empty($dimension) || empty($weight)) {
            return null;
        }

        $weight = (float)str_replace(',', '.', $weight);
        if ($weight > 25) {
            return null;
        }

        $dims = explode('*', str_replace(',', '.', $dimension));
        if (count($dims) !== 3) {
            return null;
        }

        $dims = array_map('floatval', $dims);
        sort($dims);

        // A: 8 x 38 x 64 -> sorted: 8, 38, 64
        if ($dims[0] <= 8 && $dims[1] <= 38 && $dims[2] <= 64) {
            return 'Dimension A';
        }

        // B: 19 x 38 x 64 -> sorted: 19, 38, 64
        if ($dims[0] <= 19 && $dims[1] <= 38 && $dims[2] <= 64) {
            return 'Dimension B';
        }

        // C: 41 x 38 x 64 -> sorted: 38, 41, 64
        if ($dims[0] <= 38 && $dims[1] <= 41 && $dims[2] <= 64) {
            return 'Dimension C';
        }

        // D: 50 x 50 x 80 -> sorted: 50, 50, 80
        if ($dims[0] <= 50 && $dims[1] <= 50 && $dims[2] <= 80) {
            return 'Dimension D';
        }

        return null;
    }

    public static function generateUrlKey($name): string
    {
        $table = [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
            'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'E', 'Ł' => 'L', 'Ń' => 'N', 'Ó' => 'O', 'Ś' => 'S', 'Ź' => 'Z', 'Ż' => 'Z'
        ];
        $name = strtr($name, $table);
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9]+/', '-', $name);
        $name = trim($name, '-');
        return $name;
    }
}
