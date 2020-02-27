<?php
namespace Mobium\Api;


use _CIBElement;
use Bitrix\Currency;
use Bitrix\Iblock;
use CCatalog;
use CCatalogDiscount;
use CCatalogDiscountSave;
use CCatalogProduct;
use CCatalogSku;
use CCatalogStore;
use CCatalogStoreProduct;
use CCurrencyRates;
use CDBResult;
use CEventLog;
use CFile;
use CHTTP;
use CIBlock;
use CIBlockElement;
use CIBlockFormatProperties;
use CIBlockRights;
use CIBlockSection;
use COption;
use CSite;

class ExportPoints extends OfferExporter
{
    const PRODUCTS_IBLOCK_ID = 26;
    const OFFERS_IBLOCK_ID = 27;
    const SHOPS_IBLOCK_ID = 43;
    const DELIVERIES_IBLOCK_ID = 28;
    /**
     * @var array
     */
    protected $stocks = [];

    /**
     * @var array
     */
    protected $offers = [];

    /**
     * @var array
     */
    protected $stores = [];

    /**
     * @var array
     */
    protected $selectStockField = [
        '*', 'UF_*'
    ];


    protected $loadedRegions = [];

    /**
     * @var \SplFixedArray
     */
    protected $productIDs;

    public function __construct()
    {
        \Bitrix\Main\Loader::IncludeModule("highloadblock");
        set_time_limit(0);
        ini_set('memory_limit', '4096M');
        $this->fileName = $this->getFileName();
        if (true !==$this->beforeStart()){
            exit();
        }
        //$this->productIDs = new \SplFixedArray(30000);
        $this->productIDs = [];
        $this->start();
        $this->endExport();

    }

    protected function beforeStart()
    {
        $this->createUser();
        $this->protocol = (\CMain::IsHTTPS() ? 'https://' : 'http://');
        $strExportPath = \COption::GetOptionString("catalog", "export_default_path", CATALOG_DEFAULT_EXPORT_PATH);
        $this->filePath = Rel2Abs('/',str_replace('//','/',$strExportPath."/{$this->fileName}"));
        if (!empty($this->filePath)){
            CheckDirPath($_SERVER["DOCUMENT_ROOT"].$strExportPath);

            if ($this->file = @fopen($_SERVER["DOCUMENT_ROOT"].$this->filePath, 'wb')) {
                return true;
            }
        }
        return false;
    }
    protected function endExport()
    {
        $this->deleteUser();
    }

    public function start()
    {
        $this->renderHeader();
        $this->processCatalog();
        $this->renderFooter();
        fclose($this->file);
    }

    protected function renderHeader()
    {
        fwrite($this->file, '{"type":"FeatureCollection","features":[');
    }

    protected function renderFooter()
    {
        fwrite($this->file, ']}');
    }

