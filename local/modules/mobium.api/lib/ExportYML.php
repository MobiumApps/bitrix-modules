<?php
namespace Mobium\Api;

use Bitrix\Currency;
use Bitrix\Iblock;
use CCatalog;
use CCatalogDiscount;
use CCatalogDiscountSave;
use CCatalogProduct;
use CCatalogSku;
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

class ExportYML
{
    const PRODUCTS_IBLOCK_ID = 17;
    const OFFERS_IBLOCK_ID = 20;

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
     * @var array
     */
    protected $selectProductsFields = [
        "ID", "LID", "IBLOCK_ID", "IBLOCK_SECTION_ID", "ACTIVE", "NAME",
        "PREVIEW_PICTURE", "PREVIEW_TEXT", "PREVIEW_TEXT_TYPE",
        "DETAIL_PICTURE", "LANG_DIR", "DETAIL_PAGE_URL",
        "CATALOG_AVAILABLE", 'DETAIL_TEXT', 'DISPLAY_PROPERTIES',
        'QUANTITY', 'QUANTITY_RESERVED', 'QUANTITY_TRACE', 'CAN_BUY_ZERO'
    ];

    /**
     * @var array
     */
    protected $productProps = [
        "BRAND", "VOZDUHOPRONICH", "ALTMETR", "CML2_ARTICLE", "BAROMETR", "BUDILNIK", "TKAN_UP",
        "VESUTEPL", "VIDEO_YOUTUBE", "VNESH_KURTKA", "VNUTR_KURTKA", "TKAN_DOWN", "VODOPRONIC", "VREMYA_RABOTI", "VTORCHASPOYAS", "VINOSVRACHKA", "DALNOST_SVET",
        "DIAMETRVIH", "DIAMETR_GORL", "DIAMETROBJEK", "DLINA", "DLINARASKR", "DLINASLOJ", "DLIN_LEZV", "DLIN_RUK", "DLINASPINKI", "DUGI", "ZAJIM", "ZASTEJKA",
        "ZERNISTOST", "KALIBR", "KAPUSHON", "KOLVO_KARMAN", "KOLVO", "KOLVOMEST", "KOMPAS", "MATERBRASLET", "MATKARCASA", "MATERCORPUSA", "MATERIALOPTIKI",
        "MATERIAL_RUK", "MATERIAL_RUCHKI", "MATERTOCH", "MEMBRABA", "NAZHACHENIE", "NOZHNI", "OBSH_DLIN", "OTOBRDATI", "PRILIVOTLIV", "PODKLAD", "PODOSHVA",
        "PODSVETKA", "FILLPOWER", "KROY", "POKRITIELINZ", "MENWOMEN", "POLEOBZORANA1000", "PRINTS", "PROBKA", "PROTIVOUDARNOST", "PRYAJKA", "RADIOSINHRON",
        "RAZMERVSOBRANOMVIDE", "RAZMERVUPAKOVKE", "ROSTOVKA", "ROST_PAL", "SVETOSILA", "SEKUNDOMER", "SYSTEMPRIZM", "TEMPER_SAVE", "STEEL_MARK", "STEKLO",
        "STELKA", "STYLE", "TAIMER", "TVERD_STEEL", "TEMLYAK", "TEMPER", "TERMOMETR", "TECHNOLOGY", "PROP_2033", "TIP_ZATOCHKI", "TIP_LAMP", "TIPMEHANIZMA",
        "TIP_SUMKI", "TIP_UTEPLITEL", "TKANDNA", "TKANPALATKI", "TKANTENTA", "TOLSH_KLIN", "TOLSHINA_FLISA", "TOCHNOSTHODA", "UGOLZRENIA", "USILENIE",
        "UTEPLITEL", "FOKUS", "FORM_KLIN", "FURNITURA", "CML2_ATTRIBUTES", "KHRONOGRAF", "CHEHOL","SHIRINA", "ELEM_PIT", "LUMEN", "PROP_2052", "PROP_2053",
        "PROP_2083", "PROP_2049", "PROP_2026", "PROP_2065", "PROP_2054", "PROP_2044", "SPEED", "STOP", "VILKA", "RAMA", "OBODA", "DIAMETR", "PPER",
        "ZPER", "KASSETA", "TSEP", "KERTKA", "VTULKI", "REZINA", "SHIFTER", "NAGRUZKA", "MANETKI", "PROP_159", "COLOR_REF2", "PROP_2027",
        "PROP_162", "SIZE_RAMA", "PROP_2017", "PROP_2055", "PROP_2069", "PROP_2062", "PROP_2061", "RECOMMEND", "NEW", "STOCK", "VIDEO",
    ];

