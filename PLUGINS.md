# Lista Pluginów w GardenLawn

Poniżej znajduje się lista pluginów zdefiniowanych w modułach `GardenLawn` wraz z ich opisem.

## GardenLawn_Core

### `etc/di.xml`

<table width="100%">
<thead>
<tr>
<th width="30%">Nazwa Pluginu</th>
<th width="40%">Klasa Pluginu</th>
<th width="30%">Cel</th>
</tr>
</thead>
<tbody>
<tr>
<td style="word-break: break-all;">gardenlawn_core_framework_file_uploader</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\Framework\File\UploaderPlugin</td>
<td>Modyfikuje zachowanie uploadera plików (prawdopodobnie walidacja lub obsługa nazw plików).</td>
</tr>
<tr>
<td style="word-break: break-all;">gardenlawn_core_webp_support</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\Framework\Image\Adapter\Gd2Plugin</td>
<td>Dodaje wsparcie dla formatu WebP w adapterze obrazów GD2.</td>
</tr>
<tr>
<td style="word-break: break-all;">gardenlawn_core_catalog_asset_image_webp</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\Catalog\Model\View\Asset\ImagePlugin</td>
<td>Zapewnia obsługę WebP dla assetów obrazów katalogowych.</td>
</tr>
<tr>
<td style="word-break: break-all;">gardenlawn_core_catalog_asset_image_transformation_webp</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\Catalog\Model\View\Asset\ImageTransformationPlugin</td>
<td>Obsługuje transformacje obrazów do formatu WebP.</td>
</tr>
<tr>
<td style="word-break: break-all;">gardenlawn_core_wysiwyg_storage_plugin</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\Cms\Model\Wysiwyg\Images\StoragePlugin</td>
<td>Rozszerza listę dozwolonych rozszerzeń w uploaderze WYSIWYG.</td>
</tr>
<tr>
<td style="word-break: break-all;">gardenlawn_core_wysiwyg_storage_resize_file_plugin</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\Cms\Model\Wysiwyg\Images\StorageResizeFilePlugin</td>
<td>Wyzwala synchronizację lub zmianę rozmiaru po uploadzie pliku w WYSIWYG.</td>
</tr>
<tr>
<td style="word-break: break-all;">gardenlawn_core_whitelist_modern_image_formats</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\MediaStorage\Model\File\Validator\NotProtectedExtensionPlugin</td>
<td>Dodaje nowoczesne formaty obrazów (np. WebP, AVIF) do białej listy walidatora rozszerzeń.</td>
</tr>
<tr>
<td style="word-break: break-all;">gardenlawn_core_contact_post_plugin</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\ContactPostPlugin</td>
<td>Modyfikuje działanie kontrolera wysyłania formularza kontaktowego (np. logowanie, dodatkowa walidacja).</td>
</tr>
<tr>
<td style="word-break: break-all;">gardenlawn_core_wishlist_customer_data_plugin</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\Wishlist\CustomerData\WishlistPlugin</td>
<td>Modyfikuje dane wishlisty zwracane do sekcji customer data (np. dla frontendowych widgetów).</td>
</tr>
<tr>
<td style="word-break: break-all;">gardenlawn_core_last_ordered_items_plugin</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\LastOrderedItemsPlugin</td>
<td>Modyfikuje sekcję <code>LastOrderedItems</code> w customer data.</td>
</tr>
<tr>
<td style="word-break: break-all;">gardenlawn_core_amasty_file_uploader_plugin</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\Amasty\FileUploaderPlugin</td>
<td>Rozszerza funkcjonalność uploadera plików w module importu Amasty.</td>
</tr>
</tbody>
</table>

### `etc/frontend/di.xml`

