<?php

namespace Mobium\Api;

use Bitrix\Currency;
use Bitrix\Catalog\ProductTable;
use Bitrix\Iblock\PropertyTable;
use http\Env\Request;
use Mobium\Api\OffersExportProps\OffersExportPropsTable;
use Mobium\Api\ProductsExportProps\ProductsExportPropsTable;

class ExportCatalog
{
    /**
     * ID ИБ товаров
     * @var int
     */
    protected $productsIblock;

    /**
     * ID ИБ торговых предложений
     * @var int
     */
    protected $offersIblock;

    /**
     * Карта свойств товаров
     * @var array
     */
    protected $propertiesMap = [];

    /**
     * @var string
     */
    protected $charset = 'utf-8';

    /**
     * @var bool
     */
    protected $isError = false;

    /**
     * @var string
     */
    protected $filePath;

    /**
     * @var string
     */
    protected $fileName = 'mobium_new.yml';

    /**
     * @var resource
     */
    protected $file;

    /**
     * @var string
     */
    protected $lockFilePath;

    /**
     * @var \CUser
     */
    protected $user;

    /**
     * @var bool
     */
    protected $tempUserCreated = false;

    /**
     * @var string
     */
    protected $protocol;

    /**
     * @var \CUser
     */
    protected $tmpUser;

    /**
     * @var string
     */
    protected $baseCurrency;

    /**
     * @var string
     */
    protected $currencyRub = 'RUB';

    /**
     * @var array
     */
    protected $categoriesAvailable = [];

    public function __construct($iProductsIblock, $iOffersIblock)
    {
        set_time_limit(0);
        $this->setProductsIblock($iProductsIblock);
        $this->setOffersIblock($iOffersIblock);
    }

    public function buildPropertiesMap()
    {
        $aSelectIds = [];
        foreach (['offers', 'products'] as $sType){
            $aListParam = [
                'order'=>[
                    'EXPORT_SORT'=>'ASC'
                ]
            ];
            $oProductPropsMapResult =
                $sType == 'offers' ? OffersExportPropsTable::getList($aListParam) : ProductsExportPropsTable::getList($aListParam);
            $aProductPropsMap = [];
            while ($aRow = $oProductPropsMapResult->fetch()){
                $aItem = $aRow;
                if (isset($aItem['TOOLTIP_OPTIONS']) && !empty($aItem['TOOLTIP_OPTIONS'])){
                    $aTooltipOptions = [];
                    foreach ($aItem['TOOLTIP_OPTIONS'] as $sKey=>$aValues){
                        foreach ($aValues as $iIndex=>$mValue){
                            if (count($aTooltipOptions) < $iIndex+1){
                                $aTooltipOptions[] = [];
                            }
                            $aTooltipOptions[$iIndex][$sKey] = $mValue;
                        }
                    }
                    $aItem['TOOLTIP_OPTIONS'] = $aTooltipOptions;
                }
                $aProductPropsMap[$aRow['EXPORT_PROP_ID']] = $aItem;
                $aSelectIds[] = $aRow['EXPORT_PROP_ID'];

            }
            $iIbId = $sType == 'offers' ? $this->getOffersIblock() : $this->getProductsIblock();
            $this->propertiesMap[$iIbId] = $aProductPropsMap;
        }

        $oRes = \Bitrix\Iblock\PropertyTable::getList([
            'select'=>['*'],
            'filter'=>[
                '@ID'=>$aSelectIds,
                '=ACTIVE'=>'Y'
            ]
        ]);

        while ($aData = $oRes->fetch()) {
            if (!isset($this->propertiesMap[$aData['IBLOCK_ID']])) {
                continue;
                //$this->propertiesMap[$aData['IBLOCK_ID']] = [];
            }
            $this->propertiesMap[$aData['IBLOCK_ID']][$aData['ID']]['PROP_INFO'] = $aData;
        }
        //var_dump($this->propertiesMap);
        return $this;
    }