    protected function processCatalog()
    {
        $oSectionResult = CIBlockSection::GetList([], ['IBLOCK_ID'=>static::SHOPS_IBLOCK_ID], false, ['NAME', 'ID']);
        $bIsFirst = true;
        while ($aSection = $oSectionResult->GetNext()){
            $oElementsResult = CIBlockElement::GetList([], ['IBLOCK_ID'=>static::SHOPS_IBLOCK_ID, 'SECTION_ID'=>$aSection['ID']], false ,false, ['*',
                'PROPERTY_MAP_KAK_DOBRATSA_2',
                'PROPERTY_EMAIL',
                'PROPERTY_PHONE',
                'PROPERTY_FIO_DIREKTORA',
                //'PROPERTY_PHOTO_SHOP',
                'PROPERTY_SHOP_CODE_1C'
            ]);
            /** @var _CIBElement $oElement */
            while ($oElement = $oElementsResult->GetNextElement()){
                $aPhotos = [];
                $aPhotoShop = $oElement->GetProperty('PHOTO_SHOP');
                $aPhotoShop['VALUE'] = is_array($aPhotoShop['VALUE']) ? $aPhotoShop['VALUE'] : [$aPhotoShop['VALUE']];
                foreach ($aPhotoShop['VALUE'] as $sVal){
                    $aFile = CFile::GetFileArray($sVal);
                    $aPhotos[] =  $this->getServerName().$aFile['SRC'];
                }
                $aServices = [];
                $aPossibleServices = ['ENTRANCE_WITH_ANIMALS', 'VET_APTEKA', 'VET_KABINET',
                    'AKVARIYMISTIKA', 'RASTENIA_AKVARIYM', 'FISH',
                    'GRAVIROVKA', 'GRYMING',
                    'SMALL_ANIMALS', 'DOGS_CATS'];
                foreach ($aPossibleServices as $sPossibleService) {
                    $aProp = $oElement->GetProperty($sPossibleService);
                    if (strtolower((string)$aProp['VALUE']) == 'да') {
                        $aServices[] = $aProp['NAME'];
                    }
                }

                $sCoords = $oElement->GetProperty('GOOGLE')['VALUE'];
                $aCoords = explode('|', $sCoords);
                if (count($aCoords) < 2){
                    continue;
                }
                $aPhone = $oElement->GetProperty('PHONE');
                if (!empty(trim($aPhone['VALUE']))){
                    $sPhone = $aPhone['VALUE'];
                } else {
                    $sPhone = '+78005552211';
                }
                $aShop = [
                    'id'=>$oElement->GetProperty('SHOP_CODE_1C')['VALUE'],
                    'region'=>$aSection['NAME'],
                    'address'=>html_entity_decode($oElement->GetProperty('ADDRESS')['VALUE'], ENT_QUOTES | ENT_HTML5),
                    'subway'=>$oElement->GetProperty('METRO')['VALUE'],
                    'title'=>$oElement->fields['NAME'],
                    'scheduling'=>ucfirst($oElement->GetProperty('TIMES')['VALUE']),
                    'content'=>$oElement->GetProperty('MAP_KAK_DOBRATSA_2')['VALUE']['TEXT'],
                    //'email'=>$oElement->GetProperty('EMAIL')['VALUE'],
                    'email'=>'info@bethowen.ru',
                    'phone'=>$sPhone,
                    'contact_name'=>$oElement->GetProperty('FIO_DIREKTORA')['VALUE'],
                    'photos'=>$aPhotos,
                    'services'=>$aServices,
                ];
                $aElement = [
                    'type'=>'Feature',
                    'properties'=>$aShop,
                    'geometry'=> [
                        'type'=>'Point',
                        'coordinates'=>[
                            (float)$aCoords[1],
                            (float)$aCoords[0]
                        ],
                    ]
                ];
                fwrite($this->file, ($bIsFirst ? '': ',').json_encode($aElement));
                $bIsFirst = false;
            }
        }
        $this->processPolygons();
    }

    protected function processPolygons()
    {
        $aDeliveryTypesId = [
            292223=>'5bfe6d44d4242',
            292222=>'5bfe6d163087e',
            291153=>'5bfe6cd7991b0',
            291152=>'5bfe6cd7991b0',
            291151=>'5bfe6cd7991b0',
            291150=>'5bfe6cd7991b0',
            291149=>'5bfe6c963ef94',
            287954=>'5bfe6b37ef239',
            287953=>'5bfe68c7d4405',
            287952=>'5bfe68c7d4405',
            287951=>'5bfe68c7d4405',
            287950=>'5bfe68c7d4405',
            736=>'5bfe6848bf897',

        ];
        $oElementsResult = CIBlockElement::GetList([], ['IBLOCK_ID'=>static::DELIVERIES_IBLOCK_ID, 'ACTIVE'=>'Y'], false ,false, ['*',
            'PROPERTY_COORDS',
            'PROPERTY_FREE_DELIVERY'
        ]);
        /** @var _CIBElement $oElement */
        $bIsFirst = false;
        while ($oElement = $oElementsResult->GetNextElement()) {
            $aPriceData = $oElement->GetProperty('DIFFERENT_ZONES_COST_DELIVERY');
            $iElements = count($aPriceData['VALUE']);
            $aDeliveryPricesRange = [];
            if ($iElements == count($aPriceData['DESCRIPTION'])){
                for ($i=0;$i<$iElements;++$i){
                    list($sMin, $sMax) = explode('-', $aPriceData['VALUE'][$i]);
                    $aDeliveryPricesRange[] = [
                        'min'=>(float) $sMin,
                        'max'=>(float) $sMax,
                        'cost'=>(float) $aPriceData['DESCRIPTION'][$i]
                    ];
                }
            }
            $fFreeShippingFrom = (float) $oElement->GetProperty('FREE_DELIVERY')['VALUE'];
            $sCoords = $oElement->GetProperty('COORDS')['VALUE']['TEXT'];
            $aCoords = json_decode(str_replace('&quot;', '', $sCoords), true);

            $sCityName = $oElement->GetProperty('CITY')['VALUE_ENUM'];
            $sName = $oElement->fields['NAME'];
            $iId = (int) $oElement->fields['ID'];
            $sDeliveryTypeId = '5bfe6848bf897'; // Доставка по Москве
            if (isset($aDeliveryTypesId[$iId])){
                $sDeliveryTypeId = $aDeliveryTypesId[$iId];
            }
            $aDeliveryItemData = [
                'id'=>$iId,
                'title'=>$sName,
                'city'=>$sCityName,
                'freeFrom'=>$fFreeShippingFrom,
                'costRange'=>$aDeliveryPricesRange,
                'delivery_type'=>$sDeliveryTypeId
            ];
            $aDeliveryItem = [
                'type'=>'Feature',
                'properties'=>$aDeliveryItemData,
                'geometry'=>[
                    'type'=>'Polygon',
                    'coordinates'=>[$aCoords]
                ],
            ];
            fwrite($this->file, ($bIsFirst ? '': ',').json_encode($aDeliveryItem));
            //$bIsFirst = false;
        }
    }

