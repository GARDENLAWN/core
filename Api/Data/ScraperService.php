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

    /**
     * @throws InvalidSelectorException
     */
    public static function saveAutomowJsonData(): void
    {
        $reader = new Xls();
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load(BP . "/Configs/automow.xls");
        $worksheet = $spreadsheet->getActiveSheet();

        $i = 0;

        $items = [];

        foreach ($worksheet->getRowIterator() as $row) {
            if ($i != 0 && $i < 2000) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                $item = new stdClass();
                $item->url = '';
                foreach ($cellIterator as $cell) {
                    switch ($cell->getColumn()) {
                        case 'A':
                            $item->url = $cell->getCalculatedValue();
                            if (!empty($item->url)) {
                                $item = self::scraper($item);
                            }
                            break;
                        case 'B':
                            $item->skuExternal = $cell->getCalculatedValue();
                            break;
                        case 'D':
                            $item->contains = (string)$cell->getCalculatedValue();
                            try {
                                if (empty($item->url)) {
                                    $item = self::scraper($item);
                                }
                                if ($item->catalog_product_attribute && $item->catalog_product_attribute[0] != null) {
                                    $item->catalog_product_attribute[0]->sku = $item->sku;
                                }
                            } catch (Exception $e) {
                                echo '<p><strong>' . $e->getMessage() . '</strong></p>';
                            }
                            break;
                        case 'F':
                            if ($item != null && property_exists($item, 'catalog_product_attribute') && $item->catalog_product_attribute[0] != null) {
                                $item->catalog_product_attribute[0]->price = (string)$cell->getCalculatedValue();
                            }
                            $item->import_price = (string)$cell->getCalculatedValue();
                            $item->import_currency = 'EUR';
                            break;
                        case 'G':
                            $item->dealer_price = (string)$cell->getCalculatedValue();
                            break;
                        case 'H':
                            $item->distributor_price = (string)$cell->getCalculatedValue();
                            break;
                        case 'I':
                            $item->dimension = (string)$cell->getCalculatedValue();
                            break;
                        case 'J':
                            $item->weight = (string)$cell->getCalculatedValue();
                            break;
                        case 'O':
                            $item->commodity_code = (string)$cell->getCalculatedValue();
                            break;
                        case 'T':
                            $item->GTIN13 = (string)$cell->getCalculatedValue();
                            break;
                        case 'U':
                            $item->CategoryCustom = (string)$cell->getCalculatedValue();
                            break;
                        case 'V':
                            $item->qnty = $cell->getCalculatedValue();
                            break;
                    }
                }

                if (property_exists($item, 'catalog_product_attribute')) {
                    $item->catalog_product_attribute[0]->import_price = $item->import_price;
                    $item->catalog_product_attribute[0]->import_currency = $item->import_currency;
                    $item->catalog_product_attribute[0]->dealer_price = $item->dealer_price;
                    $item->catalog_product_attribute[0]->distributor_price = $item->distributor_price;
                    $item->catalog_product_attribute[0]->dimension = $item->dimension;
                    $item->catalog_product_attribute[0]->weight = $item->weight;
                    $item->catalog_product_attribute[0]->external_sku = $item->skuExternal;
                    $item->catalog_product_attribute[0]->commodity_code = $item->commodity_code;
                    $item->catalog_product_attribute[0]->GTIN13 = $item->GTIN13;
                }

                $items[] = $item;
            }
            $i++;
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

    public static function getSkuMap(): array
    {
        $path = BP . "/app/code/GardenLawn/Core/Configs/amrobots-maps.json";
        if (!file_exists($path)) {
            return ['gtin' => [], 'name' => [], 'skus' => []];
        }
        $string = file_get_contents($path);
        $data = json_decode($string);
        $gtinMap = [];
        $nameMap = [];
        $skus = [];
        if ($data) {
            foreach ($data as $item) {
                if (isset($item->catalog_product_attribute[0])) {
                    $attr = $item->catalog_product_attribute[0];
                    if (!empty($attr->sku)) {
                        $skus[$attr->sku] = true;
                    }
                    if (!empty($attr->GTIN13)) {
                        $gtinMap[$attr->GTIN13] = $attr->sku;
                    }
                    if (!empty($attr->name)) {
                        $nameMap[$attr->name] = $attr->sku;
                    }
                }
            }
        }
        return ['gtin' => $gtinMap, 'name' => $nameMap, 'skus' => $skus];
    }

    public static function prepareAutomowJsonData(): void
    {
        $categories = ScraperService::getAmRobotsCategory();
        $maps = ScraperService::getSkuMap();
        $mappedSkus = $maps['skus'];

        $string = file_get_contents(BP . "/Configs/automow_data.json");
        $table = json_decode($string);
        $all = [];

        $skus = [];
        $tableSimple = [];
        $tableConfigurable = [];
        $tableDescriptions = [];

        foreach ($table as $item) {
            if (property_exists($item, 'sku') && $item->skuExternal != null) {
                $item->importType = 'single';
                $skus[] = $item->sku;
            }
        }

        $configurable = array_count_values($skus);

        $configurableNumber = 1;
        $rowId = 1;
        foreach ($configurable as $sku => $count) {
            if ($count > 1) {
                $name = '';
                $options = null;
                $options = [];
                $current = new stdClass();

                $parentName = '';
                foreach ($table as $i) {
                     if (property_exists($i, 'sku') && $i->sku == $sku) {
                         $parentName = $i->catalog_product_attribute[0]->name;
                         break;
                     }
                }

                $parentSku = '';
                if (isset($maps['name'][$parentName])) {
                    $parentSku = $maps['name'][$parentName];
                } else {
                     $maskSku = '';
                     do {
                         $maskSku = "AMROBOTSC" . substr("000" . $configurableNumber, -3);
                         $exists = isset($mappedSkus[$maskSku]);
                         if ($exists) {
                             $configurableNumber++;
                         }
                     } while ($exists);
                     $parentSku = $maskSku;
                     $configurableNumber++;
                }

                foreach ($table as $i) {
                    if (property_exists($i, 'sku') && $i->sku == $sku) {
                        $current = clone $i;
                        $tmp = $i;
                        $tmp->rowId = $rowId;

                        $variationName = $tmp->catalog_product_attribute[0]->name . "-" . $tmp->contains;
                        $variationSku = '';
                        $gtin = $tmp->GTIN13 ?? ($tmp->catalog_product_attribute[0]->GTIN13 ?? '');

                        if (!empty($gtin) && isset($maps['gtin'][$gtin])) {
                            $variationSku = $maps['gtin'][$gtin];
                        } elseif (isset($maps['name'][$variationName])) {
                            $variationSku = $maps['name'][$variationName];
                        } else {
                            $variationSku = $parentSku . "-" . str_replace(' ', '', $tmp->contains);
                        }

                        $tmp->sku = $variationSku;
                        $options[] = $tmp->sku;
                        $tmp->has_options = 0;
                        $tmp->required_options = 0;
                        $name = $tmp->catalog_product_attribute[0]->name;
                        $tmp->catalog_product_attribute[0]->name = $tmp->catalog_product_attribute[0]->name . "-" . $tmp->contains;
                        $tmp->catalog_product_attribute[0]->sku = $tmp->sku;
                        $tmp->catalog_product_attribute[0]->visibility = 1;
                        $tmp->catalog_product_attribute[0]->length = $tmp->contains;
                        $tmp->catalog_product_attribute[0]->has_options = 0;
                        $tmp->catalog_product_attribute[0]->required_options = 0;
                        $tmp->catalog_product_attribute[0]->external_sku = $tmp->skuExternal;

                        $inpostDimension = self::calculateInpostDimension($tmp->catalog_product_attribute[0]->dimension, $tmp->catalog_product_attribute[0]->weight);
                        if ($inpostDimension) {
                            $tmp->catalog_product_attribute[0]->inpost_dimension = $inpostDimension;
                        }

                        $tmp->catalog_product_attribute[0]->meta_title = $tmp->catalog_product_attribute[0]->name;
                        $tmp->catalog_product_attribute[0]->meta_keyword = implode(',', $tmp->catalog_product_attribute[0]->Tags);

                        $sd = new stdClass();
                        $sd->sku = $tmp->skuExternal;
                        $sd->description = $tmp->catalog_product_attribute[0]->short_description;
                        $tableDescriptions[] = $sd;

                        $tmp->importType = 'simple';
                        foreach ($tmp->catalog_product_entity_media_gallery as $img) {
                            $img->product_id = $tmp->sku;
                            $img->catalog_product_entity_media_gallery_value[0]->entity_id = $tmp->sku;
                        }

                        $s1 = new stdClass();
                        $s1->source_code = "am_robots";
                        $s1->sku = $tmp->sku;
                        $s1->quantity = 10;
                        $s1->status = 1;

                        $s2 = new stdClass();
                        $s2->source_code = "gardenlawn_source";
                        $s2->sku = $tmp->sku;
                        $s2->quantity = $tmp->qnty ?? 0;
                        $s2->status = 1;

                        $source = [$s1, $s2];
                        $tmp->inventory_source_item = $source;

                        $s3 = new stdClass();
                        $s3->stock_id = 1;
                        $s3->qty = 0;
                        $s3->product_sku = $tmp->sku;
                        $tmp->inventory_stock_item = [$s3];

                        $s4 = new stdClass();
                        $s4->stock_status = 1;
                        $s4->stock_id = 2;
                        $s4->product_sku = $tmp->sku;
                        $tmp->inventory_stock_status = [$s4];

                        $cat = ScraperService::find($tmp->skuExternal, $categories);
                        if ($cat != null) {
                            $tmp->catalog_product_attribute[0]->compatibility = $cat->company;
                            $c = new stdClass();
                            $c->categories = $cat->category;
                            $c->product_sku = $tmp->sku;
                            $tmp->product_category_relation = [$c];
                        }

                        $tableSimple[] = $tmp;
                        $all[] = $tmp;
                        $rowId++;
                    }
                }

                $configurable = new stdClass();

                $configurable->rowId = $rowId;
                $configurable->sku = $parentSku;
                $configurable->attribute_set_id = 11;
                $configurable->type_id = 'configurable';
                $configurable->has_options = 1;
                $configurable->required_options = 1;

                $a = new stdClass();
                $a->sku = $configurable->sku;
                $a->name = $name;
                $a->CategoryWords = $current->catalog_product_attribute[0]->CategoryWords;
                $a->Tags = $current->catalog_product_attribute[0]->Tags;
                $a->short_description = $current->catalog_product_attribute[0]->short_description;
                $a->description = $current->catalog_product_attribute[0]->description;
                $a->image = $current->catalog_product_attribute[0]->image;
                $a->small_image = $current->catalog_product_attribute[0]->small_image;
                $a->thumbnail = $current->catalog_product_attribute[0]->thumbnail;
                $a->swatch_image = $current->catalog_product_attribute[0]->swatch_image;
                $a->visibility = 4;
                $a->has_options = 1;
                $a->required_options = 1;

                $configurable->catalog_product_attribute = [$a];

                $configurable->catalog_product_attribute[0]->meta_title = $configurable->catalog_product_attribute[0]->name;
                $configurable->catalog_product_attribute[0]->meta_keyword = implode(',', $configurable->catalog_product_attribute[0]->Tags);

                $g = [];
                foreach ($current->catalog_product_entity_media_gallery as $img) {
                    $i = new stdClass();
                    $i->value = $img->value;
                    $i->media_type = $img->media_type;
                    $i->product_id = $configurable->sku;

                    $v = new stdClass();
                    $v->entity_id = $configurable->sku;
                    $v->position = $img->catalog_product_entity_media_gallery_value[0]->position;
                    $i->catalog_product_entity_media_gallery_value = [$v];

                    $g[] = $i;
                }

                $configurable->catalog_product_entity_media_gallery = $g;

                $s = new stdClass();
                $s->product_id = $configurable->sku;

                $op = [];

                foreach ($options as $i) {
                    $o = new stdClass();
                    $o->parent_id = $configurable->sku;
                    $o->sku = $i;
                    $op[] = $o;
                }

                $s->catalog_product_super_attribute_link = $op;
                $configurable->catalog_product_super_attribute = [$s];

                $cat = ScraperService::find($current->skuExternal, $categories);
                if ($cat != null) {
                    $configurable->catalog_product_attribute[0]->compatibility = $cat->company;
                    $c = new stdClass();
                    $c->categories = $cat->category;
                    $c->product_sku = $configurable->sku;
                    $configurable->product_category_relation = [$c];
                }

                $configurable->importType = 'configurable';

                $table[] = $configurable;
                $tableConfigurable[] = $configurable;
                $all[] = $configurable;
                $rowId++;
            } else {
                foreach ($table as $tmp) {
                    if (property_exists($tmp, 'sku') && $tmp->sku == $sku) {

                        $simpleSku = '';
                        $gtin = $tmp->GTIN13 ?? ($tmp->catalog_product_attribute[0]->GTIN13 ?? '');
                        if (!empty($gtin) && isset($maps['gtin'][$gtin])) {
                            $simpleSku = $maps['gtin'][$gtin];
                        } elseif (isset($maps['name'][$tmp->catalog_product_attribute[0]->name])) {
                             $simpleSku = $maps['name'][$tmp->catalog_product_attribute[0]->name];
                        } else {
                             $maskSku = '';
                             do {
                                 $maskSku = "AMROBOTSS" . substr("000" . $configurableNumber, -3);
                                 $exists = isset($mappedSkus[$maskSku]);
                                 if ($exists) {
                                     $configurableNumber++;
                                 }
                             } while ($exists);
                             $simpleSku = $maskSku;
                             $configurableNumber++;
                        }

                        $tmp->sku = $simpleSku;
                        $tmp->rowId = $rowId;
                        $tmp->catalog_product_attribute[0]->sku = $tmp->sku;
                        $tmp->catalog_product_attribute[0]->external_sku = $tmp->skuExternal;

                        $inpostDimension = self::calculateInpostDimension($tmp->catalog_product_attribute[0]->dimension, $tmp->catalog_product_attribute[0]->weight);
                        if ($inpostDimension) {
                            $tmp->catalog_product_attribute[0]->inpost_dimension = $inpostDimension;
                        }

                        $tmp->catalog_product_attribute[0]->meta_title = $tmp->catalog_product_attribute[0]->name;
                        $tmp->catalog_product_attribute[0]->meta_keyword = implode(',', $tmp->catalog_product_attribute[0]->Tags);

                        $sd = new stdClass();
                        $sd->sku = $tmp->skuExternal;
                        $sd->description = $tmp->catalog_product_attribute[0]->short_description;
                        $tableDescriptions[] = $sd;

                        foreach ($tmp->catalog_product_entity_media_gallery as $img) {
                            $img->product_id = $tmp->sku;
                            $img->catalog_product_entity_media_gallery_value[0]->entity_id = $tmp->sku;
                        }

                        $s1 = new stdClass();
                        $s1->source_code = "am_robots";
                        $s1->sku = $tmp->sku;
                        $s1->quantity = 10;
                        $s1->status = 1;

                        $s2 = new stdClass();
                        $s2->source_code = "gardenlawn_source";
                        $s2->sku = $tmp->sku;
                        $s2->quantity = $tmp->qnty ?? 0;
                        $s2->status = 1;

                        $source = [$s1, $s2];
                        $tmp->inventory_source_item = $source;

                        $s3 = new stdClass();
                        $s3->stock_id = 1;
                        $s3->qty = 0;
                        $s3->product_sku = $tmp->sku;
                        $tmp->inventory_stock_item = [$s3];

                        $s4 = new stdClass();
                        $s4->stock_status = 1;
                        $s4->stock_id = 2;
                        $s4->product_sku = $tmp->sku;
                        $tmp->inventory_stock_status = [$s4];

                        $cat = ScraperService::find($tmp->skuExternal, $categories);
                        if ($cat != null) {
                            $tmp->catalog_product_attribute[0]->compatibility = $cat->company;
                            $c = new stdClass();
                            $c->categories = $cat->category;
                            $c->product_sku = $tmp->sku;
                            $tmp->product_category_relation = [$c];
                        }

                        $tableSimple[] = $tmp;
                        $all[] = $tmp;
                        $rowId++;
                    }
                }
            }
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

    public static function calculateInpostDimension($dimension, $weight)
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
}