<table width="100%">
<thead>
<tr>
<th width="30%">Nazwa Pluginu</th>
<th width="40%">Klasa Pluginu</th>
<th width="30%">Cel</th>
</tr>
</thead>
<tbody>
<tr>
<td style="word-break: break-all;">GardenLawn_Core_Plugin_Hyva_Checkout_Model_Form_EntityFormSaveService_EavAttributeBillingAddressPlugin</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\Hyva\Checkout\Model\Form\EntityFormSaveService\EavAttributeBillingAddressPlugin</td>
<td>Modyfikuje zapis atrybutów EAV dla adresu rozliczeniowego w Hyva Checkout.</td>
</tr>
<tr>
<td style="word-break: break-all;">GardenLawn_Core_Plugin_Customer_Address_FormPostPlugin</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\Customer\Address\FormPostPlugin</td>
<td>Modyfikuje działanie kontrolera zapisu adresu klienta.</td>
</tr>
<tr>
<td style="word-break: break-all;">GardenLawn_Core_Plugin_Hyva_Checkout_Magewire_Checkout_AddressView_BillingDetails_AddressListPlugin</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\Hyva\Checkout\Magewire\Checkout\AddressView\BillingDetails\AddressListPlugin</td>
<td>Modyfikuje widok listy adresów rozliczeniowych w Magewire (Hyva Checkout).</td>
</tr>
<tr>
<td style="word-break: break-all;">GardenLawn_Core_Plugin_Hyva_Checkout_ViewModel_Checkout_AddressView_AddressList_AddressListBillingPlugin</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\Hyva\Checkout\ViewModel\Checkout\AddressView\AddressList\AddressListBillingPlugin</td>
<td>Modyfikuje ViewModel listy adresów rozliczeniowych w Hyva Checkout.</td>
</tr>
<tr>
<td style="word-break: break-all;">GardenLawn_Core_Plugin_Hyva_Checkout_ViewModel_Checkout_AddressView_AddressList_AddressListBillingCanCreatePlugin</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\Hyva\Checkout\ViewModel\Checkout\AddressView\AddressList\AddressListBillingCanCreatePlugin</td>
<td>Kontroluje możliwość tworzenia nowego adresu rozliczeniowego w Hyva Checkout.</td>
</tr>
<tr>
<td style="word-break: break-all;">GardenLawn_Core_Plugin_Hyva_Checkout_Model_ConfigData_HyvaThemes_SystemConfigBillingPlugin</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\Hyva\Checkout\Model\ConfigData\HyvaThemes\SystemConfigBillingPlugin</td>
<td>Modyfikuje dane konfiguracyjne dla adresu rozliczeniowego w Hyva Checkout.</td>
</tr>
<tr>
<td style="word-break: break-all;">GardenLawn_Core::override_santander_shop_id</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\ViewModel\Santander\RatesPlugin</td>
<td>Nadpisuje logikę pobierania ID sklepu dla rat Santander.</td>
</tr>
<tr>
<td style="word-break: break-all;">GardenLawn_Core::santander_payment_availability</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\Model\Santander\AvailabilityPlugin</td>
<td>Kontroluje dostępność metody płatności Santander.</td>
</tr>
<tr>
<td style="word-break: break-all;">GardenLawn_Core::santander_gross_price_fix</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\ViewModel\Santander\InstallmentViewModelPlugin</td>
<td>Poprawia obliczanie ceny brutto w symulatorze rat Santander.</td>
</tr>
<tr>
<td style="word-break: break-all;">GardenLawn_Core_Plugin_Amasty_SeoHtmlSitemap_Block_Sitemap</td>
<td style="word-break: break-all;">GardenLawn\Core\Plugin\Amasty\SeoHtmlSitemap\Block\SitemapPlugin</td>
<td>Usuwa style inline z mapy strony HTML generowanej przez moduł Amasty.</td>
</tr>
</tbody>
</table>

## GardenLawn_Delivery

### `etc/di.xml`

<table width="100%">
<thead>
<tr>
<th width="30%">Nazwa Pluginu</th>
<th width="40%">Klasa Pluginu</th>
<th width="30%">Cel</th>
</tr>
</thead>
<tbody>
<tr>
<td style="word-break: break-all;">gardenlawn_delivery_add_description</td>
<td style="word-break: break-all;">GardenLawn\Delivery\Plugin\Quote\ShippingMethodConverterPlugin</td>
<td>Dodaje opis do metod dostawy w koszyku (konwerter metod wysyłki).</td>
</tr>
</tbody>
</table>

## GardenLawn_Company

Brak zdefiniowanych pluginów w `etc/di.xml`. Moduł korzysta głównie z `preference` i `virtualType`.

## GardenLawn_MediaGallery

Brak zdefiniowanych pluginów w `etc/di.xml`. Moduł korzysta z `preference`, `virtualType` oraz komend konsoli.

## GardenLawn_AdminCommands

Brak zdefiniowanych pluginów w `etc/di.xml`. Moduł dodaje komendy konsoli.

## GardenLawn_Seo

Brak zdefiniowanych pluginów.

## GardenLawn_LogViewer

Brak zdefiniowanych pluginów.

# Lista Pluginów w Innych Modułach (app/code)

## Aurora_Santander

### `etc/di.xml`