    /**
     * @var string
     */
    protected $baseCurrency;

    /**
     * @var string
     */
    protected $currencyRub = 'RUB';

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
    protected $fileName = 'mobium.yml';

    /**
     * @var resource
     */
    protected $file;

    /**
     * @var string
     */
    protected $charset = 'utf-8';

    public function __construct()
    {
        \Bitrix\Main\Loader::IncludeModule("highloadblock");
        set_time_limit(0);
        ini_set('memory_limit', '4096M');
        if (true !==$this->beforeStart()){
            exit();
        }
        $this->start();
        $this->endExport();
    }


    public static function run(){
        $oObject = new static();
        return 'Mobium\Api\ExportYML::run();';
    }

    public static function runBalance()
    {
        return ExportBalance::run();
    }

    protected function beforeStart()
    {
        $this->createUser();
        \CCatalogDiscountSave::Disable();
        \CCatalogDiscountCoupon::ClearCoupon();
        if ($this->user->IsAuthorized()) {
            \CCatalogDiscountCoupon::ClearCouponsByManage($this->user->GetID());
        }
        $this->protocol = (\CMain::IsHTTPS() ? 'https://' : 'http://');
        $this->defineCurrency();
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
        if ($this->isError) {
            CEventLog::Log('WARNING','CAT_YAND_AGENT','mobium.api','exportYML',$this->filePath);
        }
        CCatalogDiscountSave::Enable();
        $this->deleteUser();
    }

    protected function start()
    {
        fwrite($this->file, $this->renderShopInfo());
        fwrite($this->file, $this->renderCurrency());
        fwrite($this->file, '<offers>'.PHP_EOL);
        $this->processCatalog();
        fwrite($this->file, '</offers>'.PHP_EOL.'</shop>'.PHP_EOL.'</yml_catalog>');
        fclose($this->file);
    }

