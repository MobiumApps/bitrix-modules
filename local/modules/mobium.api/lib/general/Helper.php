<?php

namespace Mobium\Api;


use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Config\Option;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Sale\Location\LocationTable;
use CSite;
use Exception;
use Mobium\Api\AuthTries\AuthTriesTable;
use phpDocumentor\Reflection\Types\Float_;

/**
 * Class ApiHelper
 * @package Mobium\Api
 */
abstract class Helper
{

	/**
	 * аторизация по токену
	 * @return bool
	 */
	public static function authorizeByHeader()
	{
		if (!empty($sToken = static::getTokenFromHeaders())) {
			try {
				return static::authorizeToken($sToken);
			} catch (\Exception $e) {
				return false;
			}
		}
		return false;
	}

	/**
	 * Выделить тоен из заголовков
	 * @return bool|mixed|string
	 */
	public static function getTokenFromHeaders()
	{
		$aHeaders = ['REMOTE_USER', 'HTTP_AUTHORIZATION', 'HTTP_AUTHORIZATIONTOKEN'];
		foreach ($aHeaders as $sHeader) {
			$sToken = $_SERVER[$sHeader] ?? '';
			if (preg_match('/^[a-f0-9]{32}$/i', $sToken)) {
				return $sToken;
			}
		}
		return false;
	}

