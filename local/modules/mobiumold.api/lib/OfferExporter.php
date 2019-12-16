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

abstract class OfferExporter
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
     * @var string
     */
    protected $serverName;

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

    protected function getServerName(){
        if (!$this->serverName){
            $this->serverName = (\CMain::IsHTTPS() ? 'https://' : 'http://') . COption::GetOptionString("main", "server_name", $_SERVER['SERVER_NAME']);
        }
        return $this->serverName;
    }

    /**
     * @return string
     */
    abstract protected function getFileName();
}