    /**
     * @param $iId
     * @return array
     */
    protected function getStore($iId){
        if (!isset($this->stores[$iId])){
            $oStocksResult = CCatalogStore::GetList(array(), array('ID'=>$iId, 'ACTIVE'=>'Y'), false, false, $this->selectStockField);
            $aStock = $oStocksResult->GetNext();
            $this->stores[$iId] = $aStock;
        }
        return $this->stores[$iId];

    }

    protected function getRegion($sRegionId)
    {
        if (!isset($this->loadedRegions[$sRegionId])){
            var_dump($sRegionId);
        }
    }

    public static function run(){
        $oObject = new static();
        return 'Mobium\Api\ExportPoints::run();';
    }
    /**
     * @return string
     */
    protected function getFileName()
    {
        return 'mobium_points.json';
    }
}

/*
array(42) {
  ["ID"]=>
  string(1) "1"
  ["ACTIVE"]=>
  string(1) "Y"
  ["TITLE"]=>
  string(53) "Остатки в магазине Ленинский"
  ["PHONE"]=>
  string(18) "+7 (800) 555-22-11"
  ["SCHEDULE"]=>
  string(37) "Ежедневно с 9.00 до 23.00"
  ["ADDRESS"]=>
  string(72) "г. Москва, Ленинский проспект, д. 49, стр. 5"
  ["DESCRIPTION"]=>
  string(422) "м."Ленинский проспект", 1 вагон из центра, из метро направо на ул.Вавилова, любой трамвай в сторону Черемушкинского рынка, 2-ая остановка "ул.Бардина", направо по ул.Бардина пройти 100 метров. Рядом с магазином удобное место для парковки"
  ["GPS_N"]=>
  string(9) "55.700735"
  ["GPS_S"]=>
  string(9) "37.571762"
  ["IMAGE_ID"]=>
  string(6) "109022"
  ["LOCATION_ID"]=>
  NULL
  ["DATE_CREATE"]=>
  string(19) "19.07.2018 15:52:00"
  ["DATE_MODIFY"]=>
  string(19) "07.10.2018 05:14:56"
  ["USER_ID"]=>
  string(6) "347924"
  ["MODIFIED_BY"]=>
  string(6) "347924"
  ["XML_ID"]=>
  string(20) "_QUANTITY_IN_SHOP_1_"
  ["SORT"]=>
  string(3) "100"
  ["EMAIL"]=>
  string(27) "shop.leninskij@petretail.ru"
  ["ISSUING_CENTER"]=>
  string(1) "Y"
  ["SHIPPING_CENTER"]=>
  string(1) "Y"
  ["SITE_ID"]=>
  string(2) "s1"
  ["CODE"]=>
  string(15) "moscow_leninsky"
  ["PRODUCT_AMOUNT"]=>
  NULL
  ["ELEMENT_ID"]=>
  NULL
  ["UF_SMS"]=>
  string(54) "Ленинский проспект, д. 49, стр. 5"
  ["UF_PUBLIC_TRANSPORT"]=>
  string(220) "Метро Октябрьская, Ленинский проспект, далее любой транспорт до остановки "Дворец труда и профсоюзов" в сторону области."
  ["UF_EXTERNAL_CODE"]=>
  string(4) "1784"
  ["UF_CODE_1C"]=>
  string(9) "000000119"
  ["UF_METRO"]=>
  string(53) "a:1:{i:0;s:35:"Ленинский проспект";}"
  ["UF_NAME_SHOP"]=>
  string(54) "Ленинский проспект Ленинский"
  ["UF_NUMBER_SHOP"]=>
  string(3) "032"
  ["UF_REGION"]=>
  string(3) "543"
  ["UF_ENTER_WITH_ANIMAL"]=>
  string(1) "1"
  ["UF_AQUARIUMS"]=>
  string(1) "1"
  ["UF_VETERINARY"]=>
  string(1) "1"
  ["UF_VETERINARY_OFFICE"]=>
  string(1) "0"
  ["UF_ENGRAVING"]=>
  string(1) "1"
  ["UF_LIVE_PLANTS"]=>
  string(1) "1"
  ["UF_PETS"]=>
  string(1) "0"
  ["UF_SMALL_ANIMALS"]=>
  string(1) "1"
  ["UF_GROOMING"]=>
  string(1) "0"
  ["UF_LIVE_FISH"]=>
  string(1) "0"
}
 */