	/**
	 * получаем пользователя по токену и каторизуем его в системе
	 * @param string $sToken
	 * @param bool $bCheckExpire
	 * @return bool
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\ObjectPropertyException
	 * @throws \Bitrix\Main\SystemException
	 */
	public static function authorizeToken($sToken, $bCheckExpire = false)
	{
		if (!empty($sToken)) {
			$oResult = AccessToken\AccessTokenTable::getList([
				'filter' => [
					'BODY' => $sToken,
					'TYPE' => 'auth'
				]
			]);
			$aTokenResult = $oResult->fetchAll();
			if (count($aTokenResult) === 1) {
				$aTokenResult = $aTokenResult [0];
				if ($bCheckExpire && ((int)($aTokenResult['CREATED_AT'] + (int)$aTokenResult['LIFETIME']) < time())) {
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
	 * Получаем значение свойства текущего польлзователя по его коду
	 * @param $sId - код свойства
	 * @return mixed|null
	 */
	public static function getProfileFieldValue($sId)
	{
		/**@var \CUser $USER */
		global $USER;
		$aUser=\CUser::GetByID($USER->GetID())->Fetch();
		switch ($sId) {
			case 'name':
				return $USER->GetFirstName();
			case 'last_name':
				return $USER->GetLastName();
			case 'birthday':
				if ($aUser) {
					return $aUser['PERSONAL_BIRTHDAY'];
				}
				return $USER->GetParam('PERSONAL_BIRTHDAY');
			case 'sex':
				if ($aUser) {
					$sGender = $aUser['PERSONAL_GENDER'];
				} else {
					$sGender = $USER->GetParam('PERSONAL_GENDER');
				}
				switch ($sGender) {
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
				if ($aUser) {
					return $aUser['PERSONAL_PHONE'];
				}
				return $USER->GetParam('PERSONAL_PHONE');
			case 'photo':
				if ($aUser) {
					return $aUser['PERSONAL_PHOTO'];
				}
				return $USER->GetParam('PERSONAL_PHOTO');
			case 'login':
				$sLogin = trim($USER->GetEmail());
				if (!empty($sLogin)) {
					return $sLogin;
				}
				if ($aUser) {
					return $aUser['PERSONAL_PHONE'];
				}
				return $USER->GetParam('PERSONAL_PHONE');
			case 'bonuses':
			case 'barcode':
			case 'card_code':
			case 'card_number':
			default:
				return null;

		}

	}

	/**
	 * @param $sId
	 * @param int $mValue
	 * @return mixed|null
	 */
	public static function setProfileFieldValue($sId, $mValue)
	{
		/**@var \CUser $USER */
		global $USER;
		switch ($sId) {
			case 'name':
				return $USER->Update($USER->GetID(), [
					'NAME' => $mValue
				]);
			case 'email':
				return $USER->Update($USER->GetID(), [
					'EMAIL' => $mValue
				]);
			case 'last_name':
				return $USER->Update($USER->GetID(), [
					'LAST_NAME' => $mValue
				]);
			case 'phone':
				$USER->SetParam('PERSONAL_PHONE', $mValue);
				return $USER->Update($USER->GetID(), [
					'PERSONAL_PHONE' => $mValue
				]);
			case 'login':
				return false;
			case 'bonuses':
			case 'barcode':
			case 'card_code':
			default:
				return null;

		}

	}

	/**
	 * @return bool|array
	 */
	public static function getDeliveryTypes()
	{
		$api = new static();
		return $api->deliveries();
	}

	/**
	 * @return bool|array
	 */
	public static function getDeliveryAreas($sDeliveryTypeId)
	{
		$api = new static();
		return $api->delivery_areas([
			"limit" => "1000",
			"deliveryType" => $sDeliveryTypeId
		]);
	}

	public static function getOfferProps()
	{
		$iIblockId = Option::get('mobium.api', 'offers_iblock', '27');
		$oRes = \Bitrix\Iblock\PropertyTable::getList([
			'select' => ['ID', 'CODE', 'NAME'],
			'filter' => [
				'=IBLOCK_ID' => $iIblockId,
			]
		]);
		$aResult = [];
		while ($aData = $oRes->fetch()) {
			$aResult[$aData['ID']] = $aData['NAME'] . ' (' . $aData['CODE'] . ')';
		}
		return $aResult;
	}

	public static function getProductsProps()
	{
		$iIblockId = Option::get('mobium.api', 'products_iblock', '26');
		$oRes = \Bitrix\Iblock\PropertyTable::getList([
			'select' => ['ID', 'CODE', 'NAME'],
			'filter' => [
				'=IBLOCK_ID' => $iIblockId,
			]
		]);
		$aResult = [];
		while ($aData = $oRes->fetch()) {
			$aResult[$aData['ID']] = $aData['NAME'] . ' (' . $aData['CODE'] . ')';
		}
		return $aResult;
	}

	public static function getBitrixDeliveries()
	{
		$aData = \Bitrix\Sale\Delivery\Services\Manager::getActiveList();
		$aResult = [];
		foreach ($aData as $aItem) {
			$aResult[$aItem['ID']] = $aItem['NAME'];
		}
		return $aResult;
	}

	public static function getMobiumDeliveries()
	{
		$aResult = [];
		if (false !== ($aData = static::getDeliveryTypes())) {
			foreach ($aData as $aItem) {
				$aResult[$aItem['id']] = $aItem['title'];
			}
		}
		return $aResult;
	}

	public static function getMobiumDeliveryAreas()
	{
		$aResult = [];
		if (false !== ($aData = static::getDeliveryTypes())) {
			foreach ($aData as $aItem) {
				if (false !== ($aDeliveryAreas = static::getDeliveryAreas($aItem['id']))) {
					foreach ($aDeliveryAreas as $aDeliveryArea) {
						$aResult[$aDeliveryArea['id']] = $aDeliveryArea['title'];
					}
				}
			}
		}
		return $aResult;
	}

	public static function getDeliveryAssociations()
	{
		$oResult = DeliveryTypeTable::getList(['select' => ['*'], 'filter' => ['ACTIVE' => 'Y']]);
		$aResult = [];
		while ($aAssociation = $oResult->fetch()) {
			$sKey = $aAssociation['DELIVERY_SERVICE_ID_MOBIUM'] . ($aAssociation['DELIVERY_SERVICE_AREA_ID'] ? ':' . $aAssociation['DELIVERY_SERVICE_AREA_ID'] : '');
			$aResult[$sKey] = $aAssociation['DELIVERY_SERVICE_ID_BITRIX'];
		}
		return $aResult;
	}

	public static function attachCardToCurrentUser($sCardNumber, array $aParams = [])
	{
		/**@var \CUser $USER */
		global $USER;
		return static::attachCardToUser($sCardNumber, $USER, $aParams);
	}

	/**
	 * @param \CUser $oUser
	 * @return bool|int
	 */
	public static function createUserAppProfile($oUser)
	{
		$aUserData = \CUser::GetByID($oUser->GetID())->Fetch();
		$arProfileFields = array(
			"NAME" => "Профиль покупателя " . $oUser->GetFirstName() . '',
			"USER_ID" => $oUser->GetID(),
			"PERSON_TYPE_ID" => 1
		);
		$PROFILE_ID = \CSaleOrderUserProps::Add($arProfileFields);
		if (!$PROFILE_ID) {
			return false;
		}
		$PROPS = [
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
		if (filter_var($oUser->GetEmail(), FILTER_VALIDATE_EMAIL)) {
			$PROPS[] = [
				"USER_PROPS_ID" => $PROFILE_ID,
				"ORDER_PROPS_ID" => 2,
				"NAME" => "E-Mail",
				"VALUE" => $oUser->GetEmail()
			];
		}
		foreach ($PROPS as $prop) {
			$iPropId = \CSaleOrderUserPropsValue::Add($prop);
		}
		$oUser->Update($oUser->GetId(), [
			'UF_APP_PROFILE' => $PROFILE_ID
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
		if (!isset($aUserData['UF_APP_PROFILE']) || empty($aUserData['UF_APP_PROFILE'])) {
			$iProfileId = self::createUserAppProfile($oUser);
		} else {
			$iProfileId = $aUserData['UF_APP_PROFILE'];
		}
		foreach ($aData as $sFieldId => $sValue) {
			$sName = null;
			$iPropId = null;
			switch ($sFieldId) {
				case 'city':
					$sName = 'Город';
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
			if (null === $sName) {
				continue;
			}
			$oRes = \CSaleOrderUserPropsValue::GetList([], ['USER_PROPS_ID' => $iProfileId, 'NAME' => $sName]);
			if (!($aFieldData = $oRes->Fetch())) {
				\CSaleOrderUserPropsValue::Add([
					'USER_PROPS_ID' => $iProfileId,
					'ORDER_PROPS_ID' => $iPropId,
					'NAME' => $sName,
					'VALUE' => $sValue
				]);
			} else {
				\CSaleOrderUserPropsValue::Update($aFieldData['ID'], [
					'VALUE' => $sValue
				]);
			}
		}
	}

	/**
	 * @param int $iUserId
	 * @return string
	 * @throws \Exception
	 */
	public static function generateAccessToken($iUserId)
	{
		$oResult = AccessToken\AccessTokenTable::getList([
			'filter' => [
				'USER_ID' => $iUserId,
				'TYPE' => 'auth'
			]
		]);
		$aResult = $oResult->fetchAll();
		$iTime = time();
		if (count($aResult) === 0) {
			$sToken = md5('userMobium' . $iUserId . $iTime);
			$oAddResult = AccessToken\AccessTokenTable::add([
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
		switch ($sPlatform) {
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
		$context = stream_context_create($options);
		$verify = file_get_contents($url, false, $context);
		$captcha_success = json_decode($verify);
		if (json_last_error() !== JSON_ERROR_NONE || $captcha_success->success == false) {
			throw new \Exception('Каптча введена не правильно.');
		}
		return true;

	}
	public static function getAuthData($iAppId)
	{
		$oRecordSet = AuthTriesTable::GetList([
			"filter" => [
				"APP_ID" => $iAppId
			]
		]);
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
		$bIsNew = isset($aAppData['__isNew']) ? (bool) $aAppData['__isNew'] : false;
		if ($bIsNew){
			unset($aAppData['__isNew']);
			AuthTriesTable::add($aAppData);
		} else {
			$id = $aAppData["APP_ID"];
			unset($aAppData["APP_ID"]);
			AuthTriesTable::update($id, $aAppData);
		}
		return true;
	}
	public static function deleteAuthData($iAppId)
	{
		return AuthTriesTable::delete($iAppId);
	}
}