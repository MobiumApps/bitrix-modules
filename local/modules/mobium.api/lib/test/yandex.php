<?php
use Bitrix\Currency;
use Bitrix\Iblock;

\Bitrix\Main\Loader::IncludeModule("highloadblock");

if (!function_exists('mobium_getProductServerName')){
    function mobium_getProductServerName($aProduct){
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
}

if (!function_exists('mobium_getOfferPrice')){
    function mobium_getOfferPrice($aOffer, $mLID, $mBaseCurrency, $sRUR = 'RUB'){
        $minPrice = 0;
        $minPriceRUR = 0;
        $minPriceGroup = 0;
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
}

if (!function_exists("yandex_text2xml"))
{
    function yandex_text2xml($text, $bHSC = false, $bDblQuote = false)
    {
        global $APPLICATION;

        $bHSC = (true == $bHSC ? true : false);
        $bDblQuote = (true == $bDblQuote ? true: false);

        if ($bHSC)
        {
            $text = htmlspecialcharsbx($text);
            if ($bDblQuote)
                $text = str_replace('&quot;', '"', $text);
        }
        $text = preg_replace('/[\x01-\x08\x0B-\x0C\x0E-\x1F]/', "", $text);
        $text = str_replace("'", "&apos;", $text);
        $text = $APPLICATION->ConvertCharset($text, LANG_CHARSET, 'windows-1251');

        return $text;
    }
}

if (!function_exists('mobium_renderShopInfo')) {
    function mobium_renderShopInfo($sProtocol){
        global $APPLICATION;
        $sResult = '<? header("Content-Type: text/xml; charset=windows-1251");?>';
        $sResult.= '<?echo "<?xml version=\"1.0\" encoding=\"windows-1251\"?>"?>';
        $sResult.= "\n<!DOCTYPE yml_catalog SYSTEM \"shops.dtd\">\n";
        $sResult.= "<yml_catalog date=\"".date("Y-m-d H:i")."\">\n";
        $sResult.= "<shop>\n";
        $sResult.= "<name>".$APPLICATION->ConvertCharset(htmlspecialcharsbx(COption::GetOptionString("main", "site_name", "")), LANG_CHARSET, 'windows-1251')."</name>\n";
        $sResult.= "<company>".$APPLICATION->ConvertCharset(htmlspecialcharsbx(COption::GetOptionString("main", "site_name", "")), LANG_CHARSET, 'windows-1251')."</company>\n";
        $sResult.= "<url>".$sProtocol.htmlspecialcharsbx(COption::GetOptionString("main", "server_name", ""))."</url>\n";
        $sResult.= "<platform>1C-Bitrix</platform>\n";
        return $sResult;
    }
}

if (!function_exists('mobium_renderCurrency')){
    function mobium_renderCurrency($sRUR){
        $sResult = "<currencies>\n";
        $arCurrencyAllowed = array('RUR', 'RUB', 'USD', 'EUR', 'UAH', 'BYR', 'BYN', 'KZT');
        $currencyIterator = Currency\CurrencyTable::getList(array(
            'select' => array('CURRENCY', 'SORT'),
            'filter' => array('@CURRENCY' => $arCurrencyAllowed),
            'order' => array('SORT' => 'ASC')
        ));
        while ($currency = $currencyIterator->fetch())
            $sResult.= '<currency id="'.$currency['CURRENCY'].'" rate="'.(CCurrencyRates::ConvertCurrency(1, $currency['CURRENCY'], $sRUR)).'" />'."\n";
        $sResult.= "</currencies>\n";
        return $sResult;
    }
}

if (!function_exists('mobium_renderCategories')){
    function mobium_renderCategories($aCategories){
        $sResult = '<categories>';
        foreach ($aCategories as $aCategory){
            $sResult .= '<category id="'.$aCategory['ID'].'" '.
                ($aCategory['PARENT'] > 0 ? ' parentId="'.$aCategory['PARENT'].'"' : '').
                (isset($aCategory['PICTURE']) ? ' picture="'.$aCategory['PICTURE'].'"' : '').
                '>'.
                yandex_text2xml($aCategory['NAME'], true).'</category>'.PHP_EOL;
        }
        $sResult .= '</categories>';
        return $sResult;
    }
}

if (!function_exists('mobium_renderOffer')){
    function mobium_renderOffer($aOffer, $sProtocol){
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
            $sResult .= '<url>'.yandex_text2xml($aOffer['url'], true).'</url>'.PHP_EOL;
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
            //$sResult .= '<picture>'.$aOffer['picture'].'</picture>'.PHP_EOL;
        }
        if (isset($aOffer['name'])){
            $sResult .= '<name>'.yandex_text2xml($aOffer['name'], true).'</name>'.PHP_EOL;
        }
        if (isset($aOffer['description'])){
            $sResult .= '<description><![CDATA['.$aOffer['description'].']]></description>'.PHP_EOL;
        }
        $aProps = [];
        if (false && isset($aOffer['props'])){
            foreach ($aOffer['props'] as $sPropID => $aPropData){
                if (!empty($aPropData['VALUE'])){
                    /*$aData = CIBlockFormatProperties::GetDisplayValue(['NAME' => $aOffer['name']], $aPropData, 'catalog_out');
                    $sValue = yandex_text2xml(strip_tags(is_array($aData) ? $aData['DISPLAY_VALUE'] : $aData), true);
                    $aProps[$aPropData['CODE']] = ['value'=>$sValue, 'name'=>$aPropData['NAME']];
                    if ($aPropData['PROPERTY_TYPE'] === 'L'){
                        $aProps[$aPropData['CODE']]['value'] = $aPropData['VALUE_ENUM'];
                    }*/
                    /*if ($aPropData['CODE'] == 'MENWOMEN'&& $aPropData['VALUE'] !== null){
                        var_dump($aPropData);
                    }*/
                    if ($aPropData['CODE'] === 'BRAND'){
                        //DISPLAY_VALUE
                        $aData = CIBlockFormatProperties::GetDisplayValue(['NAME' => $aOffer['name']], $aPropData, 'catalog_out');
                        $sValue = yandex_text2xml(strip_tags($aData['DISPLAY_VALUE']), true);

                    } elseif ($aPropData['CODE'] === 'CML2_ARTICLE'){
                        $sVendorCode = $aPropData['VALUE'];
                    } else  {
                        $sValue = yandex_text2xml($aPropData['VALUE'], true);
                    }
                    if ($aPropData['PROPERTY_TYPE'] === 'L') {
                        $sValue = yandex_text2xml($aPropData['VALUE_ENUM']);
                    }
                    $aProps[] = $aPropData['CODE'];
                    $sResult .= '<param name="'.yandex_text2xml($aPropData['NAME'], true).'">'.$sValue.'</param>'.PHP_EOL;
                }

            }
        }

        //var_dump($aOffer);
        if (false && isset($aOffer['parent_props'])){
            foreach ($aOffer['parent_props'] as $sPropID => $aPropData){
                if (in_array($aPropData['CODE'], $aProps)){
                    continue;
                }
                if (!empty($aPropData['VALUE'])){
                    /*$aData = CIBlockFormatProperties::GetDisplayValue(['NAME' => $aOffer['name']], $aPropData, 'catalog_out');
                    //var_dump(is_array($aData) ? $aData['DISPLAY_VALUE'] : $aData);
                    $sValue = yandex_text2xml(strip_tags(is_array($aData) ? $aData['DISPLAY_VALUE'] : $aData), true);
                    $aProps[$aPropData['CODE']] = ['value'=>$sValue, 'name'=>$aPropData['NAME']];
                    if ($aPropData['PROPERTY_TYPE'] === 'L'){
                        $aProps[$aPropData['CODE']]['value'] = $aPropData['VALUE_ENUM'];
                    }*/

                    if ($aPropData['CODE'] === 'BRAND'){
                        $sValue = yandex_text2xml(strip_tags(CIBlockFormatProperties::GetDisplayValue(['NAME' => $aOffer['name']], $aPropData, 'catalog_out')), true);
                    } elseif ($aPropData['CODE'] === 'CML2_ARTICLE'){
                        $sVendorCode = $aPropData['VALUE'];
                    } else {
                        $sValue = yandex_text2xml($aPropData['VALUE'], true);
                    }
                    if ($aPropData['PROPERTY_TYPE'] === 'L') {
                        $sValue = yandex_text2xml($aPropData['VALUE_ENUM']);
                    }
                    $sResult .= '<param name="'.yandex_text2xml($aPropData['NAME'], true).'">'.$sValue.'</param>'.PHP_EOL;

                    //$sResult .= '<param name="'.yandex_text2xml($aPropData['NAME'], true).'">'.yandex_text2xml(strip_tags($aPropData['VALUE']), true).'</param>'.PHP_EOL;
                }

            }
        }
        if (false && isset($aOffer['prop_res']) && $aOffer['prop_res'] instanceof CDBResult){
            $aPropsRes = mobium_extractProps($aOffer['prop_res'], $sProtocol);
            foreach ($aPropsRes as $sCode => $aData){
                if (in_array($sCode, $aProps)){
                    continue;
                }
                if (!empty($sVal = yandex_text2xml($aData['value'], true))){
                    $sResult .= '<param name="'.yandex_text2xml($aData['name'], true).'">'.$sVal.'</param>'.PHP_EOL;
                    $aProps[] = $sCode;
                    if ($sCode === 'CML2_ARTICLE'){
                        $sVendorCode = $aData['value'];
                    }
                }
            }
//            while ($aProp = $aOffer['prop_res']->Fetch()){
//                if (in_array($aProp['CODE'], $aProps)){
//                    continue;
//                }
//                $bContinue = false;
//                if (!empty($aProp['VALUE'])){
//                    $sPropName = $aProp['NAME'];
//                //if (true){
//                    $value = '';
//                    if (isset($aProp['USER_TYPE']) && !empty($aProp['USER_TYPE']) && isset($aProp['USER_TYPE_SETTINGS']) && !empty($aProp['USER_TYPE_SETTINGS'])){
//                        continue;
//                        $aUserType = \CIBlockProperty::GetUserType($aProp['USER_TYPE']);
//                        if (isset($aUserType['GetPublicViewHTML'])){
//                            $value = call_user_func_array($aUserType['GetPublicViewHTML'],
//                                array(
//                                    $aProp,
//                                    array("VALUE" => $aProp['VALUE']),
//                                    array('MODE' => 'SIMPLE_TEXT'),
//                                )
//                            );
//                        }
//                    } else {
//                        switch ($aProp['PROPERTY_TYPE']){
//                            case Iblock\PropertyTable::TYPE_ELEMENT:
//                                if (!empty($aProp['VALUE']))
//                                {
//                                    $arCheckValue = array();
//                                    if (!is_array($aProp['VALUE']))
//                                    {
//                                        $aProp['VALUE'] = (int)$aProp['VALUE'];
//                                        if ($aProp['VALUE'] > 0)
//                                            $arCheckValue[] = $aProp['VALUE'];
//                                    }
//                                    else
//                                    {
//                                        foreach ($aProp['VALUE'] as $intValue)
//                                        {
//                                            $intValue = (int)$intValue;
//                                            if ($intValue > 0)
//                                                $arCheckValue[] = $intValue;
//                                        }
//                                        unset($intValue);
//                                    }
//                                    if (!empty($arCheckValue))
//                                    {
//                                        $filter = array(
//                                            '@ID' => $arCheckValue
//                                        );
//                                        if ($aProp['LINK_IBLOCK_ID'] > 0)
//                                            $filter['=IBLOCK_ID'] = $aProp['LINK_IBLOCK_ID'];
//
//                                        $iterator = Iblock\ElementTable::getList(array(
//                                            'select' => array('ID', 'NAME'),
//                                            'filter' => array($filter)
//                                        ));
//                                        while ($row = $iterator->fetch())
//                                        {
//                                            $value .= ($value ? ', ' : '').$row['NAME'];
//                                        }
//                                        unset($row, $iterator);
//                                    }
//                                }
//                                break;
//                            case Iblock\PropertyTable::TYPE_SECTION:
//                                $arCheckValue = array();
//                                if (!is_array($aProp['VALUE']))
//                                {
//                                    $aProp['VALUE'] = (int)$aProp['VALUE'];
//                                    if ($aProp['VALUE'] > 0)
//                                        $arCheckValue[] = $aProp['VALUE'];
//                                }
//                                else
//                                {
//                                    foreach ($aProp['VALUE'] as $intValue)
//                                    {
//                                        $intValue = (int)$intValue;
//                                        if ($intValue > 0)
//                                            $arCheckValue[] = $intValue;
//                                    }
//                                    unset($intValue);
//                                }
//                                if (!empty($arCheckValue))
//                                {
//                                    $filter = array(
//                                        '@ID' => $arCheckValue
//                                    );
//                                    if ($aProp['LINK_IBLOCK_ID'] > 0)
//                                        $filter['=IBLOCK_ID'] = $aProp['LINK_IBLOCK_ID'];
//
//                                    $iterator = Iblock\SectionTable::getList(array(
//                                        'select' => array('ID', 'NAME'),
//                                        'filter' => array($filter)
//                                    ));
//                                    while ($row = $iterator->fetch())
//                                    {
//                                        $value .= ($value ? ', ' : '').$row['NAME'];
//                                    }
//                                    unset($row, $iterator);
//                                }
//                                break;
//                            case Iblock\PropertyTable::TYPE_LIST:
//                                $bContinue = true;
//                                break;
//                                if (!empty($aProp['~VALUE']))
//                                {
//                                    if (is_array($aProp['~VALUE']))
//                                        $value .= implode(', ', $aProp['~VALUE']);
//                                    else
//                                        $value .= $aProp['~VALUE'];
//                                }
//                                break;
//                            case Iblock\PropertyTable::TYPE_FILE:
//                                if (!empty($aProp['VALUE']))
//                                {
//                                    if (is_array($aProp['VALUE']))
//                                    {
//                                        foreach ($aProp['VALUE'] as $intValue)
//                                        {
//                                            $intValue = (int)$intValue;
//                                            if ($intValue > 0)
//                                            {
//                                                if ($ar_file = CFile::GetFileArray($intValue)) {
//                                                    if (!CFile::IsImage($ar_file["FILE_NAME"], $ar_file["CONTENT_TYPE"])){
//                                                        continue;
//                                                    }
//                                                    if(substr($ar_file["SRC"], 0, 1) == "/")
//                                                        $strFile = $sProtocol.$aOffer['SERVER_NAME'].CHTTP::urnEncode($ar_file['SRC'], 'utf-8');
//                                                    else
//                                                        $strFile = $ar_file["SRC"];
//                                                    $aPictures[] = $strFile;
//                                                    $bContinue = true;
//                                                }
//                                            }
//                                        }
//                                        unset($intValue);
//                                    }
//                                    else
//                                    {
//                                        $aProp['VALUE'] = (int)$aProp['VALUE'];
//                                        if ($aProp['VALUE'] > 0)
//                                        {
//                                            if ($ar_file = CFile::GetFileArray($aProp['VALUE']))
//                                            {
//                                                if (!CFile::IsImage($ar_file["FILE_NAME"], $ar_file["CONTENT_TYPE"])){
//                                                    continue;
//                                                }
//                                                if(substr($ar_file["SRC"], 0, 1) == "/")
//                                                    $strFile = $sProtocol.$aOffer['SERVER_NAME'].CHTTP::urnEncode($ar_file['SRC'], 'utf-8');
//                                                else
//                                                    $strFile = $ar_file["SRC"];
//
//                                                $aPictures[] = $strFile;
//                                                $bContinue = true;
//                                            }
//                                        }
//                                    }
//                                }
//                                break;
//                            default:
//                                if ($aProp['CODE'] == 'CML2_ATTRIBUTES'){
//                                    $sPropName = $aProp['DESCRIPTION'];
//                                    $value = $aProp['VALUE'];
//                                }
//                                //$value = 'DEFAULT '.print_r($aProp, true);
//                        }
//                    }
//                    if($bContinue) continue;
//                    if (!empty($sVal = yandex_text2xml($value, true))){
//                        $sResult .= '<param name="'.yandex_text2xml($sPropName, true).'">'.$sVal.'</param>'.PHP_EOL;
//                        $aProps[] = $aProp['CODE'];
//                        if ($aProp['CODE'] === 'CML2_ARTICLE'){
//                            $sVendorCode = $aProp['VALUE'];
//                        }
//                    }
//
//                }
//            }
        }
        if (false && isset($aOffer['parent_prop_res']) && $aOffer['parent_prop_res'] instanceof CDBResult){
            $aPropsRes = mobium_extractProps($aOffer['parent_prop_res'], $sProtocol);
            foreach ($aPropsRes as $sCode => $aData){
                if (in_array($sCode, $aProps)){
                    continue;
                }
                if (!empty($sVal = yandex_text2xml($aData['value'], true))){
                    $sResult .= '<param name="'.yandex_text2xml($aData['name'], true).'">'.$sVal.'</param>'.PHP_EOL;
                    $aProps[] = $sCode;
                    if ($sCode === 'CML2_ARTICLE'){
                        $sVendorCode = $aData['value'];
                    }
                }
            }
//            while ($aProp = $aOffer['parent_prop_res']->Fetch()){
//                $bContinue = false;
//                if (in_array($aProp['CODE'], $aProps)){
//                    continue;
//                }
//                if (!empty($aProp['VALUE'])){
//                    $sPropName = $aProp['NAME'];
//                    //if (true){
//                    $value = '';
//                    if (isset($aProp['USER_TYPE']) && !empty($aProp['USER_TYPE']) && isset($aProp['USER_TYPE_SETTINGS']) && !empty($aProp['USER_TYPE_SETTINGS'])){
//                        continue;
//                        $aUserType = \CIBlockProperty::GetUserType($aProp['USER_TYPE']);
//                        if (isset($aUserType['GetPublicViewHTML'])){
//                            $value = call_user_func_array($aUserType['GetPublicViewHTML'],
//                                array(
//                                    $aProp,
//                                    array("VALUE" => $aProp['VALUE']),
//                                    array('MODE' => 'SIMPLE_TEXT'),
//                                )
//                            );
//                        }
//                    } else {
//                        switch ($aProp['PROPERTY_TYPE']){
//                            case Iblock\PropertyTable::TYPE_ELEMENT:
//                                if (!empty($aProp['VALUE']))
//                                {
//                                    $arCheckValue = array();
//                                    if (!is_array($aProp['VALUE']))
//                                    {
//                                        $aProp['VALUE'] = (int)$aProp['VALUE'];
//                                        if ($aProp['VALUE'] > 0)
//                                            $arCheckValue[] = $aProp['VALUE'];
//                                    }
//                                    else
//                                    {
//                                        foreach ($aProp['VALUE'] as $intValue)
//                                        {
//                                            $intValue = (int)$intValue;
//                                            if ($intValue > 0)
//                                                $arCheckValue[] = $intValue;
//                                        }
//                                        unset($intValue);
//                                    }
//                                    if (!empty($arCheckValue))
//                                    {
//                                        $filter = array(
//                                            '@ID' => $arCheckValue
//                                        );
//                                        if ($aProp['LINK_IBLOCK_ID'] > 0)
//                                            $filter['=IBLOCK_ID'] = $aProp['LINK_IBLOCK_ID'];
//
//                                        $iterator = Iblock\ElementTable::getList(array(
//                                            'select' => array('ID', 'NAME'),
//                                            'filter' => array($filter)
//                                        ));
//                                        while ($row = $iterator->fetch())
//                                        {
//                                            $value .= ($value ? ', ' : '').$row['NAME'];
//                                        }
//                                        unset($row, $iterator);
//                                    }
//                                }
//                                break;
//                            case Iblock\PropertyTable::TYPE_SECTION:
//                                $arCheckValue = array();
//                                if (!is_array($aProp['VALUE']))
//                                {
//                                    $aProp['VALUE'] = (int)$aProp['VALUE'];
//                                    if ($aProp['VALUE'] > 0)
//                                        $arCheckValue[] = $aProp['VALUE'];
//                                }
//                                else
//                                {
//                                    foreach ($aProp['VALUE'] as $intValue)
//                                    {
//                                        $intValue = (int)$intValue;
//                                        if ($intValue > 0)
//                                            $arCheckValue[] = $intValue;
//                                    }
//                                    unset($intValue);
//                                }
//                                if (!empty($arCheckValue))
//                                {
//                                    $filter = array(
//                                        '@ID' => $arCheckValue
//                                    );
//                                    if ($aProp['LINK_IBLOCK_ID'] > 0)
//                                        $filter['=IBLOCK_ID'] = $aProp['LINK_IBLOCK_ID'];
//
//                                    $iterator = Iblock\SectionTable::getList(array(
//                                        'select' => array('ID', 'NAME'),
//                                        'filter' => array($filter)
//                                    ));
//                                    while ($row = $iterator->fetch())
//                                    {
//                                        $value .= ($value ? ', ' : '').$row['NAME'];
//                                    }
//                                    unset($row, $iterator);
//                                }
//                                break;
//                            case Iblock\PropertyTable::TYPE_LIST:
//                                $bContinue = true;
//                                break;
//                                if (!empty($aProp['~VALUE']))
//                                {
//                                    if (is_array($aProp['~VALUE']))
//                                        $value .= implode(', ', $aProp['~VALUE']);
//                                    else
//                                        $value .= $aProp['~VALUE'];
//                                }
//                                break;
//                            case Iblock\PropertyTable::TYPE_FILE:
//                                if (!empty($aProp['VALUE']))
//                                {
//                                    if (is_array($aProp['VALUE']))
//                                    {
//                                        foreach ($aProp['VALUE'] as $intValue)
//                                        {
//                                            $intValue = (int)$intValue;
//                                            if ($intValue > 0)
//                                            {
//                                                if ($ar_file = CFile::GetFileArray($intValue)) {
//                                                    if (!CFile::IsImage($ar_file["FILE_NAME"], $ar_file["CONTENT_TYPE"])){
//                                                        continue;
//                                                    }
//                                                    if(substr($ar_file["SRC"], 0, 1) == "/")
//                                                        $strFile = $sProtocol.$aOffer['SERVER_NAME'].CHTTP::urnEncode($ar_file['SRC'], 'utf-8');
//                                                    else
//                                                        $strFile = $ar_file["SRC"];
//                                                    $aPictures[] = $strFile;
//                                                    $bContinue = true;
//                                                }
//                                            }
//                                        }
//                                        unset($intValue);
//                                    }
//                                    else
//                                    {
//                                        $aProp['VALUE'] = (int)$aProp['VALUE'];
//                                        if ($aProp['VALUE'] > 0)
//                                        {
//                                            if ($ar_file = CFile::GetFileArray($aProp['VALUE']))
//                                            {
//                                                if (!CFile::IsImage($ar_file["FILE_NAME"], $ar_file["CONTENT_TYPE"])){
//                                                    continue;
//                                                }
//                                                if(substr($ar_file["SRC"], 0, 1) == "/")
//                                                    $strFile = $sProtocol.$aOffer['SERVER_NAME'].CHTTP::urnEncode($ar_file['SRC'], 'utf-8');
//                                                else
//                                                    $strFile = $ar_file["SRC"];
//
//                                                $aPictures[] = $strFile;
//                                                $bContinue = true;
//                                            }
//                                        }
//                                    }
//                                }
//                                break;
//                            default:
//                                if ($aProp['CODE'] == 'CML2_ATTRIBUTES'){
//                                    $sPropName = $aProp['DESCRIPTION'];
//                                    $value = $aProp['VALUE'];
//                                }
//                            //$value = 'DEFAULT '.print_r($aProp, true);
//                        }
//                    }
//                    if($bContinue) continue;
//                    if (!empty($sVal = yandex_text2xml($value, true))){
//                        $sResult .= '<param name="'.yandex_text2xml($sPropName, true).'">'.$sVal.'</param>'.PHP_EOL;
//                        $aProps[] = $aProp['CODE'];
//                        if ($aProp['CODE'] === 'CML2_ARTICLE'){
//                            $sVendorCode = $aProp['VALUE'];
//                        }
//                    }
//
//                }
//            }
        }
        if (isset($aOffer['offer_props'])){
            foreach ($aOffer['offer_props'] as $sCode => $aData){
                if ($sCode === 'CML2_ARTICLE'){
                    $sVendorCode = $aData['value'];
                    continue;
                }
                if (!empty($sVal = yandex_text2xml($aData['value'], true, false))){
                    $sResult .= '<param code="'.$sCode.'" name="'.yandex_text2xml($aData['name'], true).'">'.$sVal.'</param>'.PHP_EOL;

                } else {
                    $sResult .= '<param code="'.$sCode.'" name="'.yandex_text2xml($aData['name'], true).'">'.$aData['value'].'</param>'.PHP_EOL;
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
        }/*
        foreach ($aProps as $sCode=>$aData){
            $sVal = yandex_text2xml(strip_tags($aData['value']), true);
            if (!empty($sVal)){
                if (in_array($sVal, ['Y', 'N'])){
                    $sVal = $sVal === 'Y';
                }
                $sResult .= '<param name="'.yandex_text2xml($aData['name'], true).'">'.$sVal.'</param>'.PHP_EOL;
            }

        }*/
        foreach ($aPictures as $sPic){
            $sResult .= '<picture>'.$sPic.'</picture>'.PHP_EOL;
        }
        if (null !== $sVendorCode){
            $sResult .= '<vendorCode>'.yandex_text2xml($sVendorCode).'</vendorCode>';
        }
        $sResult .= '</offer>';
        return $sResult;

    }
}

if (!function_exists("yandex_replace_special")) {
    function yandex_replace_special($arg){
        if (in_array($arg[0], array("&quot;", "&amp;", "&lt;", "&gt;")))
            return $arg[0];
        else
            return " ";
    }
}

if (!function_exists('mobium_extractProps')){
    /**
     * @param CDBResult $oPropsRes
     * @param string $sProtocol
     * @param bool $bAsArray
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    function mobium_extractProps($oPropsRes, $sProtocol, $bAsArray=false){
        $aResult = [];
        while ($aProp = $oPropsRes->Fetch()){
            /*$aData = CIBlockFormatProperties::GetDisplayValue(['NAME' => $aOffer['name']], $aProp, 'catalog_out');
            //var_dump(is_array($aData) ? $aData['DISPLAY_VALUE'] : $aData);
            $sValue = yandex_text2xml(strip_tags(is_array($aData) ? $aData['DISPLAY_VALUE'] : $aData), true);
            $aProps[$aProp['CODE']] = ['value'=>$sValue, 'name'=>$aProp['NAME']];
            if ($aProp['PROPERTY_TYPE'] === 'L'){
                $aProps[$aProp['CODE']]['value'] = $aProp['VALUE_ENUM'];
            }
            continue;*/

            $bContinue = false;
            if (!empty($aProp['VALUE'])){
                $sPropName = $aProp['NAME'];
                //if (true){
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
}

if (!function_exists('mobium_createOffer')){
    function mobium_createOffer($aProduct,
                                $sBaseCurrency,
                                $aAvailableCategories,
                                $sProtocol,
                                $iCatalogIBlockID,
                                $bLoadCategories=true,
                                $aParent = null
                                ){
        $aOffer = [];
        $aProduct['SERVER_NAME'] = mobium_getProductServerName($aProduct);
        $aOffer['SERVER_NAME'] = $aProduct['SERVER_NAME'];
        $aOffer['available'] = $aProduct['CATALOG_AVAILABLE'] == 'Y';
        list($aMinPrice, $sCurrency) = mobium_getOfferPrice($aProduct, $aProduct['LID'], $sBaseCurrency);
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
                    $sValue = yandex_text2xml(strip_tags($aData['DISPLAY_VALUE']), true);

                } else  {
                    $sValue = yandex_text2xml($aPropData['VALUE'], true);
                }
                if ($aPropData['PROPERTY_TYPE'] === 'L') {
                    $sValue = yandex_text2xml($aPropData['VALUE_ENUM']);
                }
                if (!empty($sValue)){
                    $aProps[$aPropData['CODE']] = ['name'=>$aPropData['NAME'], 'value'=>$sValue];
                }
            }
        }
        $aOffer['offer_props'] = array_merge(mobium_extractProps($aOffer['prop_res'], $sProtocol), $aProps);
        /*$aOffer['description'] = yandex_text2xml(TruncateText(
            ($aProduct["DETAIL_TEXT_TYPE"]=="html"?
                strip_tags(preg_replace_callback("'&[^;]*;'", "yandex_replace_special", $aProduct["~DETAIL_TEXT"])) : preg_replace_callback("'&[^;]*;'", "yandex_replace_special", $aProduct["~DETAIL_TEXT"])),
            3000), true);*/
        $aOffer['description'] = yandex_text2xml(preg_replace_callback("'&[^;]*;'", "yandex_replace_special", $aProduct["~DETAIL_TEXT"]));
        if (null !== $aParent){
            /*if (isset($aParent['prop_res']) && $aParent['prop_res'] instanceof CDBResult){
                $aOffer['parent_prop_res'] = $aParent['prop_res'];
            }*/
            /*if (isset($aParent['prop_extracted'])){
                $aOffer['parent_prop_extracted'] = $aParent['prop_extracted'];
            }*/
            if (isset($aParent['offer_props'])){
                //$aOffer['parent_offer_props'] = $aParent['offer_props'];
                $aOffer['offer_props'] = array_merge($aParent['offer_props'], $aOffer['offer_props']);
            }
            $aOffer['group_id'] = $aParent['id'];
            $aOffer['description'] = $aParent['description'];
            $aOffer['name'] = $aParent['name'];
            $aOffer['categories'] = $aParent['categories'];
            unset($aOffer['offer_props']['CML2_ATTRIBUTES']);
            //получаем массив значений множественного свойства CML2_ATTRIBUTES в которое стандартно выгружаются характеристики ТП из 1С
            $resCml2Attributes = CIBlockElement::GetProperty(OFFERS_IBLOCK_ID, $aOffer['id'], array('sort' => 'asc'), array('CODE' => 'CML2_ATTRIBUTES'));
            $aModifications = mobium_extractProps($resCml2Attributes, $sProtocol, true);
            foreach ($aModifications as $iIndex => $aItem) {
                $aOffer['offer_props']['modification_'.$iIndex] = $aItem;
            }
        }
        return $aOffer;
    }
}

global $APPLICATION;
set_time_limit(0);
define('PRODUCTS_IBLOCK_ID', 17);
define('OFFERS_IBLOCK_ID', 20);
ini_set('memory_limit', '4096M');

global $USER;
$bTmpUserCreated = false;
if (!CCatalog::IsUserExists()) {
    $bTmpUserCreated = true;
    if (isset($USER))
        $USER_TMP = $USER;
    $USER = new CUser();
}
CCatalogDiscountSave::Disable();
CCatalogDiscountCoupon::ClearCoupon();
if ($USER->IsAuthorized()) {
    CCatalogDiscountCoupon::ClearCouponsByManage($USER->GetID());
}
$sProtocol = (CMain::IsHTTPS() ? 'https://' : 'http://');
$aSelectForProducts = array(
    "ID", "LID", "IBLOCK_ID", "IBLOCK_SECTION_ID", "ACTIVE", "NAME",
    "PREVIEW_PICTURE", "PREVIEW_TEXT", "PREVIEW_TEXT_TYPE",
    "DETAIL_PICTURE", "LANG_DIR", "DETAIL_PAGE_URL",
    "CATALOG_AVAILABLE", 'DETAIL_TEXT', 'DISPLAY_PROPERTIES',
    'QUANTITY', 'QUANTITY_RESERVED', 'QUANTITY_TRACE', 'CAN_BUY_ZERO'
);
$aProductProps = array(
     "BRAND",
     "VOZDUHOPRONICH",
     "ALTMETR",
     "CML2_ARTICLE",
     "BAROMETR", "BUDILNIK", "TKAN_UP",
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
);
$BASE_CURRENCY = Currency\CurrencyManager::getBaseCurrency();
$RUR = 'RUB';
$currencyIterator = Currency\CurrencyTable::getList(array(
    'select' => array('CURRENCY'),
    'filter' => array('=CURRENCY' => 'RUR')
));
if ($currency = $currencyIterator->fetch()){
    $RUR = 'RUR';
}

$bError = true;
$strExportPath = COption::GetOptionString("catalog", "export_default_path", CATALOG_DEFAULT_EXPORT_PATH);
$strYandexPath = Rel2Abs('/',str_replace('//','/',$strExportPath."/yandex.php"));
if (!empty($strYandexPath))
{
    CheckDirPath($_SERVER["DOCUMENT_ROOT"].$strExportPath);

    if (!($fp = @fopen($_SERVER["DOCUMENT_ROOT"].$strYandexPath, 'wb')))
    {
        exit();
        $sAll = '';
        $sAll .= mobium_renderShopInfo($sProtocol);
        $sAll .= mobium_renderCurrency($RUR);
        $sAll .= mobium_renderCategories($aAvailableSections);
        $sAll .= '<offers>'.PHP_EOL;
        foreach ($aOffers as $offer){
            $sAll .= mobium_renderOffer($offer, $sProtocol);
        }
        $sAll .= '</offers>'.PHP_EOL.'</shop>'.PHP_EOL.'</yml_catalog>';
        fwrite($fp, $sAll);
        /*fwrite($fp, mobium_renderShopInfo($sProtocol));
        fwrite($fp, mobium_renderCurrency($RUR));
        fwrite($fp, mobium_renderCategories($aAvailableSections));
        fwrite($fp, '<offers>'.PHP_EOL);
        foreach ($aOffers as $offer){
            fwrite($fp, mobium_renderOffer($offer));
        }
        fwrite($fp, '</offers>'.PHP_EOL.'</shop>'.PHP_EOL.'</yml_catalog>');
        */
        fclose($fp);
        $bError = false;
    }
    fwrite($fp, mobium_renderShopInfo($sProtocol));
    fwrite($fp, mobium_renderCurrency($sProtocol));

}

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
                $aTemp['PICTURE'] = substr($aPicInfo["SRC"], 0, 1) == "/" ? $sProtocol.COption::GetOptionString("main", "server_name", "").CHTTP::urnEncode($aPicInfo["SRC"], 'utf-8') : $aPicInfo['SRC'];
            }
        }
        $aAvailableSections[] = $aTemp;

    }
    fwrite($fp, mobium_renderCategories($aAvailableSections));
    // Загрузка товаров
    $aFilters = ['IBLOCK_ID'=>$aCatalog['IBLOCK_ID'], 'ACTIVE'=>'Y', 'ACTIVE_DATE'=>'Y'];
    $oProductsResult = CIBlockElement::GetList(array(), $aFilters, false, false, $aSelectForProducts);

    $oPropsResult = Iblock\PropertyTable::getList([
        'select'=>['ID','CODE'],
        'filter'=>['=IBLOCK_ID'=>$aCatalog['IBLOCK_ID'], '@CODE'=>$aProductProps],
        'order'=>['SORT'=>'ASC', 'NAME'=>'ASC']
    ]);
    $aPropsDB = $oPropsResult->fetchAll();
    //var_dump($aPropsDB );

    $iTotalSum = 0; $bIsExists = false; $iCnt = 0;
    $aOffers = [];
    $iTotalCount = 0;
    fwrite($fp, '<offers>'.PHP_EOL);
    //while ($oProduct = $oProductsResult->GetNextElement()) {
    $aColumns = array_column($aPropsDB, 'ID');
    while ($aProduct = $oProductsResult->GetNext()) {
        $aOffer = [];
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
        //var_dump($aProps);
        //var_dump(array_column($aProps, 'VALUE'));
        //echo('Get props '.(microtime(true) - $fStart)."<br>");
        $aProduct['PROPS'] = $aProps;
        $iTotalCount++;
        $iCnt++;
        $fTime = microtime(true);
        $aOffer = mobium_createOffer($aProduct, $BASE_CURRENCY, $aAvailableSections, $sProtocol, $aCatalog['IBLOCK_ID']);
        //echo('Create offer '.(microtime(true) - $fTime)."<br>");
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
                $aFilters['IBLOCK_ID']=OFFERS_IBLOCK_ID;
                $aTempProduct = CIBlockElement::GetList(array(), $aFilters, false, false, $aSelectForProducts)->GetNext();
                if (false === ($aTempOffer = mobium_createOffer($aTempProduct, $BASE_CURRENCY, $aAvailableSections, $sProtocol, OFFERS_IBLOCK_ID, false, $aOffer))){
                    continue;
                }
                /*if ($aTempOffer['group_id'] == 14157){
                    var_dump($aOffer, $aTempOffer);
                }*/
                /*$aT = $aTempOffer;
                $aO = $aOffer;
                unset($aO['prop_res']);
                unset($aO['props']);
                unset($aT['prop_res']);
                unset($aT['parent_prop_res']);
                var_dump($aO, $aT);
                exit();*/
                /*$aTempOffer['parent_prop_res'] = $aOffer['prop_res'];
                if (isset($aOffer['props'])){
                    $aTempOffer['parent_props'] = $aOffer['props'];
                }
                $aTempOffer['group_id'] = $aProduct['ID'];
                $aTempOffer['description'] = $aOffer['description'];
                $aTempOffer['name'] = $aOffer['name'];
                $aTempOffer['categories'] = $aOffer['categories'];
                */

                //$aOffers[] = $aTempOffer;
                fwrite($fp, mobium_renderOffer($aTempOffer, $sProtocol));
            }

        } else {
            //$aOffers[] = $aOffer;
            fwrite($fp, mobium_renderOffer($aOffer, $sProtocol));
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
fwrite($fp, '</offers>'.PHP_EOL.'</shop>'.PHP_EOL.'</yml_catalog>');
fclose($fp);



/*

$bError = true;
$strExportPath = COption::GetOptionString("catalog", "export_default_path", CATALOG_DEFAULT_EXPORT_PATH);
$strYandexPath = Rel2Abs('/',str_replace('//','/',$strExportPath."/yandex.php"));
if (!empty($strYandexPath))
{
    CheckDirPath($_SERVER["DOCUMENT_ROOT"].$strExportPath);

    if ($fp = @fopen($_SERVER["DOCUMENT_ROOT"].$strYandexPath, 'wb'))
    {
        $sAll = '';
        $sAll .= mobium_renderShopInfo($sProtocol);
        $sAll .= mobium_renderCurrency($RUR);
        $sAll .= mobium_renderCategories($aAvailableSections);
        $sAll .= '<offers>'.PHP_EOL;
        foreach ($aOffers as $offer){
            $sAll .= mobium_renderOffer($offer, $sProtocol);
        }
        $sAll .= '</offers>'.PHP_EOL.'</shop>'.PHP_EOL.'</yml_catalog>';
        fwrite($fp, $sAll);
        fclose($fp);
        $bError = false;
    }
}
*/




if ($bError) {
    CEventLog::Log('WARNING','CAT_YAND_AGENT','catalog','YandexAgent',$strYandexPath);
}

CCatalogDiscountSave::Enable();
if ($bTmpUserCreated) {
    if (isset($USER_TMP)) {
        $USER = $USER_TMP;
        unset($USER_TMP);
    }
}