    public function processCategories()
    {
        $aFilters = ['IBLOCK_ID'=>$this->getProductsIblock(), 'ACTIVE'=>'Y', 'GLOBAL_ACTIVE'=>'Y'];
        $oCategoryResult = \CIBlockSection::GetList(["left_margin"=>"asc"], $aFilters);
        $aAvailableSections = [];
        $this->categoriesAvailable = [];
        while ($aCategory = $oCategoryResult->Fetch()){
            $aTemp = [
                'id'=>(int) $aCategory['ID'],
                'parent'=> (int) $aCategory['IBLOCK_SECTION_ID'],
                'name'=>$aCategory['NAME']
            ];
            if ((int) $aCategory['PICTURE'] > 0){
                $iPicId = (int) $aCategory['PICTURE'];

                $aPicInfo = \CFile::GetFileArray($iPicId);
                if (is_array($aPicInfo)){
                    $aTemp['image'] = $this->getFileURL($aPicInfo);
                }
            } else {
                continue;
            }
            $this->categoriesAvailable[] = $aTemp['id'];
            $aAvailableSections[] = $aTemp;
        }
        $sResult = $this->renderCategories($aAvailableSections);
        return $sResult;
    }

    /**
     * @param array $aCategories
     * @return string
     */
    protected function renderCategories($aCategories)
    {
        $sResult = '<categories>';
        foreach ($aCategories as $aCategory){
            $sResult .= '<category id="'.$aCategory['id'].'" '.
                ($aCategory['parent'] > 0 ? ' parentId="'.$aCategory['parent'].'"' : '').
                (isset($aCategory['image']) ? ' picture="'.$aCategory['image'].'"' : '').
                '>'.
                $this->text2xml($aCategory['name'], true).'</category>'.PHP_EOL;
        }
        $sResult .= '</categories>';
        return $sResult;
    }

