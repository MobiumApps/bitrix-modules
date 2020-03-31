<?php
namespace Mobium\Api;


use BethowenSOAP\Client\DTO\ActivateDiscountCard;
use BethowenSOAP\Client\DTO\DC;
use BethowenSOAP\Client\Simple\DateType;
use BethowenSOAP\Client\Simple\PersonalStringType;
use BethowenSOAP\Client\Simple\PhoneType;
use BethowenSOAP\Cms\Helper;
use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Config\Option;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Sale\Location\LocationTable;
use CSite;
use Exception;
use Mobium\Api\DeliveryType\DeliveryTypeTable;
use phpDocumentor\Reflection\Types\Float_;

/**
 * Class ApiHelper
 * @package Mobium\Api
 */
class ApiHelper
{


    /**
	 *
     * @return bool
     */
    public static function authorizeByHeader()
    {
        if (!empty($sToken = static::getTokenFromHeaders())){
            try {
                return static::authorizeToken($sToken);
            } catch (\Exception $e) {
                return false;
            }
        }
        return false;
    }

    public static function getTokenFromHeaders(){
        $aHeaders = ['REMOTE_USER', 'HTTP_AUTHORIZATION', 'HTTP_AUTHORIZATIONTOKEN'];
        foreach ($aHeaders as $sHeader){
            $sToken = $_SERVER[$sHeader] ?? '';
            if (preg_match('/^[a-f0-9]{32}$/i', $sToken)){
                return $sToken;
            }
        }
        return false;
    }

    public static function buildOrder($oBasket)
    {

    }