<table width="100%">
<thead>
<tr>
<th width="30%">Nazwa Pluginu</th>
<th width="40%">Klasa Pluginu</th>
<th width="30%">Cel</th>
</tr>
</thead>
<tbody>
<tr>
<td style="word-break: break-all;">installments</td>
<td style="word-break: break-all;">Aurora\Santander\Plugin\Installment</td>
<td>Dodaje funkcjonalność rat do widoku produktu konfigurowalnego.</td>
</tr>
<tr>
<td style="word-break: break-all;">santander_agreement_validator</td>
<td style="word-break: break-all;">Aurora\Santander\Plugin\AgreementsValidator</td>
<td>Waliduje zgody w procesie checkoutu dla Santander.</td>
</tr>
</tbody>
</table>

### `etc/adminhtml/di.xml`

<table width="100%">
<thead>
<tr>
<th width="30%">Nazwa Pluginu</th>
<th width="40%">Klasa Pluginu</th>
<th width="30%">Cel</th>
</tr>
</thead>
<tbody>
<tr>
<td style="word-break: break-all;">admin_system_config_save_plugin_aurora</td>
<td style="word-break: break-all;">Aurora\Santander\Plugin\ConfigPlugin</td>
<td>Wykonuje akcje po zapisie konfiguracji w panelu admina.</td>
</tr>
</tbody>
</table>

## InPost_InPostPay

### `etc/di.xml`

<table width="100%">
<thead>
<tr>
<th width="30%">Nazwa Pluginu</th>
<th width="40%">Klasa Pluginu</th>
<th width="30%">Cel</th>
</tr>
</thead>
<tbody>
<tr>
<td style="word-break: break-all;">update_checkout_agreement_version</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\CheckoutAgreementsRepository\UpdateCheckoutAgreementsVersionPlugin</td>
<td>Aktualizuje wersję zgód checkoutu.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::updateCheckoutAgreementsVersionPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\UpdateCheckoutAgreementsVersionPlugin</td>
<td>Aktualizuje wersję zgód checkoutu (dla interfejsu AgreementInterface).</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::allowRemoteQuoteChangeByInPostPayPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Quote\AllowRemoteQuoteChangeByInPostPayPlugin</td>
<td>Pozwala na zdalną zmianę koszyka przez InPost Pay.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::swaggerGetListOfServicesPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Swagger\SwaggerGetListOfServicesPlugin</td>
<td>Modyfikuje listę usług w Swaggerze.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::swaggerAllowAclResourcePlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Swagger\SwaggerAllowAclResourcePlugin</td>
<td>Zarządza uprawnieniami ACL w Swaggerze.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::orderCompleteIsEnablePlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\IsPaymentMethodEnable</td>
<td>Sprawdza czy metoda płatności jest włączona dla zakończenia zamówienia.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::basketGetIsEnablePlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\IsPaymentMethodEnable</td>
<td>Sprawdza czy metoda płatności jest włączona dla pobierania koszyka.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::basketUpdateIsEnablePlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\IsPaymentMethodEnable</td>
<td>Sprawdza czy metoda płatności jest włączona dla aktualizacji koszyka.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::registerMobileAppBasketEventPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\ApiConnector\Merchant\RegisterMobileAppBasketEventPlugin</td>
<td>Rejestruje zdarzenia koszyka z aplikacji mobilnej.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::basketDeleteIsEnablePlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\IsPaymentMethodEnable</td>
<td>Sprawdza czy metoda płatności jest włączona dla usuwania koszyka.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::basketConfirmationIsEnablePlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\IsPaymentMethodEnable</td>
<td>Sprawdza czy metoda płatności jest włączona dla potwierdzenia koszyka.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::orderCreateIsEnablePlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\IsPaymentMethodEnable</td>
<td>Sprawdza czy metoda płatności jest włączona dla tworzenia zamówienia.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::registerMobileAppOrderPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\ApiConnector\Merchant\RegisterMobileAppOrderPlugin</td>
<td>Rejestruje zamówienia z aplikacji mobilnej.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::addAdditionalAnalyticsOrderParamsToOrderDetailsPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\DataTransfer\OrderToInPostOrder\AddAdditionalAnalyticsOrderParamsToOrderDetailsPlugin</td>
<td>Dodaje parametry analityczne do szczegółów zamówienia.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::orderGetIsEnablePlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\IsPaymentMethodEnable</td>
<td>Sprawdza czy metoda płatności jest włączona dla pobierania zamówienia.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::orderEventIsEnablePlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\IsPaymentMethodEnable</td>
<td>Sprawdza czy metoda płatności jest włączona dla zdarzeń zamówienia.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::addErrorsToBasketPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\AddErrorsToBasketPlugin</td>
<td>Dodaje błędy do koszyka podczas transferu danych.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::checkAndDeactivateNoLongerAvailableConfiguredPromotionsPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\CheckAndDeactivateNoLongerAvailableConfiguredPromotionsPlugin</td>
<td>Sprawdza i dezaktywuje niedostępne promocje.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::CartItemProcessorPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\ConfigurableProduct\CartItemProcessorPlugin</td>
<td>Przetwarza elementy koszyka dla produktów konfigurowalnych.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::zeroQuantityWhenOutOfStockPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\DataTransfer\QuoteToBasket\ZeroQuantityWhenOutOfStockPlugin</td>
<td>Ustawia ilość na zero, gdy produkt nie jest dostępny w magazynie.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::applyCustomPromoPriceForCustomerGroupPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\DataTransfer\QuoteToBasket\ApplyCustomPromoPriceForCustomerGroupPlugin</td>
<td>Aplikuje niestandardowe ceny promocyjne dla grup klientów.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::limitProductAttributeNameAndValueCharactersPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Model\Data\Basket\Product\LimitProductAttributeNameAndValueCharactersPlugin</td>
<td>Ogranicza długość nazw i wartości atrybutów produktu.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::addMagentoModuleVersionHeaderPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Model\Request\AddMagentoModuleVersionHeaderPlugin</td>
<td>Dodaje nagłówek z wersją modułu Magento do żądań.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::checkProductIsSaleableAndUpdateInPostPayBestsellerProductPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\InventoryReservations\CheckProductIsSaleableAndUpdateInPostPayBestsellerProductPlugin</td>
<td>Sprawdza dostępność produktu i aktualizuje bestsellery InPost Pay.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::changeNewCustomerDelegateDataPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\ChangeNewCustomerDelegateDataPlugin</td>
<td>Modyfikuje dane delegata dla nowych klientów.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::addCopyToOrderEmailsForInPostPayAccountEmailPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Model\Order\Email\Container\AddCopyToOrderEmailsForInPostPayAccountEmailPlugin</td>
<td>Dodaje kopię e-maila zamówienia na konto InPost Pay.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::registerIfDigitalQuoteIsAllowedForMagentoConfigPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Service\DataTransfer\QuoteToBasketDataTransfer\RegisterIfDigitalQuoteIsAllowedForMagentoConfigPlugin</td>
<td>Rejestruje czy cyfrowy koszyk jest dozwolony w konfiguracji.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::disableDigitalProductDeliveryIfNotAllowedPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Service\DataTransfer\ProductToInPostProduct\ProductToInPostProductDataTransfer\DisableDigitalProductDeliveryIfNotAllowedPlugin</td>
<td>Wyłącza dostawę produktów cyfrowych, jeśli nie jest dozwolona.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::registerBasketIdBeforeOrderCreatePlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Service\ApiConnector\Merchant\RegisterBasketIdBeforeOrderCreatePlugin</td>
<td>Rejestruje ID koszyka przed utworzeniem zamówienia.</td>
</tr>
</tbody>
</table>