    /**
     * @return string
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    protected function renderCurrency()
    {
        $sResult = "<currencies>\n";
        $arCurrencyAllowed = array('RUR', 'RUB', 'USD', 'EUR', 'UAH', 'BYR', 'BYN', 'KZT');
        $currencyIterator = Currency\CurrencyTable::getList(array(
            'select' => array('CURRENCY', 'SORT'),
            'filter' => array('@CURRENCY' => $arCurrencyAllowed),
            'order' => array('SORT' => 'ASC')
        ));
        while ($currency = $currencyIterator->fetch()){
            $sResult.= '<currency id="'.$currency['CURRENCY'].'" rate="'.(\CCurrencyRates::ConvertCurrency(1, $currency['CURRENCY'], 'RUB')).'" />'."\n";
        }
        $sResult.= "</currencies>\n";
        return $sResult;
    }

    public function processProducts()
    {
        $aResult = [];
        $oRes = ProductTable::getList([
            'select'=>['*'],
            'filter'=>[
                '=IBLOCK_ELEMENT.IBLOCK_ID'=>$this->getProductsIblock(),
                '@TYPE'=>[
                    ProductTable::TYPE_PRODUCT,
                    ProductTable::TYPE_SKU
                ],
                '=AVAILABLE'=>'Y'
            ],
        ]);
        $sResult = '<offers>'.PHP_EOL;
        while ($aItem = $oRes->fetch()){
            $aOffer = $this->buildOffer($aItem);
            if (null !== $aOffer){

                if ($aOffer['type'] == ProductTable::TYPE_SKU) {
                    $aSKURes = \CCatalogSKU::getOffersList([$aOffer['id']], $this->getProductsIblock());
                    $aOffer['children'] = [];
                    $aOffersIDs = array_keys($aSKURes[$aOffer['id']]);
                    $oSKURes = ProductTable::getList([
                        'select'=>['*'],
                        'filter'=>[
                            '=IBLOCK_ELEMENT.IBLOCK_ID'=>$this->getOffersIblock(),
                            '=TYPE'=> ProductTable::TYPE_OFFER,
                            '@ID'=>$aOffersIDs,
                            '=AVAILABLE'=>'Y'
                        ]
                    ]);
                    while ($aSKU = $oSKURes->fetch()){
                        $aTemp = $this->buildOffer($aSKU);

                        if (null !== $aTemp){
                            $aOffer['children'][] = $aTemp;
                        }
                    }
                }
                $aResult[] = $aOffer;
            }
            $sResult .= $this->renderOffer($aOffer);

        }
        $sResult .= '</offers>'.PHP_EOL;
        return $sResult;

    }

    protected function buildOffer($aItem)
    {
        $oIBlockResult = \CIBlockElement::GetByID($aItem['ID']);
        $aIBlockData = $oIBlockResult->GetNext();
        $aOffer = [
            'available'=> $aItem['AVAILABLE'],
            'id'=>$aItem['ID'],
            'prices'=>$this->getProductPrice($aItem['ID'], 2),
            'name'=>$aIBlockData['NAME'],
            'categories'=>$this->getProductCategories($aItem['ID']),
            'properties'=>$this->getProductProperties($aItem['ID'], $aIBlockData['IBLOCK_ID']),
            'description'=>preg_replace_callback(
                "'&[^;]*;'",
                function ($arg){
                    if (in_array($arg[0], array("&quot;", "&amp;", "&lt;", "&gt;")))
                        return $arg[0];
                    else
                        return " ";
                },
                $aIBlockData["~DETAIL_TEXT"]
            ),
            'sort'=> (int) $aIBlockData['SORT'],
            'type'=>$aItem['TYPE']
        ];

        if (strpos($aOffer['properties']['vendor_code']['value'],'/') !== false){
            return null;
        }
        $aOffer['url'] = 'http://'.\COption::GetOptionString("main", "server_name", "").\CHTTP::urnEncode($aIBlockData["~DETAIL_PAGE_URL"], 'utf-8');
        $iPictureId = (int) $aIBlockData['DETAIL_PICTURE'] > 0 ?
            (int) $aIBlockData['DETAIL_PICTURE'] :
            (int) $aIBlockData['PREVIEW_PICTURE'];
        if ($iPictureId > 0){
            $aPicture = \CFile::GetFileArray($iPictureId);
            if (false !== ($sFileURL = $this->getFileURL($aPicture, true))){
                $aOffer['picture'] = $sFileURL;
            }
        }
        return $aOffer;
    }

    protected function getProductPrice($iProductId, $iPrecision=2)
    {
        $fPrice = round((float) \Bethowen\Helpers\Price::getDiscountPrice($iProductId), $iPrecision);
        $aOldPrice = \CPrice::GetBasePrice($iProductId);
        $fOldPrice = round((float) $aOldPrice['PRICE'], $iPrecision);
        $aResult = [
            'price'=>$fPrice,
        ];
        if ($fPrice < $fOldPrice){
            $aResult['old_price'] = $fOldPrice;
        }
        return $aResult;

    }

    protected function getProductCategories($iProductId)
    {
        $oRes = \CIBlockElement::GetElementGroups($iProductId, false, array('ID', 'ADDITIONAL_PROPERTY_ID'));
        $aResult = [];
        while ($aData = $oRes->Fetch()){
            if (in_array($aData['ID'], $this->categoriesAvailable)){
                $aResult[] = $aData['ID'];
            }
        }
        return $aResult;
    }

    protected function getProductProperties($iProductId, $iProductIBlockId)
    {
        $aPropertiesMap = $this->getPropertiesMap();
        $aPropFilter = [
            '@ID'=>array_keys($aPropertiesMap[$iProductIBlockId]),
            'ACTIVE'=>'Y'
        ];
        $oPropRes = \CIBlockElement::GetProperty($iProductIBlockId, $iProductId, [], $aPropFilter);
        $aResult = [
            'vendor_code'=>null,
            'props'=>[],
            'badges'=>[],
        ];
        while ($aPropRes = $oPropRes->Fetch()){
            $aFieldInfo = $aPropertiesMap[$iProductIBlockId][$aPropRes['ID']];
            $aProperty = $aFieldInfo['PROP_INFO'];
            $bIsUserType = isset($aPropRes['USER_TYPE']) && !empty($aPropRes['USER_TYPE']) &&
                isset($aPropRes['USER_TYPE_SETTINGS']) && !empty($aPropRes['USER_TYPE_SETTINGS']);
            $sFieldName = isset($aFieldInfo['EXPORT_NAME']) && !empty(trim($aFieldInfo['EXPORT_NAME'])) ?
                trim($aFieldInfo['EXPORT_NAME']) : $aPropRes['NAME'];

            $aResultItem = [
                'name'=>$sFieldName,
                'id'=>$aPropRes['CODE'],
                'value'=>null,
                'type'=>$aPropertiesMap[$iProductIBlockId][$aPropRes['ID']]['PROPERTY_TYPE'],
            ];
            if ($bIsUserType) {
                $aUserType = \CIBlockProperty::GetUserType($aPropRes['USER_TYPE']);
                if (isset($aUserType['GetPublicViewHTML'])){
                    $aResultItem['value'] = call_user_func_array($aUserType['GetPublicViewHTML'],
                        array(
                            $aPropRes,
                            array("VALUE" => $aPropRes['VALUE']),
                            array('MODE' => 'SIMPLE_TEXT'),
                        )
                    );
                    $aResultItem['is_user_type'] = true;
                }
            } else {
                switch ($aProperty['PROPERTY_TYPE']){
                    case PropertyTable::TYPE_NUMBER:
                    case PropertyTable::TYPE_STRING:
                        $aResultItem['value'] = $aPropRes['VALUE'];
                        break;
                    case PropertyTable::TYPE_ELEMENT:
                    case PropertyTable::TYPE_SECTION:
                        // Ищем элементы ИБ и берем их название
                        if (!empty($aPropRes['VALUE'])){
                            $aValueIterator = is_array($aPropRes['VALUE']) ? $aPropRes['VALUE'] : [$aPropRes['VALUE']];
                            $aCheckValues = [];
                            foreach ($aValueIterator as $iElementId){
                                $iElementId = (int) $iElementId;
                                if ($iElementId > 0){
                                    $aCheckValues[] = $iElementId;
                                }
                            }
                            if (!empty($aCheckValues)){
                                $aElementsFilter = [
                                    '@ID'=>$aCheckValues,
                                ];
                                if ($aPropRes['LINK_IBLOCK_ID']){
                                    $aElementsFilter['=IBLOCK_ID'] = $aPropRes['LINK_IBLOCK_ID'];
                                }
                                if ($aProperty['PROPERTY_TYPE'] === PropertyTable::TYPE_ELEMENT){
                                    $oElementsResult = \Bitrix\Iblock\ElementTable::getList([
                                        'select'=>['ID', 'NAME'],
                                        'filter'=>$aElementsFilter,
                                    ]);
                                } else {
                                    $oElementsResult = \Bitrix\Iblock\SectionTable::getList([
                                        'select'=>['ID', 'NAME'],
                                        'filter'=>$aElementsFilter,
                                    ]);
                                }
                                $sValue = '';
                                while ($aElementItem = $oElementsResult->fetch()){
                                    $sValue .= ($sValue ? ', ' : '').$aElementItem['NAME'];
                                }
                                $aResultItem['value'] = !empty($sValue) ? $sValue : null;
                            }
                        }
                        break;
                    case PropertyTable::TYPE_LIST:
                        if (!empty($aPropRes['~VALUE'])){
                            $sValue = is_array($aPropRes['~VALUE']) ?
                                implode(', ', $aPropRes['~VALUE']) :
                                $aPropRes['~VALUE'];
                        } elseif (!empty($aPropRes['VALUE_ENUM'])) {
                            $sValue = $aPropRes['VALUE_ENUM'];
                            $aResultItem['_value'] = $aPropRes['VALUE'];
                        }
                        $aResultItem['value'] = !empty($sValue) ? $sValue : null;
                        break;
                    case PropertyTable::TYPE_FILE:
                        // Берем только изображения
                        if (!empty($aPropRes['VALUE'])){
                            $aValueIterator = is_array($aPropRes['VALUE']) ? $aPropRes['VALUE'] : [$aPropRes['VALUE']];
                            $aValue = [];
                            foreach ($aValueIterator as $iFileId){
                                $iFileId = (int) $iFileId;
                                if ($iFileId > 0){
                                    if ($aFileData = \CFile::GetFileArray($iFileId)){
                                        if (false !== ($sFileUrl = $this->getFileURL($aFileData, true))){
                                            $aValue[] = $sFileUrl;
                                        }
                                    }
                                }
                            }
                            $aResultItem['value'] = count($aValue) > 0 ? $aValue : null;
                        }
                        break;
                }
            }
            if ($aResultItem['value'] !== null){
                if ($aFieldInfo['EXPORT_PROP'] === 'Y'){
                    $aResult['props'][] = $aResultItem;
                }
                if ($aFieldInfo['PROP_IS_VENDOR_CODE'] === 'Y'){
                    $aResult['vendor_code'] = $aResultItem;
                }
                if ($aFieldInfo['PROP_IS_TOOLTIP'] === 'Y'){
                    $iIndex = array_search($aResultItem['value'], array_column($aFieldInfo['TOOLTIP_OPTIONS'], 'value'));
                    if (false === $iIndex){
                        $iIndex = array_search('#default#', array_column($aFieldInfo['TOOLTIP_OPTIONS'], 'value'));
                    }
                    if (false !== $iIndex){
                        $aBadgeOptions = $aFieldInfo['TOOLTIP_OPTIONS'][$iIndex];
                        $aBadge = [
                            'label'=>trim($aResultItem['value']),
                            'textColor'=>$aBadgeOptions['text_color'],
                            'backgroundColor'=>$aBadgeOptions['background_color'],
                            'shape'=>$aBadgeOptions['shape'],
                            'showListing'=>$aBadgeOptions['show_listing'] == '1',
                            'showCard'=>$aBadgeOptions['show_card'] == '1'
                        ];
                        $aResult['badges'][] = $aBadge;
                    }
                }
                //$aResult[] = $aResultItem;
            }
        }
        return $aResult;
    }

    protected function getFileURL(array $aFileData, $bIsImage=false)
    {
        if ($bIsImage && !\CFile::IsImage($aFileData['FILE_NAME'], $aFileData['CONTENT_TYPE'])){
            return false;
        }
        return substr($aFileData["SRC"], 0, 1) == "/" ?
            'http://'.\COption::GetOptionString("main", "server_name", "").\CHTTP::urnEncode($aFileData['SRC'], 'utf-8') :
            $aFileData['SRC'];
    }

    protected function renderOffer(array $aOffer, $aParent=null)
    {
        $sResult = '';
        if (isset($aOffer['children'])){
            foreach ($aOffer['children'] as $aSKU){
                $sResult .= $this->renderOffer($aSKU, $aOffer);
            }
            return $sResult;
        }
        $aPictures = $aOfferAttrs = [];
        $aOfferAttrs[] = 'id="'.$aOffer['id'].'"';
        $aOfferAttrs[] = 'available="'.($aOffer['available'] == 'Y').'"';
        $iSort = (int) $aOffer['sort'];
        if (null !== $aParent && isset($aParent['sort'])){
            $iSort = (int) $aParent['sort'];
        }
        $aOfferAttrs[] = 'sort="'.$iSort.'"';

        if (null !== $aParent && $aParent['id']){
            $aOfferAttrs[] = 'group_id="'.$aParent['id'].'"';
        }
        $sResult .= '<offer'.(!empty($aOfferAttrs) ? ' '.implode(' ', $aOfferAttrs) : '').'>'.PHP_EOL;
        $aCategories = $aOffer['categories'];
        if (null !== $aParent && count($aParent['categories']) > 0){
            $aCategories = array_unique(array_merge($aOffer['categories'], $aParent['categories']));
        }
        foreach ($aCategories as $sCategoryId){
            $sResult .= '<categoryId>'.$sCategoryId.'</categoryId>'.PHP_EOL;
        }
        if (isset($aOffer['url'])){
            $sResult .= '<url>'.$this->text2xml($aOffer['url'], true).'</url>'.PHP_EOL;
        }
        if (isset($aOffer['prices']['price'])){
            $sResult .= '<price>'.$aOffer['prices']['price'].'</price>'.PHP_EOL;
        }
        if (isset($aOffer['prices']['old_price'])){
            $sResult .= '<oldprice>'. $aOffer['prices']['old_price'].'</oldprice>';
        }
        if (isset($aOffer['picture'])){
            $aPictures[] = $aOffer['picture'];
        }
        if (isset($aOffer['name'])){
            $sResult .= '<name>'.$this->text2xml($aOffer['name'], true).'</name>'.PHP_EOL;
        }
        $sDescription = trim($aOffer['description']);
        if (empty($sDescription) && null !== $aParent && $aParent['description']){
            $sDescription = trim($aParent['description']);
        }
        if ($sDescription){
            $sResult .= '<description><![CDATA['.$sDescription.']]></description>'.PHP_EOL;
        }

        $sResult .= '<sort>'.($iSort).'</sort>'.PHP_EOL;

        foreach ($aPictures as $sPic){
            $sResult .= '<picture>'.$sPic.'</picture>'.PHP_EOL;
        }
        foreach ($aOffer['properties'] as $sKey => $aValue){
            switch ($sKey){
                case 'vendor_code':
                    $sResult.='<vendorCode>'.$this->text2xml($aValue['value']).'</vendorCode>'.PHP_EOL;
                    break;
                case 'badges':
                    foreach ($aValue as $aTooltip){
                        $sResult .= '<tooltip backgroundColor="'.$this->text2xml($aTooltip['backgroundColor'], true) . '"
                                     textColor="'.$this->text2xml($aTooltip['textColor'], true).'" 
                                     showListing="'.$this->renderBool($aTooltip['showListing'] ?? false).'" 
                                     showCard="'.$this->text2xml($aTooltip['showCard'] ?? false).'" 
                                     shape="'.$this->text2xml($aTooltip['shape'], true).'" 
                                     label="'.$this->text2xml($aTooltip['label'], true).'"></tooltip>'.PHP_EOL;
                    }
                    break;
                case 'props':
                    $aProps = $aValue;
                    if (null !== $aParent && $aParent['properties']['props']){
                        $aProps = array_merge($aParent['properties']['props'], $aProps);
                    }
                    foreach ($aProps as $aProp){
                        $sResult .= '<param code="'.$aProp['id'].'" name="'.$this->text2xml($aProp['name'], true).'">'.$this->text2xml(trim($aProp['value']), true).'</param>'.PHP_EOL;
                    }
                    break;
            }
        }

        $sResult .= '</offer>'.PHP_EOL;
        return $sResult;

    }

    /**
     * @return int
     */
    public function getProductsIblock()
    {
        return $this->productsIblock;
    }