    /**
     * @param string $sToken
     * @param bool $bCheckExpire
     * @return bool
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function authorizeToken($sToken, $bCheckExpire=false)
    {
        if (!empty($sToken)) {
            $oResult = \Mobium\Api\AccessToken\AccessTokenTable::getList([
                'filter' => [
                    'BODY' => $sToken,
                    'TYPE' => 'auth'
                ]
            ]);
            $aTokenResult = $oResult->fetchAll();
            if (count($aTokenResult) === 1) {
                $aTokenResult = $aTokenResult [0];
                if ($bCheckExpire && ((int) ($aTokenResult['CREATED_AT'] + (int) $aTokenResult['LIFETIME']) < time())){
                    return false;
                }
                /**@var \CUser $USER */
                global $USER;
                $USER->Authorize($aTokenResult ['USER_ID']);
                return true;
            }
        }
        return false;
    }

    /**
     * @param $sId
     * @return mixed|null
     */
    public static function getProfileFieldValue($sId){
        /**@var \CUser $USER */
        global $USER;
        switch ($sId){
            case 'name':
                return $USER->GetFirstName();
            case 'last_name':
                return $USER->GetLastName();
            case 'birthday':
                $oUserResult = \CUser::GetByID($USER->GetID());
                if ($aUser = $oUserResult->Fetch()){
                    return $aUser['PERSONAL_BIRTHDAY'];
                }
                return $USER->GetParam('PERSONAL_BIRTHDAY');
            case 'sex':
                $oUserResult = \CUser::GetByID($USER->GetID());
                if ($aUser = $oUserResult->Fetch()){
                    $sGender = $aUser['PERSONAL_GENDER'];
                } else {
                    $sGender = $USER->GetParam('PERSONAL_GENDER');
                }
                switch ($sGender){
                    case 'M':
                        return 'Мужской';
                    case 'F':
                        return 'Женский';
                    default:
                        return '';
                }
            case 'email':
                return $USER->GetEmail();
            case 'phone':
                $oUserResult = \CUser::GetByID($USER->GetID());
                if ($aUser = $oUserResult->Fetch()){
                    return $aUser['PERSONAL_PHONE'];
                }
                return $USER->GetParam('PERSONAL_PHONE');
            case 'photo':
                $oUserResult = \CUser::GetByID($USER->GetID());
                if ($aUser = $oUserResult->Fetch()){
                    return $aUser['PERSONAL_PHOTO'];
                }
                return $USER->GetParam('PERSONAL_PHOTO');
            case 'login':
                $sLogin = trim($USER->GetEmail());
                if (!empty($sLogin)){
                    return $sLogin;
                }
                $oUserResult = \CUser::GetByID($USER->GetID());
                if ($aUser = $oUserResult->Fetch()){
                    return $aUser['PERSONAL_PHONE'];
                }
                return $USER->GetParam('PERSONAL_PHONE');
            case 'bonuses':
                return static::getCurrentUserBonuses();
            case 'barcode':
                return '';
            case 'card_code':
                $aCardData = static::getCurrentUserCardData();
                if ($aCardData){
                    return 'Номер карты: '.$aCardData['UF_CODE'];
                }
                return null;
            case 'card_number':
                $aCardData = static::getCurrentUserCardData();
                if ($aCardData){
                    return $aCardData['UF_CODE'];
                }
                return null;
            default:
                return null;

        }

    }

    /**
     * @param $sId
     * @param int $mValue
     * @return mixed|null
     */
    public static function setProfileFieldValue($sId, $mValue){
        /**@var \CUser $USER */
        global $USER;
        switch ($sId){
            case 'name':
                return $USER->Update($USER->GetID(), [
                    'NAME'=>$mValue
                ]);
            case 'email':
                return $USER->Update($USER->GetID(), [
                    'EMAIL'=>$mValue
                ]);
            case 'last_name':
                return $USER->Update($USER->GetID(), [
                    'LAST_NAME'=>$mValue
                ]);
            case 'phone':
                $USER->SetParam('PERSONAL_PHONE', $mValue);
                return $USER->Update($USER->GetID(), [
                    'PERSONAL_PHONE' => $mValue
                ]);
            case 'login':
                return false;
            case 'bonuses':
                return static::setCurrentUserBonuses($mValue);
            case 'barcode':
                return null;
            case 'card_code':
                return null;
            default:
                return null;

        }

    }

    public static function getRegisterSessionData(int $iAppId)
    {
        $sSql = "SELECT * FROM `mobium_register_session` WHERE `APP_ID`={$iAppId}";
        /**@var \CDatabase $DB */
        global $DB;
        if (false === ($oRes = $DB->Query($sSql)) || (false === ($aData = $oRes->Fetch()))){
            return [
                'CREATED_AT'=>time(),
                'APP_ID'=>$iAppId,
                'DATA'=>[]
            ];
        }
        $aData['DATA'] = unserialize($aData['DATA']);
        return $aData;
    }

    public static function setRegisterSessionData(int $iAppId, array $aData)
    {
        $sSerializedData = serialize($aData['DATA']);
        $iCreatedAt = (int) $aData['CREATED_AT'];
        if (isset($aData['ID'])){
            $sSql = "UPDATE `mobium_register_session` SET `DATA`='{$sSerializedData}', `CREATED_AT`={$iCreatedAt} WHERE `ID`={$aData['ID']}";
        } else {
            $sSql = "INSERT INTO `mobium_register_session`(`APP_ID`, `CREATED_AT`, `DATA`) VALUES ({$iAppId}, {$iCreatedAt}, '{$sSerializedData}');";
        }
        /**@var \CDatabase $DB */
        global $DB;
        if (false === $DB->Query($sSql)){
            var_dump('Error'.$DB->GetErrorMessage().$DB->GetErrorSQL());
        }
        return true;
    }

    public static function deleteRegisterSessionData(int $iId)
    {
        $sSql = "DELETE FROM `mobium_register_session` WHERE `ID`={$iId}";
        global $DB;
        return false !== $DB->Query($sSql);

    }

    /**
     * @param int $iValue
     * @return bool
     */
    public static function setCurrentUserBonuses($iValue){
        /**@var \CUser $USER */
        global $USER;
        return $USER->Update($USER->GetID(), [
            'UF_LOGICTIM_BONUS' => $iValue
        ]);
    }

    /**
     * @return int
     */
    public static function getCurrentUserBonuses(){
        /**@var \CUser $USER */
        global $USER;
        $by = 'timestamp_x'; $o = 'desc';
        $oResult = \CUser::GetList($by, $o, ['ID'=>$USER->GetID()], ['SELECT'=>['UF_LOGICTIM_BONUS']]);
        $aUserData = $oResult->Fetch();
        return (int) $aUserData['UF_LOGICTIM_BONUS'] ?? 0;
    }

    public static function getBonusCard()
    {
        /**@var \CUser $USER */
        global $USER;
        $by = 'timestamp_x'; $o = 'desc';
        $oResult = \CUser::GetList($by, $o, ['ID'=>$USER->GetID()], ['SELECT'=>['UF_LOGICTIM_BONUS']]);
        $aUserData = $oResult->Fetch();
        return (int) $aUserData['UF_LOYMAX_BONUS'] ?? 0;
    }

    public static function getCurrentUserPersonalDiscount()
    {
        /**@var \CUser $USER */
        global $USER;
        $by = 'timestamp_x'; $o = 'desc';
        $oResult = \CUser::GetList($by, $o, ['ID'=>$USER->GetID()], ['SELECT'=>['UF_DISCOUNT']]);
        $aUserData = $oResult->Fetch();
        return (int) $aUserData['UF_DISCOUNT'] ?? 0;
    }

    /**
     * @return array|bool
     */
    public static function getCurrentUserCardData()
    {
        $aResult = [];

        if (false === ($sCode = \Bethowen\Helpers\Discountcard::getUserDiscountCardId(true)) || null === $sCode){
            return false;
        }

        $aResult['UF_CODE'] = $sCode;
        self::authorizeUserCard($sCode);
        try{
            $fBalance = self::getCardBalance();
            $aResult['BONUS'] = true;
            $aResult['BONUS_POINTS'] = $fBalance;


        }catch (\Exception $e){
            $aResult['BONUS'] = false;
        }

        //return \Bethowen\Helpers\Discountcard::getUserDiscountCard();
        return $aResult;
    }

    public static function authorizeUserCard($sCardCode)
    {
        \Bethowen\Services\Loymax::getMerchantToken();
        \Bethowen\Services\Loymax::getCode($sCardCode);
        \Bethowen\Services\Loymax::getToken();
    }

    public static function getCardBalance(): float
    {

        $request = \Bethowen\Services\Loymax::prepareBalance();
        $response = \Bethowen\Services\Loymax::balanceLoymax($request);
        $response = json_decode($response, true);
        if (isset($response['data']) && count($response) > 0 && isset($response['data'][0]['amount'])){
            return (float) $response['data'][0]['amount'];
        }
        throw new \RuntimeException('Invalid response');
    }

    /**
     * @return bool|array
     */
    public static function getDeliveryTypes(){
        $sApiUrl = 'https://core.pre.mobiumapps.com';
        $sApiUrl = Option::get('mobium.api', 'server_url', 'https://core.mobium.pro');
        $sToken = Option::get('mobium.api', "api_key", '');
        if (empty($sToken)){
            return false;
        }
        //$sToken = '5d34fa36aefd23bea9e144b02256a54c06759ac5';
        $oCh = curl_init();
        curl_setopt($oCh, CURLOPT_URL, $sApiUrl.'/api/deliveries');
        curl_setopt($oCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($oCh, CURLOPT_HTTPHEADER, [
            'Authorization: '.$sToken
        ]);
        $sContent = curl_exec($oCh);
        curl_close($oCh);
        $aData = json_decode($sContent, true);
        if (JSON_ERROR_NONE === json_last_error()){
            return $aData;
        }
        return false;
    }

    /**
     * @return bool|array
     */
    public static function getDeliveryAreas($sDeliveryTypeId){
        $sApiUrl = Option::get('mobium.api', 'server_url', 'https://core.mobium.pro');
        $sToken = Option::get('mobium.api', "api_key", '');
        if (empty($sToken)){
            return false;
        }
        $oCh = curl_init();
        curl_setopt($oCh, CURLOPT_URL, $sApiUrl.'/api/delivery_areas?limit=1000&deliveryType='.$sDeliveryTypeId);
        curl_setopt($oCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($oCh, CURLOPT_HTTPHEADER, [
            'Authorization: '.$sToken
        ]);
        $sContent = curl_exec($oCh);
        curl_close($oCh);
        $aData = json_decode($sContent, true);
        if (JSON_ERROR_NONE === json_last_error()){
            return $aData;
        }
        return false;
    }

    public static function getOfferProps()
    {
        $iIblockId = Option::get('mobium.api', 'offers_iblock', '27');
        $oRes = \Bitrix\Iblock\PropertyTable::getList([
            'select'=>['ID', 'CODE', 'NAME'],
            'filter'=>[
                '=IBLOCK_ID'=>$iIblockId,
            ]
        ]);
        $aResult = [];
        while ($aData = $oRes->fetch()) {
            $aResult[$aData['ID']] = $aData['NAME'].' ('.$aData['CODE'].')';
        }
        return $aResult;
    }

    public static function getProductsProps()
    {
        $iIblockId = Option::get('mobium.api', 'products_iblock', '26');
        $oRes = \Bitrix\Iblock\PropertyTable::getList([
            'select'=>['ID', 'CODE', 'NAME'],
            'filter'=>[
                '=IBLOCK_ID'=>$iIblockId,
            ]
        ]);
        $aResult = [];
        while ($aData = $oRes->fetch()) {
            $aResult[$aData['ID']] = $aData['NAME'].' ('.$aData['CODE'].')';
        }
        return $aResult;
    }

    public static function getBitrixDeliveries()
    {
        $aData = \Bitrix\Sale\Delivery\Services\Manager::getActiveList();
        $aResult = [];
        foreach ($aData as $aItem){
            $aResult[$aItem['ID']] = $aItem['NAME'];
        }
        return $aResult;
    }

    public static function getMobiumDeliveries()
    {
        $aResult = [];
        if (false !== ($aData = static::getDeliveryTypes())){
            foreach ($aData as $aItem){
                $aResult[$aItem['id']] = $aItem['title'];
            }
        }
        return $aResult;
    }

    public static function getMobiumDeliveryAreas()
    {
        $aResult = [];
        if (false !== ($aData = static::getDeliveryTypes())){
            foreach ($aData as $aItem){
                if (false !== ($aDeliveryAreas = static::getDeliveryAreas($aItem['id']))){
                    foreach ($aDeliveryAreas as $aDeliveryArea){
                        $aResult[$aDeliveryArea['id']] = $aDeliveryArea['title'];
                    }
                }
            }
        }
        return $aResult;
    }

    public static function getDeliveryAssociations()
    {
        $oResult = DeliveryTypeTable::getList(['select'=>['*'], 'filter'=>['ACTIVE'=>'Y']]);
        $aResult = [];
        while ($aAssociation = $oResult->fetch()){
            $sKey = $aAssociation['DELIVERY_SERVICE_ID_MOBIUM'].($aAssociation['DELIVERY_SERVICE_AREA_ID'] ? ':'.$aAssociation['DELIVERY_SERVICE_AREA_ID'] : '');
            $aResult[$sKey] = $aAssociation['DELIVERY_SERVICE_ID_BITRIX'];
        }
        return $aResult;
    }

    public static function attachCardToCurrentUser($sCardNumber, array $aParams=[])
    {
        /**@var \CUser $USER */
        global $USER;
        return static::attachCardToUser($sCardNumber, $USER, $aParams);

    }

    /**
     * @param string $sCardNumber
     * @param \CUser $oUser
     * @param array $aParams
     * @return bool
     */
    public static function attachCardToUser($sCardNumber, $oUser, array $aParams=[])
    {
        if (!is_numeric($sCardNumber)){
            return false;
        }
        $sTestPhone = preg_replace('/[^0-9]/', '', (string) Helper::normalizePhone($aParams['phone']));
        /**@var \CDatabase $DB */
        global $DB;
        $sQuery = 'SELECT * FROM `avbel_discount_card` WHERE `UF_CODE`='.$sCardNumber.' OR `UF_PHONE`=\''.$sTestPhone.'\'LIMIT 1';
        if (false === ($oRes = $DB->Query($sQuery))){
            //var_dump('error query');
            return false;
        }
        $aCard = $oRes->Fetch();
        if ($aCard && !empty($aCard['UF_OWNER'])){
            //var_dump('card exists', $sCardNumber);
            return false;
        }



        $config = \BethowenSOAP\Loader::getConfig();
        //var_dump($config);
        $logger = \BethowenSOAP\Loader::getLogger( 'client', $config);
        $transformer = new \BethowenSOAP\Cms\EnumTransformer();
        $oSoap       = new \BethowenSOAP\Client\ExchangeWeb( $config['client'], $config['client']['wsdl'] );
        $sLastName = trim($oUser->GetLastName());
        if (empty($sLastName)){
            $sLastName = 'Unknown';
        }
        $sPhone = Helper::normalizePhone($aParams['phone']);
        $oDiscountCard = new DC(
            new PersonalStringType($sCardNumber),
            false,
            new PersonalStringType(urldecode($oUser->GetFirstName())),
            new PersonalStringType(urldecode($sLastName)),
            new PersonalStringType(urldecode($oUser->GetSecondName())),
            new PersonalStringType($oUser->GetEmail()),
            new DateType(Helper::normalizeDate(date('d.m.Y'))),
            new PhoneType($sPhone),
            new DateType(Helper::normalizeDate(date('d.m.Y')))
        );
        $str = urldecode($aParams["phone"]);
        $phone = preg_replace("/[^\d]+/", "", $str);
        $oDiscountCard->setORDERSITEID(false);
        $oDiscountCard->setGETNEWS(true);
        $oDiscountCard->setPERSONALPHONE('+'.$phone);
        $oActivationRequest = new ActivateDiscountCard($oDiscountCard);
        $oResult = $oSoap->ActivateDiscountCard($oActivationRequest);
        if (strpos($oResult->getReturn(), 'OK') === 0){
            $sDateUpdate = date($DB->DateFormatToPHP(CSite::GetDateFormat("FULL")));
            $sDateOfIssue = date('d.m.Y');
            if ($aCard && $aCard['UF_OWNER']){
                $sQuery = "UPDATE `avbel_discount_card` SET `UF_OWNER`='{$oUser->GetID()}', `UF_EMAIL`='{$oUser->GetEmail()}',
                           `UF_DATE_OF_ISSUE`='{$sDateOfIssue}', `UF_DATE_UPDATE`='{$sDateUpdate}' WHERE `ID`={$aCard['ID']}";
                if (false === ($oInsertRest = $DB->Query($sQuery))){
                    return false;
                }
                \Bethowen\Helpers\Discountcard::saveDiscountCardUser($sCardNumber, (float) $aCard['UF_DISCOUNT']);
            } else {
                $iDiscount = 0;
                $fCumulativeSum = 0;
                $sQuery = "INSERT INTO `avbel_discount_card` (
              `UF_CODE`, `UF_EMAIL`, `UF_OWNER`, `UF_DATE_OF_ISSUE`,
              `UF_DISCOUNT`, `UF_CUMULATIVE_SUM`,
              `UF_DATE_UPDATE`
            ) VALUES ('{$sCardNumber}', '{$oUser->GetEmail()}', '{$oUser->GetID()}', '{$sDateOfIssue}',
                  '{$iDiscount}', '{$fCumulativeSum}', '{$sDateUpdate}')";
                if (false === ($oInsertRest = $DB->Query($sQuery))){
                    return false;
                }
                \Bethowen\Helpers\Discountcard::saveDiscountCardUser($sCardNumber, 0);
            }

        }

        $by = 'id'; $order='desc';
        $qUsers = \CUser::GetList($by, $order, ['ID'=>$oUser->GetID()], [
            'SELECT'=>['UF_DISCOUNTCARD', 'UF_DISCOUNT'],
            'FIELDS'=>['ID']
        ]);
        while($rUsers = $qUsers->Fetch())
        {
            \Bethowen\Helpers\User::UserGroupByDiscount($rUsers);
        }
        return true;
    }

    /**
     * @param \CUser $oUser
     * @return bool|int
     */
    public static function createUserAppProfile($oUser)
    {
        $aUserData = \CUser::GetByID($oUser->GetID())->Fetch();
        $arProfileFields = array(
            "NAME" => "Профиль покупателя ".$oUser->GetFirstName().'',
            "USER_ID" => $oUser->GetID(),
            "PERSON_TYPE_ID" => 1
        );
        $PROFILE_ID = \CSaleOrderUserProps::Add($arProfileFields);
        if (!$PROFILE_ID){
            return false;
        }
        $PROPS=[
            [
                "USER_PROPS_ID" => $PROFILE_ID,
                "ORDER_PROPS_ID" => 3,
                "NAME" => "Телефон",
                "VALUE" => $aUserData['PERSONAL_PHONE']
            ],
            [
                "USER_PROPS_ID" => $PROFILE_ID,
                "ORDER_PROPS_ID" => 25,
                "NAME" => "Ф.И.О.",
                "VALUE" => $oUser->GetFullName()
            ],
            [
                "USER_PROPS_ID" => $PROFILE_ID,
                "ORDER_PROPS_ID" => 31,
                "NAME" => '№ дисконтной карты',
                "VALUE" => $aUserData['UF_DISCOUNTCARD']
            ]
        ];
        if (filter_var($oUser->GetEmail(), FILTER_VALIDATE_EMAIL)){
            $PROPS[] = [
                "USER_PROPS_ID" => $PROFILE_ID,
                "ORDER_PROPS_ID" => 2,
                "NAME" => "E-Mail",
                "VALUE" => $oUser->GetEmail()
            ];
        }
        foreach ($PROPS as $prop){
            $iPropId = \CSaleOrderUserPropsValue::Add($prop);
        }
        $oUser->Update($oUser->GetId(), [
            'UF_APP_PROFILE'=>$PROFILE_ID
        ]);
        return $PROFILE_ID;
    }

    /**
     * @param \CUser $oUser
     * @param array $aData
     */
    public static function updateProfileForOrder($oUser, $aData)
    {
        $aUserData = \CUser::GetByID($oUser->GetID())->Fetch();
        if (!isset($aUserData['UF_APP_PROFILE']) || empty($aUserData['UF_APP_PROFILE'])){
            $iProfileId = self::createUserAppProfile($oUser);
        } else {
            $iProfileId = $aUserData['UF_APP_PROFILE'];
        }
        foreach ($aData as $sFieldId=>$sValue){
            $sName = null; $iPropId = null;
            switch ($sFieldId){
                case 'city':
                    $sName='Город';
                    $iPropId = 5;
                    break;
                case 'building':
                    $sName = 'Дом';
                    $iPropId = 27;
                    break;
                case 'street':
                    $sName = 'Улица';
                    $iPropId = 26;
                    break;
                case 'name':
                    $sName = 'Имя';
                    $iPropId = 25;
                    break;
                case 'phone':
                    $sName = 'Телефон';
                    $iPropId = 3;
                    break;
                case 'location':
                    $sName = 'Местоположение';
                    $iPropId = 6;
                    break;
                case 'apartments':
                    $sName = 'Квартира';
                    $iPropId = 28;
                    break;
                case 'email':
                    $sName = 'E-Mail';
                    $iPropId = 2;
                    break;
            }
            if (null === $sName){
                continue;
            }
            $oRes = \CSaleOrderUserPropsValue::GetList([], ['USER_PROPS_ID'=>$iProfileId, 'NAME'=>$sName]);
            if (!($aFieldData = $oRes->Fetch())){
                \CSaleOrderUserPropsValue::Add([
                    'USER_PROPS_ID'=>$iProfileId,
                    'ORDER_PROPS_ID'=>$iPropId,
                    'NAME'=>$sName,
                    'VALUE'=>$sValue
                ]);
            } else {
                \CSaleOrderUserPropsValue::Update($aFieldData['ID'], [
                    'VALUE'=>$sValue
                ]);
            }
        }
    }

    public static function getLocationsForDelivery($iDeliveryId, $sRegionName=null, $sLanguage=LANGUAGE_ID)
    {
        $sKey = md5($iDeliveryId.$sRegionName.$sLanguage);
        $oCache = \Bitrix\Main\Application::getInstance()->getManagedCache();
        $iCacheTTL = 3600*10000;
        if ($oCache->read($iCacheTTL, $sKey)){
            $aResult = $oCache->get($sKey);
            return $aResult;
        }
        $aResult = [];

        $fStartTime = microtime(true);
        $deliveryLocationIterator = \Bitrix\Sale\Delivery\DeliveryLocationTable::getList([
            'select' => [
                '*',
                'LOCATION_ID' => 'LOCATION.ID',
                'LOCATION_GROUP_ID' => 'GROUP.ID',
                'LOCATION_GROUP_LOCATION_ID' => 'LOCATION_GROUP.LOCATION_ID',
            ],
            'filter' => [
                '=DELIVERY_ID' => $iDeliveryId,
            ],
            'runtime' => [
                'LOCATION_GROUP' => [
                    'data_type' => '\Bitrix\Sale\Location\GroupLocationTable',
                    'reference' => [
                        '=this.LOCATION_GROUP_ID' => 'ref.LOCATION_GROUP_ID',
                    ],
                    'join_type' => 'left'
                ],
            ]
        ]);
        $fStartFirstProccessTime = microtime(true);
        while ($deliveryLocation = $deliveryLocationIterator->fetch()) {

            $locationId = null;

            if (
                isset($deliveryLocation['LOCATION_GROUP_ID'])
                && $deliveryLocation['LOCATION_GROUP_ID'] > 0
            ) {

                if ($deliveryLocation['LOCATION_GROUP_LOCATION_ID'] <= 0) {
                    continue;
                }

                $locationId = $deliveryLocation['LOCATION_GROUP_LOCATION_ID'];
            } else if (
                isset($deliveryLocation['LOCATION_ID'])
                && $deliveryLocation['LOCATION_ID'] > 0
            ) {
                $locationId = $deliveryLocation['LOCATION_ID'];
            }

            if (!isset($locationId)) {
                continue;
            }


            $aResult[] = $locationId;

            $childrenIterator = \Bitrix\Sale\Location\LocationTable::getChildren($locationId, [
                'select' => [
                    'ID',
                    'CODE',
                    'NAME.NAME',
                    'PARENT.NAME.NAME',
                ],
                'filter' => [
                    '=NAME.LANGUAGE_ID' => $sLanguage,
                    '=PARENT.NAME.LANGUAGE_ID' => $sLanguage

                ]
            ]);

            foreach ($childrenIterator->fetchAll() as $location) {
                $aResult[] = $location['ID'];
            }
        }
        $aResult = array_unique($aResult);
        $fEndFirstProcessTime = microtime(true) - $fStartFirstProccessTime;
        $fStartSecond = microtime(true);
        if (null !== $sRegionName){
            $oRes = LocationTable::getList([
                'select'=>['*', 'NAME_RU'=>'NAME.NAME'],
                'filter'=>['ID'=>$aResult, '=NAME.LANGUAGE_ID'=>$sLanguage, '%NAME_RU'=>$sRegionName]
            ]);
            $aResult = [];
            while ($aLocation = $oRes->fetch()){
                $aResult[] = $aLocation;
            }
        }
        $fEndSecond = microtime(true) - $fStartSecond;
        $fEndTotalTime = microtime(true) - $fStartTime;
        $oCache->set($sKey, [$aResult, [
            'total'=>$fEndTotalTime,
            'first'=>$fEndFirstProcessTime,
            'second'=>$fEndSecond
        ]]);
        return [$aResult, [
            'total'=>$fEndTotalTime,
            'first'=>$fEndFirstProcessTime,
            'second'=>$fEndSecond
        ]];

    }

    /**
     * @param int $iUserId
     * @return string
     * @throws \Exception
     */
    public static function generateAccessToken($iUserId)
    {
        $oResult = \Mobium\Api\AccessToken\AccessTokenTable::getList([
            'filter'=>[
                'USER_ID'=>$iUserId,
                'TYPE'=>'auth'
            ]
        ]);
        $aResult = $oResult->fetchAll();
        $iTime = time();
        if (count($aResult) === 0) {
            $sToken = md5('userMobium' . $iUserId . $iTime);
            $oAddResult = \Mobium\Api\AccessToken\AccessTokenTable::add([
                'BODY' => $sToken,
                'CREATED_AT' => $iTime,
                'LIFETIME' => 3600,
                'TYPE' => 'auth',
                'USER_ID' => $iUserId,
            ]);
        } elseif (count($aResult) === 1) {
            $sToken = $aResult[0]['BODY'];
        } else {
            throw new \Exception('Неоднозначный токен.');
        }
        return $sToken;
    }

    /**
     * @param $sRecaptchaToken
     * @param $sPlatform
     * @return bool
     * @throws \Exception
     */
    public static function checkRecaptcha($sRecaptchaToken, $sPlatform)
    {
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $sIosKey = '6LfqS7MUAAAAAA3ggHVqNlJZs2koY2KvKAABujF6';
        $sAndroidKey = '6LcuTLMUAAAAAHFiMXHIlgVmeswrhbLOKKHdBMH-';
        $sPlatform = strtolower($sPlatform);
        switch ($sPlatform){
            case 'android':
                $sCaptchaSecret = $sAndroidKey;
                break;
            case 'ios':
                $sCaptchaSecret = $sIosKey;
                break;
            default:
                throw new \Exception('Платформа не распознана.');
        }
        $data = [
            'secret' => $sCaptchaSecret,
            'response' => $sRecaptchaToken,
        ];
        $options = [
            'http' => [
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        $context  = stream_context_create($options);
        $verify = file_get_contents($url, false, $context);
        $captcha_success=json_decode($verify);
        if (json_last_error() !== JSON_ERROR_NONE || $captcha_success->success==false) {
            throw new \Exception('Каптча введена не правильно.');
        }
        return true;

    }

    public static function getAuthData($iAppId)
    {
        $oConnection = \Bitrix\Main\Application::getConnection();
        $oSqlHelper = $oConnection->getSqlHelper();
        $sSql = 'SELECT * FROM `mobium_auth_tries` WHERE `APP_ID`='.$oSqlHelper->convertToDbInteger((string) $iAppId);
        try {
            $oRecordSet = $oConnection->query($sSql);
        } catch (\Bitrix\Main\Db\SqlQueryException $e) {
            throw new \Exception('Ошибка БД.');
        }
        if (false === ($aAppData = $oRecordSet->fetch())){
            $aAppData['APP_ID'] = (int) $iAppId;
            $aAppData['__isNew'] = true;
            $aAppData['DATA'] = [
                'tries'=>0,
                'last_try'=>0
            ];
        } else {
            $aAppData['DATA'] = unserialize($aAppData['DATA']);
        }
        return $aAppData;
    }

    public static function saveAppData($aAppData)
    {
        $oConnection = \Bitrix\Main\Application::getConnection();
        $oSqlHelper = $oConnection->getSqlHelper();
        $bIsNew = isset($aAppData['__isNew']) ? (bool) $aAppData['__isNew'] : false;
        if ($bIsNew){
            $sSql = "INSERT INTO `mobium_auth_tries`(`APP_ID`, `DATA`) VALUES (".$oSqlHelper->convertToDbInteger((string) $aAppData['APP_ID']).",'".$oSqlHelper->forSql(serialize($aAppData['DATA']))."')";
        } else {
            $sSql = "UPDATE `mobium_auth_tries` SET `DATA`='".$oSqlHelper->forSql(serialize($aAppData['DATA']))."' WHERE `APP_ID`=".$oSqlHelper->convertToDbInteger((string) $aAppData['APP_ID']);
        }

        try {
            $oConnection->queryExecute($sSql);
        } catch (\Bitrix\Main\Db\SqlQueryException $e) {
            return false;
        }
        return true;
    }

    public static function deleteAuthData($iAppId)
    {
        $oConnection = \Bitrix\Main\Application::getConnection();
        $oSqlHelper = $oConnection->getSqlHelper();
        $sSql = 'DELETE FROM `mobium_auth_tries` WHERE `APP_ID`='.$oSqlHelper->convertToDbInteger((string) $iAppId);
        try {
            $oConnection->queryExecute($sSql);
        } catch (\Bitrix\Main\Db\SqlQueryException $e) {
        	//
        }
        return true;
    }

}