### `etc/frontend/di.xml`

<table width="100%">
<thead>
<tr>
<th width="30%">Nazwa Pluginu</th>
<th width="40%">Klasa Pluginu</th>
<th width="30%">Cel</th>
</tr>
</thead>
<tbody>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::addAdditionalQuoteProductAttributesPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Quote\AddAdditionalQuoteProductAttributesPlugin</td>
<td>Dodaje dodatkowe atrybuty produktu do koszyka.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::registerSaveAddressActionPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Quote\RegisterSaveAddressActionPlugin</td>
<td>Rejestruje akcję zapisu adresu.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::replaceOrderDeliveryEmailWithAccountEmailPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Checkout\ReplaceOrderDeliveryEmailWithAccountEmailPlugin</td>
<td>Zamienia e-mail dostawy na e-mail konta w checkoutcie.</td>
</tr>
</tbody>
</table>

### `etc/adminhtml/di.xml`

<table width="100%">
<thead>
<tr>
<th width="30%">Nazwa Pluginu</th>
<th width="40%">Klasa Pluginu</th>
<th width="30%">Cel</th>
</tr>
</thead>
<tbody>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::registerCurrentOrderPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Adminhtml\Order\View\RegisterCurrentOrderPlugin</td>
<td>Rejestruje bieżące zamówienie w widoku admina.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::renderInPostPayOrderViewInfoPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Adminhtml\Order\View\RenderInPostPayOrderViewInfoPlugin</td>
<td>Renderuje informacje o zamówieniu InPost Pay w panelu admina.</td>
</tr>
</tbody>
</table>

### `etc/webapi_rest/di.xml`