    /**
     * @param int $productsIblock
     * @return $this
     */
    public function setProductsIblock($productsIblock)
    {
        $this->productsIblock = $productsIblock;
        return $this;
    }

    /**
     * @return int
     */
    public function getOffersIblock()
    {
        return $this->offersIblock;
    }

    /**
     * @param int $offersIblock
     * @return $this
     */
    public function setOffersIblock($offersIblock)
    {
        $this->offersIblock = $offersIblock;
        return $this;
    }

    /**
     * @return array
     */
    public function getPropertiesMap()
    {
        return $this->propertiesMap;
    }

    /**
     * @param array $propertiesMap
     * @return $this
     */
    public function setPropertiesMap($propertiesMap)
    {
        $this->propertiesMap = $propertiesMap;
        return $this;
    }

    /**
     * @param string $text
     * @param bool $bHSC использовать htmlspecialchars
     * @param bool $bDblQuote декодировать &quot; в "
     * @return string
     */
    protected function text2xml($text, $bHSC = false, $bDblQuote = false)
    {
        global $APPLICATION;

        $bHSC = (true == $bHSC ? true : false);
        $bDblQuote = (true == $bDblQuote ? true: false);

        if ($bHSC) {
            $text = htmlspecialcharsbx($text);
            if ($bDblQuote){
                $text = str_replace('&quot;', '"', $text);
            }

        }
        $text = preg_replace('/[\x01-\x08\x0B-\x0C\x0E-\x1F]/', "", $text);
        $text = str_replace("'", "&apos;", $text);
        $text = $APPLICATION->ConvertCharset($text, LANG_CHARSET, $this->charset);

        return $text;
    }