    protected function processCatalog()
    {
        $oCatalogListResult = CCatalog::GetList(array(), array("YANDEX_EXPORT" => "Y", "PRODUCT_IBLOCK_ID" => 0), false, false, array('IBLOCK_ID'));
        while ($aCatalog = $oCatalogListResult->Fetch()){
            $aCatalog['IBLOCK_ID'] = (int) $aCatalog['IBLOCK_ID'];
            $aIBlockInfo = CIBlock::GetArrayByID($aCatalog['IBLOCK_ID']);
            if (empty($aIBlockInfo) || !is_array($aIBlockInfo)){
                continue;
            }
            if ('Y' != $aIBlockInfo['ACTIVE']){
                continue;
            }
            // Проверка прав доступа, можно и выкинуть.
            $bRights = false;
            if ('E' != $aIBlockInfo['RIGHTS_MODE']){
                $aRights = CIBlock::GetGroupPermissions($aCatalog['IBLOCK_ID']);
                if (!empty($aRights) && isset($aRights[2]) && 'R' <= $aRights[2]){
                    $bRights = true;
                }
            } else {
                $oRights = new CIBlockRights($aCatalog['IBLOCK_ID']);
                $aRights = $oRights->GetGroups(array('section_read', 'element_read'));
                if (!empty($aRights) && in_array('G2',$aRights)){
                    $bRights = true;
                }

            }
            if (!$bRights){
                continue;
            }

            $aFilters = ['IBLOCK_ID'=>$aCatalog['IBLOCK_ID'], 'ACTIVE'=>'Y', 'GLOBAL_ACTIVE'=>'Y'];
            $oCategoryResult = CIBlockSection::GetList(["left_margin"=>"asc"], $aFilters);
            $aAvailableSections = [];
            while ($aCategory = $oCategoryResult->Fetch()){
                $aTemp = [
                    'ID'=>(int) $aCategory['ID'],
                    'PARENT'=> (int) $aCategory['IBLOCK_SECTION_ID'],
                    'NAME'=>$aCategory['NAME']
                ];
                if ((int) $aCategory['PICTURE'] > 0){
                    $iPicId = (int) $aCategory['PICTURE'];
                    $aPicInfo = CFile::GetFileArray($iPicId);
                    if (is_array($aPicInfo)){
                        $aTemp['PICTURE'] = substr($aPicInfo["SRC"], 0, 1) == "/" ? $this->protocol.COption::GetOptionString("main", "server_name", "").CHTTP::urnEncode($aPicInfo["SRC"], 'utf-8') : $aPicInfo['SRC'];
                    }
                }
                $aAvailableSections[] = $aTemp;

            }
            fwrite($this->file, $this->renderCategories($aAvailableSections));
            // Загрузка товаров
            $aFilters = ['IBLOCK_ID'=>$aCatalog['IBLOCK_ID'], 'ACTIVE'=>'Y', 'ACTIVE_DATE'=>'Y'];
            $oProductsResult = CIBlockElement::GetList(array(), $aFilters, false, false, $this->selectProductsFields);

            $oPropsResult = Iblock\PropertyTable::getList([
                'select'=>['ID','CODE'],
                'filter'=>['=IBLOCK_ID'=>$aCatalog['IBLOCK_ID'], '@CODE'=>$this->productProps],
                'order'=>['SORT'=>'ASC', 'NAME'=>'ASC']
            ]);
            $aPropsDB = $oPropsResult->fetchAll();
            $iCnt = 0; $iTotalCount = 0;
            fwrite($this->file, '<offers>'.PHP_EOL);
            $aColumns = array_column($aPropsDB, 'ID');
            while ($aProduct = $oProductsResult->GetNext()) {
                /*if ($iTotalCount > 100){
                    break;
                }*/
                $fStart = microtime(true);
                $oProps = CIBlockElement::GetProperty($aProduct['IBLOCK_ID'], $aProduct['ID'], false, false, ['ID'=>$aColumns]);
                $aProps = [];
                while ($aTemp = $oProps->Fetch()){
                    if (!$aTemp['VALUE']){
                        continue;
                    }
                    $aProps[$aTemp['CODE']] = $aTemp;
                }

                $aProduct['PROPS'] = $aProps;
                $iTotalCount++;
                $iCnt++;
                $fTime = microtime(true);
                $aOffer = $this->createOffer($aProduct, $this->baseCurrency, $aAvailableSections, $this->protocol, $aCatalog['IBLOCK_ID']);
                if (false === $aOffer){
                    continue;
                }
                $fTime = microtime(true);
                $aProductOffers = CCatalogSKU::getExistOffers(array($aProduct['ID']), $aCatalog["IBLOCK_ID"]);
                if (count($aProductOffers) == 1 &&
                    isset($aProductOffers[$aProduct['ID']]) && $aProductOffers[$aProduct['ID']] !== false && count($aProductOffers[$aProduct['ID']]) > 0) {

                    $res = CCatalogSKU::getOffersList(array($aProduct['ID']), $aCatalog["IBLOCK_ID"]);
                    $aOffersIds = array_keys($res[$aProduct['ID']]);
                    foreach ($aOffersIds as $iId){

                        $aFilters['ID'] = $iId;
                        $aFilters['IBLOCK_ID']=static::OFFERS_IBLOCK_ID;
                        $aTempProduct = CIBlockElement::GetList(array(), $aFilters, false, false, $this->selectProductsFields)->GetNext();
                        if (false === ($aTempOffer = $this->createOffer($aTempProduct, $this->baseCurrency, $aAvailableSections, $this->protocol, static::OFFERS_IBLOCK_ID, false, $aOffer))){
                            continue;
                        }
                        fwrite($this->file, $this->renderOffer($aTempOffer, $this->protocol));
                    }

                } else {
                    fwrite($this->file, $this->renderOffer($aOffer, $this->protocol));
                }
                //echo('Check offer '.(microtime(true) - $fTime)."<br>");
                //echo('Total offer '.(microtime(true) - $fStart)."<br>");
                //echo "<br>"."<br>";
                if (100 <= $iCnt) {
                    $iCnt = 0;
                    CCatalogDiscount::ClearDiscountCache(array(
                        'PRODUCT' => true,
                        'SECTIONS' => true,
                        'PROPERTIES' => true
                    ));
                }
            }
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
        $sResult.= "<name>".$APPLICATION->ConvertCharset(htmlspecialcharsbx(COption::GetOptionString("main", "site_name", "")), LANG_CHARSET, $this->charset)."</name>\n";
        $sResult.= "<company>".$APPLICATION->ConvertCharset(htmlspecialcharsbx(COption::GetOptionString("main", "site_name", "")), LANG_CHARSET, $this->charset)."</company>\n";
        $sResult.= "<url>".$this->protocol.htmlspecialcharsbx(COption::GetOptionString("main", "server_name", ""))."</url>\n";
        $sResult.= "<platform>1C-Bitrix</platform>\n";
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
            $sResult.= '<currency id="'.$currency['CURRENCY'].'" rate="'.(CCurrencyRates::ConvertCurrency(1, $currency['CURRENCY'], $this->currencyRub)).'" />'."\n";
        }
        $sResult.= "</currencies>\n";
        return $sResult;
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
     * @param array $aCategories
     * @return string
     */
    protected function renderCategories($aCategories)
    {
        $sResult = '<categories>';
        foreach ($aCategories as $aCategory){
            $sResult .= '<category id="'.$aCategory['ID'].'" '.
                ($aCategory['PARENT'] > 0 ? ' parentId="'.$aCategory['PARENT'].'"' : '').
                (isset($aCategory['PICTURE']) ? ' picture="'.$aCategory['PICTURE'].'"' : '').
                '>'.
                $this->text2xml($aCategory['NAME'], true).'</category>'.PHP_EOL;
        }
        $sResult .= '</categories>';
        return $sResult;
    }

    protected function createOffer($aProduct,
                                          $sBaseCurrency,
                                          $aAvailableCategories,
                                          $sProtocol,
                                          $iCatalogIBlockID,
                                          $bLoadCategories=true,
                                          $aParent = null
    ){
        $aOffer = [];
        $aProduct['SERVER_NAME'] = $this->getProductServerName($aProduct);
        $aOffer['SERVER_NAME'] = $aProduct['SERVER_NAME'];
        $aOffer['available'] = $aProduct['CATALOG_AVAILABLE'] == 'Y';
        list($aMinPrice, $sCurrency) = $this->getOfferPrice($aProduct, $aProduct['LID'], $sBaseCurrency);
        $fPrice = $aMinPrice['BASE_PRICE'];
        $fOldPrice = null;
        if (isset($aMinPrice['DISCOUNT_PRICE']) && !empty($aMinPrice['DISCOUNT_PRICE'])){
            $fOldPrice = $fPrice;
            $fPrice = $aMinPrice['DISCOUNT_PRICE'];
        }
        if ($aMinPrice <= 0){
            return false;
        }
        if ($bLoadCategories){
            $aOffer['categories'] = [];
            $oDBResult = CIBlockElement::GetElementGroups($aProduct["ID"], false, array('ID', 'ADDITIONAL_PROPERTY_ID'));
            $bNoActiveGroup = True;
            while ($aResult = $oDBResult->Fetch())
            {
                if (0 < intval($aResult['ADDITIONAL_PROPERTY_ID'])){
                    continue;
                }
                $aOffer['categories'][] = $aResult['ID'];
                if ($bNoActiveGroup && in_array(intval($aResult["ID"]), array_column($aAvailableCategories, 'ID'))) {
                    $bNoActiveGroup = False;
                }
            }
            if ($bNoActiveGroup){
                return false;
            }
        }
        if ('' == $aProduct['DETAIL_PAGE_URL']){
            $aProduct['DETAIL_PAGE_URL'] = '/';
        } else {
            $aProduct['DETAIL_PAGE_URL'] = str_replace(' ', '%20', $aProduct['DETAIL_PAGE_URL']);
        }
        if ('' == $aProduct['~DETAIL_PAGE_URL']) {
            $aProduct['~DETAIL_PAGE_URL'] = '/';
        } else {
            $aProduct['~DETAIL_PAGE_URL'] = str_replace(' ', '%20', $aProduct['~DETAIL_PAGE_URL']);
        }

        if ((int) $aProduct['DETAIL_PICTURE'] > 0 || (int) $aProduct['PREVIEW_PICTURE'] > 0){
            $iPicId = (int) $aProduct['DETAIL_PICTURE'];
            if ($iPicId <= 0){
                $iPicId = (int) $aProduct['PREVIEW_PICTURE'];
            }
            $aPicInfo = CFile::GetFileArray($iPicId);
            if (is_array($aPicInfo)){
                $aOffer['picture'] = substr($aPicInfo["SRC"], 0, 1) == "/" ? $sProtocol.$aProduct['SERVER_NAME'].CHTTP::urnEncode($aPicInfo["SRC"], 'utf-8') : $aPicInfo['SRC'];
            }
        }
        $aOffer['available'] = $aProduct['CATALOG_AVAILABLE'] == 'Y';
        if (isset($aProduct['CATALOG_QUANTITY']) && isset($aProduct['CATALOG_QUANTITY_TRACE']) && isset($aProduct['CATALOG_CAN_BUY_ZERO']))
        {
            $aOffer['available'] = !((float)$aProduct['CATALOG_QUANTITY'] <= 0 && $aProduct['CATALOG_QUANTITY_TRACE'] == 'Y' && $aProduct['CATALOG_CAN_BUY_ZERO'] == 'N');
        }
        $aOffer['id'] = $aProduct['ID'];
        $aOffer['url'] = $sProtocol.$aProduct['SERVER_NAME'].htmlspecialcharsbx($aProduct["~DETAIL_PAGE_URL"]);
        if (null !== $fOldPrice && $fPrice !== $fOldPrice){
            $aOffer['old_price']=$fOldPrice;
        }
        $aOffer['price'] = $fPrice;
        $aOffer['currency'] = $sCurrency;
        $aOffer['name'] = $aProduct['~NAME'];
        $aOffer['prop_res'] = CIBlockElement::GetProperty($iCatalogIBlockID, $aProduct['ID']);
        //$aOffer['prop_extracted'] = mobium_extractProps($aOffer['prop_res'], $sProtocol);

        $aProps = [];
        if (isset($aProduct['PROPS'])){
            $aOffer['props'] = $aProduct['PROPS'];
            foreach ($aOffer['props'] as $sCode=>$aPropData){
                if ($aPropData['CODE'] === 'BRAND'){
                    //DISPLAY_VALUE
                    $aData = CIBlockFormatProperties::GetDisplayValue(['NAME' => $aOffer['name']], $aPropData, 'catalog_out');
                    $sValue = $this->text2xml(strip_tags($aData['DISPLAY_VALUE']), true);

                } else  {
                    $sValue = $this->text2xml($aPropData['VALUE'], true);
                }
                if ($aPropData['PROPERTY_TYPE'] === 'L') {
                    $sValue = $this->text2xml($aPropData['VALUE_ENUM']);
                }
                if (!empty($sValue)){
                    $aProps[$aPropData['CODE']] = ['name'=>$aPropData['NAME'], 'value'=>$sValue];
                }
            }
        }
        $aOffer['offer_props'] = array_merge($this->extractProps($aOffer['prop_res'], $sProtocol), $aProps);
        $aOffer['description'] = $this->text2xml(
            preg_replace_callback(
                "'&[^;]*;'",
                function ($arg){
                    if (in_array($arg[0], array("&quot;", "&amp;", "&lt;", "&gt;")))
                        return $arg[0];
                    else
                        return " ";
                },
                $aProduct["~DETAIL_TEXT"]
            )
        );
        if (null !== $aParent){
            if (isset($aParent['offer_props'])){
                $aOffer['offer_props'] = array_merge($aParent['offer_props'], $aOffer['offer_props']);
            }
            $aOffer['group_id'] = $aParent['id'];
            $aOffer['description'] = $aParent['description'];
            $aOffer['name'] = $aParent['name'];
            $aOffer['categories'] = $aParent['categories'];
            unset($aOffer['offer_props']['CML2_ATTRIBUTES']);
            //получаем массив значений множественного свойства CML2_ATTRIBUTES в которое стандартно выгружаются характеристики ТП из 1С
            $resCml2Attributes = CIBlockElement::GetProperty(OFFERS_IBLOCK_ID, $aOffer['id'], array('sort' => 'asc'), array('CODE' => 'CML2_ATTRIBUTES'));
            $aModifications = $this->extractProps($resCml2Attributes, $sProtocol, true);
            foreach ($aModifications as $iIndex => $aItem) {
                $aOffer['offer_props']['modification_'.$iIndex] = $aItem;
            }
        }
        return $aOffer;
    }

    /**
     * @param array $aOffer
     * @param mixed $mLID
     * @param mixed $mBaseCurrency
     * @param string $sRUR
     * @return array
     */
    protected function getOfferPrice($aOffer, $mLID, $mBaseCurrency, $sRUR = 'RUB'){
        $minPriceCurrency = "";

        if ($arPrice = CCatalogProduct::GetOptimalPrice(
            $aOffer['ID'],
            1,
            array(2), // anonymous
            'N',
            array(),
            $mLID,
            array()
        ))
        {
            $minPrice = $arPrice['DISCOUNT_PRICE'];
            $minPriceCurrency = $mBaseCurrency;
            if ($mBaseCurrency != $sRUR)
            {
                $minPriceRUR = CCurrencyRates::ConvertCurrency($minPrice, $mBaseCurrency, $sRUR);
            }
            else
            {
                $minPriceRUR = $minPrice;
            }
            $minPriceGroup = $arPrice['PRICE']['CATALOG_GROUP_ID'];
        }
        return [$arPrice['RESULT_PRICE'], $minPriceCurrency];
    }

    /**
     * @param array $aProduct
     * @return mixed|string
     */
    protected function getProductServerName($aProduct){
        static $aSiteServers = [];
        if (!array_key_exists($aProduct['LID'], $aSiteServers)){
            $sServerName = '';
            $b= 'sort';$o='asc';
            $oSite = CSite::GetList($b, $o, array("LID" => $aProduct["LID"]));
            if ($aSite = $oSite->Fetch()){
                $sServerName = $aSite['SERVER_NAME'];
            }
            if (strlen($sServerName) <= 0 && defined('SITE_SERVER_NAME')){
                $sServerName = SITE_SERVER_NAME;
            }
            if (strlen($sServerName) <= 0){
                $sServerName = COption::GetOptionString("main", "server_name", "");
            }
            $aSiteServers[$aProduct['LID']] = $sServerName;
        }
        return $aSiteServers[$aProduct['LID']] ?? '';
    }

    /**
     * @param CDBResult $oPropsRes
     * @param string $sProtocol
     * @param bool $bAsArray
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    protected function extractProps($oPropsRes, $sProtocol, $bAsArray=false){
        $aResult = [];
        while ($aProp = $oPropsRes->Fetch()){

            $bContinue = false;
            if (!empty($aProp['VALUE'])){
                $sPropName = $aProp['NAME'];
                $value = '';
                if (isset($aProp['USER_TYPE']) && !empty($aProp['USER_TYPE']) && isset($aProp['USER_TYPE_SETTINGS']) && !empty($aProp['USER_TYPE_SETTINGS'])){
                    continue;
                    $aUserType = \CIBlockProperty::GetUserType($aProp['USER_TYPE']);
                    if (isset($aUserType['GetPublicViewHTML'])){
                        $value = call_user_func_array($aUserType['GetPublicViewHTML'],
                            array(
                                $aProp,
                                array("VALUE" => $aProp['VALUE']),
                                array('MODE' => 'SIMPLE_TEXT'),
                            )
                        );
                    }
                } else {
                    switch ($aProp['PROPERTY_TYPE']){
                        case Iblock\PropertyTable::TYPE_ELEMENT:
                            if (!empty($aProp['VALUE']))
                            {
                                $arCheckValue = array();
                                if (!is_array($aProp['VALUE']))
                                {
                                    $aProp['VALUE'] = (int)$aProp['VALUE'];
                                    if ($aProp['VALUE'] > 0)
                                        $arCheckValue[] = $aProp['VALUE'];
                                }
                                else
                                {
                                    foreach ($aProp['VALUE'] as $intValue)
                                    {
                                        $intValue = (int)$intValue;
                                        if ($intValue > 0)
                                            $arCheckValue[] = $intValue;
                                    }
                                    unset($intValue);
                                }
                                if (!empty($arCheckValue))
                                {
                                    $filter = array(
                                        '@ID' => $arCheckValue
                                    );
                                    if ($aProp['LINK_IBLOCK_ID'] > 0)
                                        $filter['=IBLOCK_ID'] = $aProp['LINK_IBLOCK_ID'];

                                    $iterator = Iblock\ElementTable::getList(array(
                                        'select' => array('ID', 'NAME'),
                                        'filter' => array($filter)
                                    ));
                                    while ($row = $iterator->fetch())
                                    {
                                        $value .= ($value ? ', ' : '').$row['NAME'];
                                    }
                                    unset($row, $iterator);
                                }
                            }
                            break;
                        case Iblock\PropertyTable::TYPE_SECTION:
                            $arCheckValue = array();
                            if (!is_array($aProp['VALUE']))
                            {
                                $aProp['VALUE'] = (int)$aProp['VALUE'];
                                if ($aProp['VALUE'] > 0)
                                    $arCheckValue[] = $aProp['VALUE'];
                            }
                            else
                            {
                                foreach ($aProp['VALUE'] as $intValue)
                                {
                                    $intValue = (int)$intValue;
                                    if ($intValue > 0)
                                        $arCheckValue[] = $intValue;
                                }
                                unset($intValue);
                            }
                            if (!empty($arCheckValue))
                            {
                                $filter = array(
                                    '@ID' => $arCheckValue
                                );
                                if ($aProp['LINK_IBLOCK_ID'] > 0)
                                    $filter['=IBLOCK_ID'] = $aProp['LINK_IBLOCK_ID'];

                                $iterator = Iblock\SectionTable::getList(array(
                                    'select' => array('ID', 'NAME'),
                                    'filter' => array($filter)
                                ));
                                while ($row = $iterator->fetch())
                                {
                                    $value .= ($value ? ', ' : '').$row['NAME'];
                                }
                                unset($row, $iterator);
                            }
                            break;
                        case Iblock\PropertyTable::TYPE_LIST:
                            $bContinue = true;
                            break;
                            if (!empty($aProp['~VALUE']))
                            {
                                if (is_array($aProp['~VALUE']))
                                    $value .= implode(', ', $aProp['~VALUE']);
                                else
                                    $value .= $aProp['~VALUE'];
                            }
                            break;
                        case Iblock\PropertyTable::TYPE_FILE:
                            if (!empty($aProp['VALUE']))
                            {
                                if (is_array($aProp['VALUE']))
                                {
                                    foreach ($aProp['VALUE'] as $intValue)
                                    {
                                        $intValue = (int)$intValue;
                                        if ($intValue > 0)
                                        {
                                            if ($ar_file = CFile::GetFileArray($intValue)) {
                                                if (!CFile::IsImage($ar_file["FILE_NAME"], $ar_file["CONTENT_TYPE"])){
                                                    continue;
                                                }
                                                if(substr($ar_file["SRC"], 0, 1) == "/")
                                                    $strFile = $sProtocol.COption::GetOptionString("main", "server_name", "").CHTTP::urnEncode($ar_file['SRC'], 'utf-8');
                                                else
                                                    $strFile = $ar_file["SRC"];
                                                $aPictures[] = $strFile;
                                                $bContinue = true;
                                            }
                                        }
                                    }
                                    unset($intValue);
                                }
                                else
                                {
                                    $aProp['VALUE'] = (int)$aProp['VALUE'];
                                    if ($aProp['VALUE'] > 0)
                                    {
                                        if ($ar_file = CFile::GetFileArray($aProp['VALUE']))
                                        {
                                            if (!CFile::IsImage($ar_file["FILE_NAME"], $ar_file["CONTENT_TYPE"])){
                                                continue;
                                            }
                                            if(substr($ar_file["SRC"], 0, 1) == "/")
                                                $strFile = $sProtocol.COption::GetOptionString("main", "server_name", "").CHTTP::urnEncode($ar_file['SRC'], 'utf-8');
                                            else
                                                $strFile = $ar_file["SRC"];

                                            $aPictures[] = $strFile;
                                            $bContinue = true;
                                        }
                                    }
                                }
                            }
                            break;
                        default:
                            if ($aProp['CODE'] == 'CML2_ATTRIBUTES') {
                                $sPropName = $aProp['DESCRIPTION'];
                                $value = $aProp['VALUE'];
                            }
                            break;
                    }
                }
                if($bContinue) continue;
                if (!empty($value)){
                    if ($bAsArray){
                        $aResult[] = ['name'=>$sPropName, 'value'=>$value, 'code'=>$aProp['CODE']];
                    } else {
                        $aResult[$aProp['CODE']] = ['name'=>$sPropName, 'value'=>$value];
                    }

                }

            }
        }
        return $aResult;
    }

    protected function renderOffer($aOffer, $sProtocol){
        if ($aOffer['available'] === false){
            return '';
        }
        $aOfferAttrs = [];
        $aOfferAttrs[] = 'id="'.$aOffer['id'].'"';
        $aOfferAttrs[] = 'available="'.($aOffer['available'] ?  'true' : 'false').'"';
        $aPictures = [];
        $sVendorCode = null;
        if(isset($aOffer['group_id'])){
            $aOfferAttrs[] = 'group_id="'.$aOffer['group_id'].'"';
        }
        $sResult = '<offer'.(!empty($aOfferAttrs) ? ' '.implode(' ', $aOfferAttrs) : '').'>'.PHP_EOL;
        if (isset($aOffer['categories']) && !empty($aOffer['categories'])){
            foreach ($aOffer['categories'] as $sCategoryId){
                $sResult .= '<categoryId>'.$sCategoryId.'</categoryId>'.PHP_EOL;
            }
        }
        if (isset($aOffer['url'])){
            $sResult .= '<url>'.$this->text2xml($aOffer['url'], true).'</url>'.PHP_EOL;
        }
        if (isset($aOffer['price'])){
            $sResult .= '<price>'.$aOffer['price'].'</price>'.PHP_EOL;
        }
        if (isset($aOffer['old_price'])){
            $sResult .= '<oldprice>'. $aOffer['old_price'].'</oldprice>';
        }
        if (isset($aOffer['currency'])){
            $sResult .= '<currencyId>'.$aOffer['currency'].'</currencyId>'.PHP_EOL;
        }
        if (isset($aOffer['picture'])){
            $aPictures[] = $aOffer['picture'];
        }
        if (isset($aOffer['name'])){
            $sResult .= '<name>'.$this->text2xml($aOffer['name'], true).'</name>'.PHP_EOL;
        }
        if (isset($aOffer['description'])){
            $sResult .= '<description><![CDATA['.$aOffer['description'].']]></description>'.PHP_EOL;
        }
        $aProps = [];
        if (false && isset($aOffer['props'])){
            foreach ($aOffer['props'] as $sPropID => $aPropData){
                if (!empty($aPropData['VALUE'])){
                    if ($aPropData['CODE'] === 'BRAND'){
                        //DISPLAY_VALUE
                        $aData = CIBlockFormatProperties::GetDisplayValue(['NAME' => $aOffer['name']], $aPropData, 'catalog_out');
                        $sValue = $this->text2xml(strip_tags($aData['DISPLAY_VALUE']), true);

                    } elseif ($aPropData['CODE'] === 'CML2_ARTICLE'){
                        $sVendorCode = $aPropData['VALUE'];
                    } else  {
                        $sValue = $this->text2xml($aPropData['VALUE'], true);
                    }
                    if ($aPropData['PROPERTY_TYPE'] === 'L') {
                        $sValue = $this->text2xml($aPropData['VALUE_ENUM']);
                    }
                    $aProps[] = $aPropData['CODE'];
                    $sResult .= '<param name="'.$this->text2xml($aPropData['NAME'], true).'">'.$sValue.'</param>'.PHP_EOL;
                }

            }
        }

        if (false && isset($aOffer['parent_props'])){
            foreach ($aOffer['parent_props'] as $sPropID => $aPropData){
                if (in_array($aPropData['CODE'], $aProps)){
                    continue;
                }
                if (!empty($aPropData['VALUE'])){
                    if ($aPropData['CODE'] === 'BRAND'){
                        $sValue = $this->text2xml(strip_tags(CIBlockFormatProperties::GetDisplayValue(['NAME' => $aOffer['name']], $aPropData, 'catalog_out')), true);
                    } elseif ($aPropData['CODE'] === 'CML2_ARTICLE'){
                        $sVendorCode = $aPropData['VALUE'];
                    } else {
                        $sValue = $this->text2xml($aPropData['VALUE'], true);
                    }
                    if ($aPropData['PROPERTY_TYPE'] === 'L') {
                        $sValue = $this->text2xml($aPropData['VALUE_ENUM']);
                    }
                    $sResult .= '<param name="'.$this->text2xml($aPropData['NAME'], true).'">'.$sValue.'</param>'.PHP_EOL;

                }

            }
        }
        if (false && isset($aOffer['prop_res']) && $aOffer['prop_res'] instanceof CDBResult){
            $aPropsRes = $this->extractProps($aOffer['prop_res'], $sProtocol);
            foreach ($aPropsRes as $sCode => $aData){
                if (in_array($sCode, $aProps)){
                    continue;
                }
                if (!empty($sVal = $this->text2xml($aData['value'], true))){
                    $sResult .= '<param name="'.$this->text2xml($aData['name'], true).'">'.$sVal.'</param>'.PHP_EOL;
                    $aProps[] = $sCode;
                    if ($sCode === 'CML2_ARTICLE'){
                        $sVendorCode = $aData['value'];
                    }
                }
            }
        }
        if (false && isset($aOffer['parent_prop_res']) && $aOffer['parent_prop_res'] instanceof CDBResult){
            $aPropsRes = $this->extractProps($aOffer['parent_prop_res'], $sProtocol);
            foreach ($aPropsRes as $sCode => $aData){
                if (in_array($sCode, $aProps)){
                    continue;
                }
                if (!empty($sVal = $this->text2xml($aData['value'], true))){
                    $sResult .= '<param name="'.$this->text2xml($aData['name'], true).'">'.$sVal.'</param>'.PHP_EOL;
                    $aProps[] = $sCode;
                    if ($sCode === 'CML2_ARTICLE'){
                        $sVendorCode = $aData['value'];
                    }
                }
            }
        }
        if (isset($aOffer['offer_props'])){
            foreach ($aOffer['offer_props'] as $sCode => $aData){
                if ($sCode === 'CML2_ARTICLE'){
                    $sVendorCode = $aData['value'];
                    continue;
                }
                if (!empty($sVal = $this->text2xml($aData['value'], true, false))){
                    $sResult .= '<param code="'.$sCode.'" name="'.$this->text2xml($aData['name'], true).'">'.$sVal.'</param>'.PHP_EOL;

                } else {
                    $sResult .= '<param code="'.$sCode.'" name="'.$this->text2xml($aData['name'], true).'">'.$aData['value'].'</param>'.PHP_EOL;
                }

            }
        }
        if (isset($aOffer['print'])){
            if ($aOffer['print'] instanceof CDBResult){
                $sTemp = '';
                while($aTemp = $aOffer['print']->Fetch()){
                    $sTemp.=print_r($aTemp, true).PHP_EOL;
                }
                $sResult.='<d>'.$sTemp.'</d>'.PHP_EOL;
            }
        }
        foreach ($aPictures as $sPic){
            $sResult .= '<picture>'.$sPic.'</picture>'.PHP_EOL;
        }
        if (null !== $sVendorCode){
            $sResult .= '<vendorCode>'.$this->text2xml($sVendorCode).'</vendorCode>'.PHP_EOL;
        }
        $sResult .= '</offer>'.PHP_EOL;
        return $sResult;
    }
}