<table width="100%">
<thead>
<tr>
<th width="30%">Nazwa Pluginu</th>
<th width="40%">Klasa Pluginu</th>
<th width="30%">Cel</th>
</tr>
</thead>
<tbody>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::signatureValidationPolicyPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Authorization\SignatureValidationPolicyPlugin</td>
<td>Waliduje podpis w żądaniach REST.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::allowLowerCasePrefixForWebapiRestEndpointsPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Controller\Rest\AllowLowerCasePrefixForWebapiRestEndpointsPlugin</td>
<td>Pozwala na małe litery w prefiksach endpointów REST.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::modifyExceptionResultPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Webapi\Rest\ModifyExceptionResultPlugin</td>
<td>Modyfikuje wynik wyjątków w odpowiedziach JSON.</td>
</tr>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::orderCreateExecutionTimePlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Service\ApiConnector\Merchant\OrderCreateExecutionTimePlugin</td>
<td>Mierzy czas wykonania tworzenia zamówienia.</td>
</tr>
</tbody>
</table>

### `etc/graphql/di.xml`

<table width="100%">
<thead>
<tr>
<th width="30%">Nazwa Pluginu</th>
<th width="40%">Klasa Pluginu</th>
<th width="30%">Cel</th>
</tr>
</thead>
<tbody>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::addAdditionalQuoteProductAttributesPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Quote\AddAdditionalQuoteProductAttributesPlugin</td>
<td>Dodaje dodatkowe atrybuty produktu do koszyka w GraphQL.</td>
</tr>
</tbody>
</table>

## InPost_InPostPayGraphQl

### `etc/graphql/di.xml`

<table width="100%">
<thead>
<tr>
<th width="30%">Nazwa Pluginu</th>
<th width="40%">Klasa Pluginu</th>
<th width="30%">Cel</th>
</tr>
</thead>
<tbody>
<tr>
<td style="word-break: break-all;">InPost_InPostPay::registerSaveAddressActionPlugin</td>
<td style="word-break: break-all;">InPost\InPostPay\Plugin\Quote\RegisterSaveAddressActionPlugin</td>
<td>Rejestruje akcję zapisu adresu w GraphQL.</td>
</tr>
</tbody>
</table>

## InPost_Restrictions

Brak zdefiniowanych pluginów w `etc/di.xml`. Moduł korzysta z `preference` i `virtualType`.

## Snowdog_HyvaCheckoutInpost

### `etc/frontend/di.xml`

<table width="100%">
<thead>
<tr>
<th width="30%">Nazwa Pluginu</th>
<th width="40%">Klasa Pluginu</th>
<th width="30%">Cel</th>
</tr>
</thead>
<tbody>
<tr>
<td style="word-break: break-all;">removePickupPointIfNotUsingInPost</td>
<td style="word-break: break-all;">Snowdog\HyvaCheckoutInpost\Plugin\RemovePickupPoint</td>
<td>Usuwa punkt odbioru, jeśli nie użyto InPost.</td>
</tr>
</tbody>
</table>

## Snowdog_HyvaCheckoutPayNow

### `etc/frontend/di.xml`

<table width="100%">
<thead>
<tr>
<th width="30%">Nazwa Pluginu</th>
<th width="40%">Klasa Pluginu</th>
<th width="30%">Cel</th>
</tr>
</thead>
<tbody>
<tr>
<td style="word-break: break-all;">paynow_payment_methods_filter</td>
<td style="word-break: break-all;">Snowdog\HyvaCheckoutPayNow\Plugin\AvailableMethodsFilterPlugin</td>
<td>Filtruje dostępne metody płatności PayNow.</td>
</tr>
<tr>
<td style="word-break: break-all;">paynow_get_payment_methods</td>
<td style="word-break: break-all;">Snowdog\HyvaCheckoutPayNow\Plugin\PaymentMethodsHelperPlugin</td>
<td>Pobiera metody płatności PayNow.</td>
</tr>
</tbody>
</table>

## Snowdog_HyvaCheckoutSantander

Brak zdefiniowanych pluginów w `etc/frontend/di.xml` (tylko argumenty i compat modules).

## Mageplaza_SocialLogin

### `etc/frontend/di.xml`

<table width="100%">
<thead>
<tr>
<th width="30%">Nazwa Pluginu</th>
<th width="40%">Klasa Pluginu</th>
<th width="30%">Cel</th>
</tr>
</thead>
<tbody>
<tr>
<td style="word-break: break-all;">social_login_add_data</td>
<td style="word-break: break-all;">Mageplaza\SocialLogin\Plugin\CustomerData\Cart</td>
<td>Dodaje dane Social Login do sekcji koszyka w Customer Data.</td>
</tr>
</tbody>
</table>