    /**
     * @param bool $bBool
     * @return string
     */
    protected function renderBool($bBool){
        return $bBool === true ? 'true' : 'false';
    }

    protected function beforeStart()
    {
        $this->createUser();
        //\CCatalogDiscountSave::Disable();
        //\CCatalogDiscountCoupon::ClearCoupon();
        if ($this->user->IsAuthorized()) {
            \CCatalogDiscountCoupon::ClearCouponsByManage($this->user->GetID());
        }
        $this->protocol = (\CMain::IsHTTPS() ? 'https://' : 'http://');
        $this->protocol = 'https://';
        $this->defineCurrency();
        $strExportPath = \COption::GetOptionString("catalog", "export_default_path", CATALOG_DEFAULT_EXPORT_PATH);
        $this->filePath = Rel2Abs('/',str_replace('//','/',$strExportPath."/{$this->fileName}"));
        if (!empty($this->filePath)){
            $this->lockFilePath = $this->filePath.'.lock';
            if (file_exists($this->lockFilePath)){
                return false;
            }
            file_put_contents($this->lockFilePath, 'locked');
            CheckDirPath($_SERVER["DOCUMENT_ROOT"].$strExportPath);

            if ($this->file = @fopen($_SERVER["DOCUMENT_ROOT"].$this->filePath, 'wb')) {
                return true;
            }
        }
        return false;
    }

    protected function createUser()
    {
        global $USER;
        if (!\CCatalog::IsUserExists()) {
            $this->tempUserCreated = true;
            if (isset($USER)) {
                $this->tmpUser = $USER;
            }
            $this->user = new \CUser();
        } else {
            $this->user = $USER;
        }
    }

    /**
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    protected function defineCurrency()
    {
        $this->baseCurrency = Currency\CurrencyManager::getBaseCurrency();
        $currencyIterator = Currency\CurrencyTable::getList(array(
            'select' => array('CURRENCY'),
            'filter' => array('=CURRENCY' => 'RUR')
        ));
        if ($currency = $currencyIterator->fetch()){
            $this->currencyRub = 'RUR';
        }
    }

    public function start(){
        \Bitrix\Main\Loader::IncludeModule("highloadblock");
        set_time_limit(0);
        ini_set('memory_limit', '4096M');
        if (true !==$this->beforeStart()){
            exit();
        }
        $this->_start();
        $this->endExport();
    }
    protected function _start()
    {
        fwrite($this->file, $this->renderShopInfo());
        fwrite($this->file, $this->renderCurrency());
        fwrite($this->file, $this->processCategories());
        fwrite($this->file, $this->processProducts());
        fwrite($this->file, '</shop>'.PHP_EOL.'</yml_catalog>');
        fclose($this->file);
    }

    /**
     * @return string
     */
    protected function renderShopInfo()
    {
        global $APPLICATION;
        $sResult = '<?xml version="1.0" encoding="'.$this->charset.'"?>';
        $sResult.= "\n<!DOCTYPE yml_catalog SYSTEM \"shops.dtd\">\n";
        $sResult.= "<yml_catalog date=\"".date("Y-m-d H:i")."\">\n";
        $sResult.= "<shop>\n";
        $sResult.= "<name>".$APPLICATION->ConvertCharset(htmlspecialcharsbx(\COption::GetOptionString("main", "site_name", "")), LANG_CHARSET, $this->charset)."</name>\n";
        $sResult.= "<company>".$APPLICATION->ConvertCharset(htmlspecialcharsbx(\COption::GetOptionString("main", "site_name", "")), LANG_CHARSET, $this->charset)."</company>\n";
        $sResult.= "<url>".$this->protocol.htmlspecialcharsbx(\COption::GetOptionString("main", "server_name", ""))."</url>\n";
        $sResult.= "<platform>1C-Bitrix</platform>\n";
        return $sResult;
    }

    protected function endExport()
    {
        if ($this->isError) {
            \CEventLog::Log('WARNING','CAT_YAND_AGENT','mobium.api','exportYML',$this->filePath);
        }
        @unlink($this->lockFilePath);
        $this->deleteUser();
    }

    protected function deleteUser()
    {
        global $USER;
        if ($this->tempUserCreated) {
            if (isset($this->tmpUser) && $this->tmpUser instanceof \CUser) {
                $USER = $this->tmpUser;
                unset($this->tmpUser);
            }
        }
    }
}