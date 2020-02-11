<?php

namespace Mobium\Api;

use Bitrix\Currency\CurrencyManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UserTable;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Delivery;
use Bitrix\Sale\PaySystem;
use Bitrix\Sale\Order;
use Bitrix\Main\Context;
use Bitrix\Sale\DiscountCouponsManager;
use Mobium\Api\Verify;

Loc::loadMessages(__FILE__);

class Main implements iMain
{

	protected $errors;
	/**
	 * @var array - массив свойств
	 */
	protected $data;
	protected $d;
	protected $status;
	protected $version;
	protected $method;
	protected $app;
	protected $project;
	protected $region_data;
	protected $access_token;

	public function __construct()
	{
		mb_detect_order("CP1251,CP1252,ASCII,UTF-8");
		// self::toLog(serialize($_SERVER));
		$this->errors = [];
		$this->status = false;
		$this->d = false;
		self::toLog(file_get_contents('php://input'));
		$aInputData = json_decode(file_get_contents('php://input'), true);
		if (JSON_ERROR_NONE === json_last_error()) {
			if ($aInputData["data"]) {
				$this->data = $aInputData["data"] ?? null;
				$this->version = $aInputData["version"] ?? null;
				$this->method = $aInputData["method"] ?? null;
				$this->app = $aInputData["app"] ?? null;
				$this->project = $aInputData["project"] ?? null;
				$this->region_data = $aInputData["region_data"] ?? null;
				$this->access_token = $aInputData["access_token"] ?? null;
			}
			else{
				$this->data = $aInputData;
			}
		}
		self::toLog(json_encode(getallheaders()));
	}
	function iconvb($text){
		if (LANG_CHARSET != "UTF-8"){
			return iconv("UTF-8" , LANG_CHARSET, $text);
		}
		return $text;
	}
	function iconv(string $text): string
	{
		if (LANG_CHARSET != "UTF-8") {
			return iconv(mb_detect_encoding($text, mb_detect_order()), "UTF-8", $text);
		}
		return $text;
	}

	/**
	 * Возвращает json с полями регистрации
	 * @return string json ответа
	 */
	function getRegistrationFields(): string
	{

		$aFields = [];
		$aFilter = [
			'REGISTER_ACTIVE' => 'Y',
		];
		$aOrder = [
			'REGISTER_SORT' => 'ASC'
		];
		$oResult = RegistrationField\RegistrationFieldTable::getList([
			'filter' => $aFilter,
			'order' => $aOrder,
		]);
		while ($aField = $oResult->fetch()) {
			$aTempField = [
				'id' => $aField['SLUG'],
				'type' => $aField['REGISTER_TYPE'],
				'title' => $this->iconv($aField['REGISTER_TITLE']),
				'required' => $aField['REGISTER_REQUIRED'] == 'Y',
				'need_verification' => $aField['VERIFICATION_ACTIVE'] == 'Y'
			];
			if ($aTempField['need_verification']) {
				$aTempField['time'] = $aField['VERIFICATION_TIME'];
				$aTempField['text'] = $this->iconv($aField['VERIFICATION_TEXT']);
				$aTempField['editable'] = $aField['EDITABLE'] == 'Y';
				$aTempField['code_input_type'] = $aField['VERIFICATION_TYPE'];
			}
			if ($aTempField['type'] === 'sex_select') {
				$aTempField['options'] = [
					[
						'id' => 'male',
						'value' => $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_FIELDS_MAN"))
					],
					[
						'id' => 'female',
						'value' => $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_FIELDS_WOMAN")),
					]
				];
				$aTempField['type'] = 'select_box';
			}
			$aFields[] = $aTempField;
		}
		if (!count($aFields)) {
			$this->errors[] = $this->iconv(Loc::GetMessage("MOBIUM_API_MODULE_MAIN_NO_REGISTER"));
			return $this->result();
		}
		return $this->result([
			"fields" => $aFields
		]);
	}

	/**
	 * Возвращает json со списком полей для восстановления пароля
	 * @return string json ответа
	 */
	function getRestoreFields(): string
	{
		$aFilter = [
			'RESTORE_ACTIVE' => 'Y',
		];
		$aOrder = [
			'RESTORE_SORT' => 'ASC'
		];
		$oResult = RegistrationField\RegistrationFieldTable::getList([
			'filter' => $aFilter,
			'order' => $aOrder,
		]);
		while ($aField = $oResult->fetch()) {
			$aTempField = [
				'id' => $aField['SLUG'],
				'type' => $aField['REGISTER_TYPE'],
				'title' => $this->iconv($aField['REGISTER_TITLE'])
			];
			$aFields[] = $aTempField;
		}
		return $this->result([
			"fields" => $aFields
		]);
	}

	/**
	 * Возвращает json со сведениями о профиле пользователя
	 * @return string json ответа
	 */
	function getUserProfile(): string
	{
		if (!ApiHelper::authorizeByHeader()) { // проверяем авторизацию
			$this->errors[] = "not_authorized";
			return $this->result();
		}
		global $USER;
		$sUserPhoto = '';
		if (($iUserPhoto = $USER->GetParam('PERSONAL_PHOTO'))) {
			$aFile = \CFile::GetFileArray($iUserPhoto);
			if (substr($aFile['SRC'], 0, 1) == '/') {
				$sUserPhoto = 'https://' . $_SERVER['HTTP_HOST'] . $aFile['SRC'];
			}
		}
		$aFilter = [
			'PROFILE_ACTIVE' => 'Y',
		];
		$aOrder = [
			'PROFILE_SORT' => 'ASC'
		];
		$oResult = RegistrationField\RegistrationFieldTable::getList([
			'filter' => $aFilter,
			'order' => $aOrder,
		]);
		$aFields = [];
		$aCardData = ApiHelper::getCurrentUserCardData($USER->GetID());
		while ($aField = $oResult->fetch()) {
			$aTempField = [
				'cabinet_field_type' => $aField['PROFILE_TYPE'],
			];
			$bAddToResult = true;

			switch ($aTempField['cabinet_field_type']) {
				case 'name_field':
					//$aTempField['cabinet_field_type'] = 'title_text_field';
					$aTempField['editable'] = $aField['EDITABLE'] == 'Y';
					$aTempField['id'] = $aField['SLUG'];
					$aTempField['value'] = $this->iconv(ApiHelper::getProfileFieldValue($aField['SLUG']));
					$aTempField['image'] = $sUserPhoto;
					$aTempField['title'] = $this->iconv($aField['PROFILE_TITLE']);
					break;
				case 'image_action_field':
					$aTempField['action'] = [
						'type' => $aTempField['PROFILE_ACTION'],
						'param' => $aTempField['PROFILE_ACTION_PARAM']
					];
					$aTempField['image'] = '';
					break;
				case 'title_text_field':
					$aTempField['editable'] = $aField['EDITABLE'] == 'Y';
					$aTempField['id'] = $aField['SLUG'];
					$aTempField['title'] = $this->iconv($aField['PROFILE_TITLE']);
					$aTempField['value'] = $this->iconv(ApiHelper::getProfileFieldValue($aField['SLUG']));
					break;
				case 'text_field':
					$aTempField['value'] = $this->iconv(ApiHelper::getProfileFieldValue($aField['SLUG']));
					if (null === $aTempField['value']) {
						$bAddToResult = false;
					}
					$aTempField['editable'] = $aField['EDITABLE'] == 'Y';
					$aTempField['id'] = $aField['SLUG'];
					break;
				case 'action_field':
					$aTempField['action'] = [
						'type' => $aTempField['PROFILE_ACTION'],
						'param' => $aTempField['PROFILE_ACTION_PARAM']
					];
					$aTempField['value'] = ApiHelper::getProfileFieldValue($aField['SLUG']);
					if ($aTempField['value'] === null) {
						$bAddToResult = false;
					}
					break;
				case 'bonus_field':
                    $bAddToResult = false;
					if ($aCardData["BONUS_POINTS"] > 0) {
						$aBonusField['amount'] = (float) $aCardData['BONUS_POINTS'];
						$aBonusField['available_proportion'] = $aCardData["BONUS_AVAILABLE"] ?? 1;
					}
					else {
                        $aBonusField = [
                            "amount" => 0,
                            "available_proportion" =>1
                        ];
                    }
					break;
				case 'barcode_field':
					$aTempField['id'] = $aField['SLUG'];
					$aTempField['image'] = $this->generateBarcode($aCardData['UF_CODE']);
					break;
			}
			if ($aField['VERIFICATION_ACTIVE'] == 'Y') {
				$aTempField['need_verification'] = $aField['VERIFICATION_ACTIVE'] == 'Y';
				$aTempField['time'] = $aField['VERIFICATION_TIME'];
				$aTempField['text'] = $aField['VERIFICATION_TEXT'];
				$aTempField['editable'] = $aField['EDITABLE'] == 'Y';
				$aTempField['code_input_type'] = $aField['VERIFICATION_TYPE'];
			}
			if ($bAddToResult) {
				$aFields[] = $aTempField;
			}
		}

		$result = [
			"fields" => $aFields
		];
		if(count($aBonusField)) {
			$result["bonus"] = $aBonusField;
		}
		$result["discount"]["value"] = ApiHelper::GetDiscount() ?? 0;
		return $this->result($result);
	}

	/**
	 * Редактирование пользователя
	 * @return string json ответа
	 */
	function userChangeProfile(): string
	{
		if (!ApiHelper::authorizeByHeader()) { // проверяем авторизацию
			$this->errors[] = "not_authorized";
			return $this->result();
		}
		$fields = [];
		foreach ($this->fields as $field) {
			$fields[ApiHelper::getProfileKey($field['field_id'])] = $this->iconvb($field['value']);

		}
		global $USER;
		$userId = $USER->GetID();
		$oUser = new \CUser;
		$oUser->Update($userId, $fields);
		return $this->result();
	}

	/**
	 * Восстановление пароля
	 * @return string json ответа
	 */
	function userRestorePassword(): string
	{
		$aRequestData = null;
		if (isset($this->login)) {
			$oUserResult = \CUser::GetByLogin(trim($this->login));
		} elseif (isset($this->email)) {
			$oUserResult = \CUser::GetList($by = "ID", $order = "ASC", ['EMAIL' => $this->email]);
		}
		if ($aUser = $oUserResult->Fetch()) {
			$aRequestData = ['login' => $aUser['LOGIN'], 'email' => $aUser['EMAIL']];
		}
		if ($aRequestData) {
			/**@var CUser $USER */
			global $USER;
			$aResult = $USER->SendPassword($aRequestData['login'], $aRequestData['email']);
			if ($aResult['TYPE'] == 'OK') {
				return $this->result([
					'message' => $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_EMAIL_SENT"))
				]);
			}
		} else {
			$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_RESTORE_ERROR"));
			return $this->result();
		}
	}

	/**
	 * Регистрация пользователя
	 * @return string json ответа
	 */
	function userRegistration(): string
	{
		$oEventManager = \Bitrix\Main\EventManager::getInstance();
		$oEventManager->addEventHandler("main", "OnAfterUserRegister", '\Mobium\Api\EventHandler::onAfterUserRegister');
		if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
			$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_WRONG_EMAIL"));
			\COption::SetOptionString("main", "captcha_registration", "Y");
			return $this->result();
		}
		$this->sanitizePhone($this->phone);
		$iCheckEmail = $this->checkMail();
		$iCheckPhone = $this->checkPhone();
		if (false == $iCheckEmail) {
			$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_EMAIL_BUSY"));
			\COption::SetOptionString("main", "captcha_registration", "Y");
			return $this->result();
		}
		if (false == $iCheckPhone) {
			$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_PHONE_BUSY"));
			\COption::SetOptionString("main", "captcha_registration", "Y");
			return $this->result();
		}
		$obUser = new \CUser;
		$arFields = Array(
			"NAME"              => $this->iconvb($this->name),
			"LAST_NAME"         => $this->iconvb($this->last_name) ?? '',
			"EMAIL"             => $this->email,
			"LOGIN"             => $this->login ?? $this->email,
			'PERSONAL_PHONE' 	=> $this->phone,
			"LID"               => "ru",
			"ACTIVE"            => "N",
			"PASSWORD"          => $this->password,
			"CONFIRM_PASSWORD"  => $this->password,
		);
		if (isset($this->birthday)) {
			$sInputFormat = $sOutputFormat = 'd.m.Y';
			$oDateTime = \DateTime::createFromFormat($sInputFormat, $this->birthday);
			if (false !== $oDateTime) {
				$arFields['PERSONAL_BIRTHDAY'] = $oDateTime->format($sOutputFormat);
			}
		}
		if (isset($this->sex)) {
			switch ($this->sex) {
				case 'male':
					$arFields['PERSONAL_GENDER'] = 'M';
					break;
				case 'female':
					$arFields['PERSONAL_GENDER'] = 'F';
					break;
			}
		}
		global $USER;
		$ID = $obUser->Add($arFields);
		if ($ID) {
			$iTime = time();
			$sToken = md5('userMobium' . $ID . $iTime);
			try {
				$oAddResult = AccessToken\AccessTokenTable::add([
					'BODY' => $sToken,
					'CREATED_AT' => $iTime,
					'LIFETIME' => 3600,
					'TYPE' => 'auth',
					'USER_ID' => $ID,
				]);
			} catch (Exception $e) {
				$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_TOKEN_ERROR"));
				\COption::SetOptionString("main", "captcha_registration", "Y");
				return $this->result();
			}
			$this->verification($ID);
			if (!$this->status || $this->status == "ok") {
                $USER->Update($ID, [
                    'ACTIVE' => 'Y'
                ]);
                return $this->result(['accessToken' => $sToken]);
            }
            return $this->result();
		}
		else {
			$this->errors[]=$this->iconv($obUser->LAST_ERROR);
		}
		return $this->result();
	}

	function verification($userID) {
        $verificationField = RegistrationField\RegistrationFieldTable::GetRow([
            'filter' => [
                "VERIFICATION_ACTIVE" => "Y"
            ]
        ]);
        if($field = $verificationField["SLUG"]){
            $code = Verify\verify::createCode($this->appId, $userID, $verificationField["VERIFICATION_TIME"]);
            if($this->sendCode($code, $this->{$field})) {
                // отправить код посетителю
                $this->status = "verification";
            }
            else {
                $this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_SMS_ERROR"));
            }
        }
    }
    function sendCode($code, $address) {
	    if($address) {
            $sms = new \Bitrix\Main\Sms\Event(
                "SMS_USER_CONFIRM_NUMBER",
                [
                    "USER_PHONE" => $address,
                    "CODE" => $code,
                ]
            );
            $sms->setSite(SITE_ID);
            $smsResult = $sms->send(true);
            return $smsResult->isSuccess();
        }
    }
	/**
	 * Логин
	 * @return string json ответа
	 */
	function userAuthorization(): string
	{
//		if (isset($this->recaptchaToken)) {
//			try {
//				ApiHelper::checkRecaptcha($this->recaptchaToken, $this->platform);
//			} catch (\Exception $e) {
//				$this->errors[] = 'Капча введена не правильно.';
//				return $this->result();
//			}
//		} else {
//			$this->errors[] = 'Пожалуйста, обновите приложение, что бы воспользоваться личным кабинетом.';
//			return $this->result();
//		}
		try {
			$aAppData = ApiHelper::getAuthData($this->appId);
		} catch (\Exception $e) {
			$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_DB_ERROR"));
			return $this->result();
		}
		if ($aAppData['DATA']['tries'] >= 5) { // считаем кол-во попыток
			$fDiff = microtime(true) - (float)$aAppData['DATA']['last_try'];
			if (($aAppData['DATA']['tries'] <= 10 && $fDiff <= 10) || ($aAppData['DATA']['tries'] > 10 && $fDiff <= 600)) {
				if (!ApiHelper::saveAppData($aAppData)) {
					$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_DB_ERROR"));
					return $this->result();
				}
				$fNextTry = $aAppData['DATA']['tries'] <= 10 ? 10 - $fDiff : 600 - $fDiff;
				$iMins = floor($fNextTry / 60.0);
				$iSecs = $fNextTry % 60;
				if($iMins > 0) {
					$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_MINUTE_LIMIT",["#min#"=>$iMins,"#sec#"=>$iSecs]));
				}
				else {
					$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_SECOND_LIMIT",["#sec#"=>$iSecs]));
				}
				return $this->result();
			}
		}
		$aAppData['DATA']['tries']++;
		$aAppData['DATA']['last_try'] = microtime(true);
		if (!ApiHelper::saveAppData($aAppData)) {
			$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_DB_ERROR"));
			return $this->result();
		}
		/**@var CUser $USER */
		global $USER;
		if (!is_object($USER)) {
			$USER = new \CUser();
		}
		if (!isset($this->login, $this->password) || empty($this->login) || empty($this->password)) {
			$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_LOGIN_REQUIRED"));
			return $this->result();
		}
		if (!($this->login = filter_var($this->login, FILTER_VALIDATE_EMAIL))) {
			$this->login = $this->sanitizePhone(trim($this->login));
		}
		$arUser = \CUser::GetByLogin($this->login)->Fetch();
		$iUserId = $arUser["ID"];
		if (0 >= $iUserId) {
			$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_USER_NOTFOUND"));
			return $this->result();
		}
		$mInputData = $USER->Login($this->login, $this->password);
		if (true === $mInputData) {
			try {
				$sToken = ApiHelper::generateAccessToken($USER->GetID());
			} catch (\Exception $e) {
				$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_WRONG_TOKEN"));
				return $this->result();
			}
			if ($sToken) {
				return $this->result([
					'accessToken' => $sToken
				]);
			} else {
				$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_WRONG_TOKEN"));
				return $this->result();
			}
		} else {
			$this->errors[] = strip_tags($this->iconv($mInputData['MESSAGE']));
			$this->d = [$iUserId, $this->login];
			return $this->result();
		}
		return $this->result();
	}

	/**
	 * Выход
	 * @return string json ответа
	 */
	function userLogout(): string
	{
		if (!ApiHelper::authorizeByHeader()) { // проверяем авторизацию
			$this->errors[] = "not_authorized";
			return $this->result();
		}
		$sToken = ApiHelper::getTokenFromHeaders();
		if (!empty($sToken)) {
			global $USER;
			$oResult = AccessToken\AccessTokenTable::getList([
				'filter' => [
					'BODY' => $sToken,
					'TYPE' => 'auth'
				]
			]);
			$aResult = $oResult->fetchAll();
			if (count($aResult) === 1) {
				return $this->result([
					"fields"=>[
						"id" => "email",
						"type" => "email",
						"title" => "email"
					]
				]);
			} else {
				$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_INVALID_TOKEN"));
				return $this->result();
			}
		} else {
			$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_EMPTY_TOKEN"));
			return $this->result();
		}
		return $this->result();
	}

	/**
	 * Авторизация по коду
	 * @return string json ответа
	 */
	function authByCode(): string
	{
		$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_SMS_OFF"));
		return $this->result();
	}

	/**
	 *
	 * @return string json ответа
	 */
	function verifyCode(): string
	{
		$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_SMS_OFF"));
		return $this->result();
	}

	/**
	 * История заказов
	 * @return string json ответа
	 */
	function userOrderHistory($limit=50,$page=1): string
	{
		$offset = $page * $limit;
		if (!ApiHelper::authorizeByHeader()) { // проверяем авторизацию
			$this->errors[] = "not_authorized";
			return $this->result();
		}
		global $USER;
		$oOrderResult = Order::getList([
			'select'=>['ID'],
			'filter'=>[
				'USER_ID'=> $USER->GetID()
			],
			'limit' => $limit,
			'offset' => $offset
		]);
		$aResult = [];
		while ($aOrder = $oOrderResult->fetch()) {
			$oOrder = Order::load($aOrder['ID']);
			/** @var \Bitrix\Sale\BasketItem[] $aBasketItems */
			$oShipmentCollection = $oOrder->getShipmentCollection();
			$iShipmentCollectionCount = $oShipmentCollection->getIterator()->count();

			/** @var Shipment $oShipment */
			$oShipment = $oShipmentCollection->getItemByIndex(0);
			//$oShipment = $oShipmentCollection->getItemByIndex($iShipmentCollectionCount - 1);
			$sDeliveryName = $oShipment->getDeliveryName();
			//$sDeliveryName = $oShipment->getDeliveryName();
			//$sDeliveryName = $oOrder->getShipmentCollection()->getSystemShipment()->getDelivery()->getName();
			/** @var \Bitrix\Sale\Payment $oPayment */
			foreach ($oOrder->getPaymentCollection() as $oPayment){
				$sPayment = $oPayment->getPaymentSystemName();
				$sOrderStatus = $oPayment->isPaid() ? GetMessage("MOBIUM_API_OPLACEN") :GetMessage("MOBIUM_API_NE_OPLACEN");
			}
			$aItems = [];
			/** @var \Bitrix\Sale\BasketItem $oBasketItem */
			foreach ($oOrder->getBasket()->getBasketItems() as $oBasketItem){
				$aItems[] = [
					'id'=>$oBasketItem->getProductId(),
					'count'=>$oBasketItem->getQuantity(),
					'price'=>$oBasketItem->getPrice()
				];
			}
			$aTempRes = [
				'id'=>$oOrder->getId(),
				'status'=>$sOrderStatus,
				'total'=>$oOrder->getPrice(),
				'deliveryType'=>$sDeliveryName,
				'items'=>$aItems,
			];
			$aResult[] = $aTempRes;
		}
		return $this->result([
			'orders'=>$aResult
		]);
	}

	/**
	 * Commit order
	 * @return string json ответа
	 */
	function commitOrder(): string
	{
		global $USER;
		$aTiming = [];
		$fStartTotalTime = microtime(true);
		\Bitrix\Main\Loader::includeModule('sale');
		\Bitrix\Main\Loader::includeModule('catalog');
		$sSiteId = Context::getCurrent()->getSite();
		if (!isset($this->order_info)){
			$this->status = 1;
			$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_INVALID_ORDER"));
			return $this->result();
		}
		$aOrderInfo = $this->order_info;
		$aPaymentType = $aOrderInfo['payment_type'];

		global $USER;
		if (!ApiHelper::authorizeByHeader()){
			$sName = $aOrderInfo['name'] ?? null;
			$sPhone = $aOrderInfo['phone'] ?? null;
			$sEmail = $aOrderInfo['email'] ?? null;
			if (isset($this->access_token) && !empty($this->access_token)){
				$sToken = $this->access_token;
				ApiHelper::authorizeToken($sToken);
			} elseif (null !== $sEmail) {
				$aRegisterResult = $USER->SimpleRegister($sEmail, $sSiteId);
				if ($aRegisterResult['TYPE'] !== 'OK' || !$USER->GetID()){
					$iAnonymousUserID = \CSaleUser::GetAnonymousUserID();
					$USER->Authorize($iAnonymousUserID);
				}
			}

		}
		if (!$USER->GetID()){
			$this->status = 3;
			$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_INVALID_ORDER"));
			return $this->result();
		}
		$currencyCode = CurrencyManager::getBaseCurrency();
		$fStartCreating = microtime(true);
		$oBasket = $this->createBasket($this->items, $sSiteId, $currencyCode);

		DiscountCouponsManager::init();
		$oOrder = Order::create($sSiteId, $USER->GetID() );
		$oCollection = $oOrder->getPropertyCollection();
		if (false !== ($aCardData = ApiHelper::getCurrentUserCardData($USER->GetID()))){
			$sCode = $aCardData['UF_CODE'];
			if (null !== $oDiscountCard = $this->getPropertyByCode($oCollection, 'DISCOUNT_CARD')){
				$oDiscountCard->setValue($sCode);
			}
		}
		$aTiming['creating'] = microtime(true) - $fStartCreating;

		/**
		 * 1 - физическое лицо
		 * 2 - Юр. лицо
		 */
		$oOrder->setPersonTypeId(1);
		$oOrder->setField('CURRENCY', $currencyCode);
		if (isset($aOrderInfo['comments']) && !empty($aOrderInfo['comments'])) {
			$oOrder->setField('USER_DESCRIPTION', $aOrderInfo['comments']);
		}
		$oOrder->setBasket($oBasket);
		$oShipmentCollection = $oOrder->getShipmentCollection();

		if ($this->version == '2.0'){
			$sDeliveryTypeId = $this->delivery['type_id'];
		} else {
			$sDeliveryTypeId = $this->delivery['deliveryTypeId'];
		}

		/*\Bitrix\Sale\DiscountCouponsManager::init(
			\Bitrix\Sale\DiscountCouponsManager::MODE_ORDER, [
				"userId" => $oOrder->getUserId(),
				"orderId" => $oOrder->getId()
			]
		);*/
		//\Bitrix\Sale\DiscountCouponsManager::add($aOrderInfo['marketing']['promoCode']);

		$aShipmentList = ApiHelper::getDeliveryAssociations();
		if (isset($aShipmentList[$sDeliveryTypeId])){
			$oTemp = Delivery\Services\Manager::getObjectById((int) $aShipmentList[$sDeliveryTypeId]);
		}
		elseif ($sDeliveryTypeId) {
			$oTemp = Delivery\Services\Manager::getObjectById((int) $sDeliveryTypeId);
		}
		else { // Если способ доставки не найден - то офрмляем без доставки
			$oTemp = Delivery\Services\Manager::getObjectById(Delivery\Services\EmptyDeliveryService::getEmptyDeliveryServiceId());
		}

		$oShipment = $oShipmentCollection->createItem($oTemp); // создаем отгрузку
		$oShipment->setField('CURRENCY', $currencyCode);

		//$oShipment->setStoreId();
		$aDeliveryInfo = [
			'phone'=>$aOrderInfo['phone'],
			'name'=>$this->iconvb($aOrderInfo['name'])
		];
		$bNeedToLoadLocation = true;
		if (isset($this->delivery['data'])){
			$aDeliveryData = $this->delivery['data'];
			foreach ($aDeliveryData as $aField){
				if ($aField['type'] == 'outpost_field'){
					$fStartOutpostTime  = microtime(true);
					$oRes = CCatalogStore::GetList([], ['UF_CODE_1C'=>$aField['value']], false, false, ['ID']);
					while ($aStore = $oRes->Fetch()){
						$oShipment->setStoreId($aStore['ID']);
						$bNeedToLoadLocation = false;
						break 2;
					}
					$aTiming['outpost'] = microtime(true) - $fStartOutpostTime;
					//$oShipment->setStoreId(10);
				}
				if ($aField['type'] == 'street_place_id' && !isset($aDeliveryInfo['street'])){
					$aDeliveryInfo['street']=$aField['value'];
				}
				if ($aField['id'] == 'building'){
					$aDeliveryInfo['building'] = $aField['value'];
				}
				if ($aField['id'] == 'apartments'){
					$aDeliveryInfo['apartments']  = $aField['value'];
				}
			}
		}

		if (isset($aInputData['region_data']['title'])){
			$aDeliveryInfo['city'] = $this->region_data['title'];
		}
		if ($bNeedToLoadLocation && isset($this->region_data['location'])){
			if (isset($this->region_data['location']['place_id'])){
				$sResult = file_get_contents(
					'https://maps.googleapis.com/maps/api/geocode/json?language=ru&key=AIzaSyAQguMPPedN6co-VuT7YX4PkDPlpz3WQto&place_id='.$this->region_data['location']['place_id']
				);

				if (null !== ($aGeoData = json_decode($sResult, true))){
					if ($aGeoData['status'] === 'OK'){
						if (isset($aGeoData['results']) && is_array($aGeoData['results']) && count($aGeoData['results']) > 0){
							$aGeoResult = $aGeoData['results'][0];
							foreach ($aGeoResult['address_components'] as $aAddressComponent){
								if (in_array('locality', $aAddressComponent['types'])){
									$aDeliveryInfo['city'] = $aAddressComponent['long_name'];
								}
								if (in_array('street_number', $aAddressComponent['types'])){
									$aDeliveryInfo['building'] = $aAddressComponent['long_name'];
								}
								if (in_array('route', $aAddressComponent['types'])){
									$aDeliveryInfo['street'] = $aAddressComponent['long_name'];
								}
							}
						}
					} else {
						$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_ADDRESS_ERROR"));
						$this->status = 7;
						return $this->result();
					}
				}
			}

		}
		self::toLog(serialize($aOrderInfo));
		if ($aOrderInfo['email']){
			if(filter_var($aOrderInfo['email'], FILTER_VALIDATE_EMAIL)) {
				$aDeliveryInfo['email'] = $aOrderInfo['email'];
			}
			else {
				$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_WRONG_EMAIL"));
				return $this->result();
			}
		}
		if(!$this->applyBonuses()) return $this->result();

		ApiHelper::updateProfileForOrder($USER, $aDeliveryInfo);
		$fStartDATime = microtime(true);
		$oShipmentCollection->calculateDelivery();

		$oShipmentItemCollection = $oShipment->getShipmentItemCollection();
		foreach ($oBasket as $oBasketItem){
			$oItem = $oShipmentItemCollection->createItem($oBasketItem);
			$oItem->setQuantity($oBasketItem->getQuantity());
		}
		$aPaymentsSystemsList = [
			'cash'=>1,
			'yandex_money'=>2,
			'bank_cards'=>3,
			'terminals'=>4,
			'web_money'=>5,
			'sberbank_ticket'=>6,
			'bank_transfer'=>7,
			'internal_bill'=>8,
			'robokassa'=>9,
			'cod'=>10,
			'bonuses'=>11
		];
		$iPaymentSystemId = $aPaymentSystemAssociation[$aPaymentType['title']] ?? 1; // поумолчанию оплата наличными

		$oPaymentCollection = $oOrder->getPaymentCollection();
		$oPayment = $oPaymentCollection->createItem(PaySystem\Manager::getObjectById($iPaymentSystemId));
		$oPayment->setField('PAY_SYSTEM_ID', $iPaymentSystemId);
		$oPayment->setField('SUM', $oOrder->getPrice());
		$oPayment->setField('CURRENCY', $oOrder->getCurrency());
		$aTiming['delivery'] = microtime(true) - $fStartDATime;

		$oPropertyCollection = $oOrder->getPropertyCollection();
		if (isset($aOrderInfo['phone'])){
			$oPhoneProp = $oPropertyCollection->getPhone();
			if(!$oPhoneProp) {
				$oPhoneProp = $this->getPropertyByCode($oPropertyCollection, "PHONE");
			}
			$oPhoneProp->setValue($aOrderInfo['phone']);
		}
		if (isset($aOrderInfo['name'])){
			$oNameProp = $oPropertyCollection->getPayerName();
			if(!$oNameProp) {
				$oNameProp = $this->getPropertyByCode($oPropertyCollection, "NAME");
			}
			$oNameProp->setValue($this->iconvb($aOrderInfo['name']));
		}
		if (isset($aOrderInfo['email'])){
			$oEmailProp = $oPropertyCollection->getUserEmail();
			if(!$oEmailProp) {
				$oEmailProp = $this->getPropertyByCode($oPropertyCollection, "EMAIL");
			}
			$oEmailProp->setValue($aOrderInfo['email']);
		}

		if (isset($aOrderInfo['marketing'], $aOrderInfo['marketing']['promoCode']) && !empty(trim($aOrderInfo['marketing']['promoCode']))){
			$aData = DiscountCouponsManager::getData($aOrderInfo['marketing']['promoCode']);
			if (is_array($aData) && (int)$aData['ID'] > 0){
				if ($aData['STATUS'] == DiscountCouponsManager::STATUS_APPLYED || $aData['STATUS'] == DiscountCouponsManager::STATUS_ENTERED ) {
					DiscountCouponsManager::add($aOrderInfo['marketing']['promoCode']);
				}
			} else{
				$this->status=4;
				$this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_NO_PROMOCODE"));
				return $this->result();
			}
		}
		$fStartSaving = microtime(true);
		$oOrder->doFinalAction(true);
		$oResult = $oOrder->save();
		$aTiming['saving_order'] = microtime(true) - $fStartSaving;
		$fStartUpdate = microtime(true);

		$aTiming['total'] = microtime(true) - $fStartTotalTime;
		if ($oResult->isSuccess()){
			$fCreatingPayment = microtime(true);
			$aReturnData = ['order_id'=>$oOrder->getId(), 'order_info'=>$this->extractOrder($oOrder)];
			$aTiming['creating_payment'] = microtime(true) - $fCreatingPayment;
			$this->status=0;
			return $this->result($aReturnData);
		} else {
			$this->status = 4;
			$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_ORDER_NOTSAVE"));
			return $this->result();
		}
		$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_ORDERS_OFF"));
		return $this->result();
	}
	function createBasket($aItems, $sSiteId, $sCurrencyCode){
		$oBasket = Basket::create($sSiteId);
		// $aItems = $aInputData['data']['items'];
		foreach ($aItems as $aItem){
			$itemId = $aItem['offer']['id'] ?? $aItem['id'];
			$oItem = $oBasket->createItem('catalog', $itemId);
			$oItem->setFields([
				'QUANTITY'=>$aItem['count'],
				'CURRENCY'=>$sCurrencyCode,
				'LID'=>$sSiteId,
				'PRODUCT_PROVIDER_CLASS' => '\CCatalogProductProvider',
			]);
		}
		return $oBasket;
	}
	function getPropertyByCode($propertyCollection, $code)  {
		foreach ($propertyCollection as $property)
		{
			if($property->getField('CODE') == $code)
				return $property;
		}
		return null;
	}

	/**
	 *
	 * @return string json ответа
	 */
	function startAgent(): string
	{
		return $this->result();
	}

	/**
	 * Применить промо код к текущей корзине
	 * @return string json ответа
	 */
	function applyCode(): string
	{
		$sSiteId = Context::getCurrent()->getSite();
		if (!isset($this->basket, $this->basket['items'])){
			$this->status = 1;
			$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_INVALID_BASKET"));
			return $this->result();
		}
		$aOrderInfo = $this->order_info;
		$aPaymentType = $aOrderInfo['payment_type'];
		/**@var CUser $USER */
		global $USER;
		if (!ApiHelper::authorizeByHeader()){
			$sEmail = $aOrderInfo['email'] ?? null;
			if (isset($this->access_token) && !empty($this->access_token)){
				$sToken = $this->access_token;
				ApiHelper::authorizeToken($sToken);
			} elseif (null !== $sEmail) {
				$aRegisterResult = $USER->SimpleRegister($sEmail, $sSiteId);
				if ($aRegisterResult['TYPE'] !== 'OK' || !$USER->GetID()){
					$iAnonymousUserID = \CSaleUser::GetAnonymousUserID();
					$USER->Authorize($iAnonymousUserID);
				}
			} else {
				$iAnonymousUserID = \CSaleUser::GetAnonymousUserID();
				$USER->Authorize($iAnonymousUserID);
			}
		}
		if (!$USER->GetID()){
			$this->status = 3;
			$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_ORDER_ERROR"));
			return $this->result();
		}
		$currencyCode = CurrencyManager::getBaseCurrency();
		$oBasket = $this->createBasket($this->basket['items'], $sSiteId, $currencyCode);

		$oOrder = Order::create($sSiteId, $USER->GetID() );
		/**
		 * 1 - физическое лицо
		 * 2 - Юр. лицо
		 */
		$oOrder->setPersonTypeId(1);
		$oOrder->setField('CURRENCY', $currencyCode);
		$oOrder->setBasket($oBasket);
		$oShipmentCollection = $oOrder->getShipmentCollection();
		$oTemp = Delivery\Services\Manager::getObjectById(Delivery\Services\EmptyDeliveryService::getEmptyDeliveryServiceId());
		$oShipment = $oShipmentCollection->createItem($oTemp);
		$oShipmentItemCollection = $oShipment->getShipmentItemCollection();
		foreach ($oBasket as $oBasketItem){
			$oItem = $oShipmentItemCollection->createItem($oBasketItem);
			$oItem->setQuantity($oBasketItem->getQuantity());
		}
		$oOrder->doFinalAction(true);
		if (isset($this->promoCode) && !empty(trim($this->promoCode))){
			$aData = DiscountCouponsManager::getData($this->promoCode);
			if (is_array($aData) && (int)$aData['ID'] > 0){
				if ($aData['STATUS'] == DiscountCouponsManager::STATUS_APPLYED || $aData['STATUS'] == DiscountCouponsManager::STATUS_ENTERED ) {
					if (!DiscountCouponsManager::add($this->promoCode)){
						$this->status = 4;
						$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_ERROR_PROMOCODE"));
						return $this->result();
					}
					$oOrder->getDiscount()->calculate();
				}
			} else{
				$this->status = 4;
				$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_WRONG_PROMOCODE"));
				return $this->result();
			}
		}
		DiscountCouponsManager::init();
		$oOrder->doFinalAction(true);
		$this->status = 0;
		return $this->result($this->extractOrder($oOrder));
	}

	/**
	 * Генерирует Bar код
	 * @param $code - фраза для кодирования
	 * @return string
	 * @throws \Picqer\Barcode\Exceptions\BarcodeException
	 */
	function generateBarcode($code): string
	{
	    if (!$code) return false;
		$oGenerator = new \Picqer\Barcode\BarcodeGeneratorPNG();
		$sContent = $oGenerator->getBarcode($code, $oGenerator::TYPE_EAN_13, 2, 70);
		imagepng(imagecreatefromstring($sContent), $_SERVER['DOCUMENT_ROOT'].'/upload/barcodes/code'.$code.'.png');
		return 'https://' . $_SERVER['HTTP_HOST'] . '/upload/barcodes/code'.$code.'.png';
	}

	/**
	 * Верификация поля
	 * @return string json ответа
	 */
	function verifyData(): string
	{
	    if($userId = Verify\verify::checkCode($this->code, $this->appId)){
            $sToken = AccessToken\AccessTokenTable::getRow([
                'filter' => [
                    "USER_ID" => $userId
                ]
            ])["BODY"];
            global $USER;
            $USER->Update($userId, [
                "ACTIVE" => "Y"
            ]);
            return $this->result([
                'accessToken' => $sToken
            ]);
        }
        else {
            $this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_WRONG_CODE"));
        }
		return $this->result();
	}

	/**
	 * Повторный запрос кода верификации
	 * @return string json ответа
	 */
	function getNewCode(): string
	{
        $verificationField = RegistrationField\RegistrationFieldTable::GetRow([
            'filter' => [
                "SLUG" => $this->field_id
            ]
        ]);
        if($verificationField["VERIFICATION_ACTIVE"] == "Y"){
            $arUser = UserTable::GetRow([
                "select" => [
                    "ID"
                ],
                "filter" => [
                    "PERSONAL_PHONE" => $this->value
                ]
            ]);
            $code = Verify\verify::createCode($this->appId, $arUser["ID"], $verificationField["VERIFICATION_TIME"]);
            if(!$this->sendCode($code, $this->value)) {
                // отправить код посетителю
                $this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_SMS_ERROR"));
            }
        }
		return $this->result();
	}

	/**
	 * Загрузка ихображения пользователя
	 * @return string json ответа
	 */
	function userPhoto(): string
	{
		global $USER;
		if (!ApiHelper::authorizeByHeader()) { // проверяем авторизацию
			$this->errors[] = "not_authorized";
			return $this->result();
		}
		if ($_FILES['user_photo']['error'] > 0){
			$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_NO_PHOTO"));
			return $this->result();
		}
		move_uploaded_file($_FILES['user_photo']['tmp_name'], $_SERVER["DOCUMENT_ROOT"].'/upload/tmp/' . $_FILES['user_photo']['name']);
		if (file_exists($_SERVER["DOCUMENT_ROOT"].'/upload/tmp/' . $_FILES['user_photo']['name'])) {
			$arUser = \CUser::GetByID($USER->GetID())->Fetch();
			$arFile = \CFile::MakeFileArray($_SERVER["DOCUMENT_ROOT"].'/upload/tmp/' . $_FILES['user_photo']['name']);
			$user = new \CUser;
			$fields = Array(
				"PERSONAL_PHOTO" => $arFile,
			);
			$user->Update($arUser['ID'], $fields);
			$strError = $user->LAST_ERROR;
			unlink($_SERVER["DOCUMENT_ROOT"].'/upload/tmp/' . $_FILES['user_photo']['name']);
			$sUserPhoto = '';
			return $this->getUserProfile();
		}
		return $this->result();
	}

	/**
	 * Обрабатывает входящий телефон в нружный формат
	 */
	protected function sanitizePhone($phone)
	{
		return $phone;
	}

	/**
	 * проверка корректности email
	 */
	protected function checkMail()
	{
		$res = \CUser::GetList($by = "ID", $order = "ASC", ["=EMAIL" => $this->email]);
		return !($res->SelectedRowsCount());
	}

	/**
	 * проверка корректности телефона
	 * @return bool
	 */
	protected function checkPhone()
	{
		$res = \CUser::GetList($by = "ID", $order = "ASC", ["PERSONAL_PHOTO" => $this->phone]);
		return !($res->SelectedRowsCount());
	}

	static function toLog(string $message, string $severity = 'INFO', string $type = "MOBIUM")
	{
		if ( Option::get("mobium.api", "logger") ) {
			global $USER;
			$userId = $USER->GetID();
			return \CEventLog::Add(array(
					'SEVERITY' => $severity,
					'AUDIT_TYPE_ID' => $type,
					'MODULE_ID' => 'user',
					'DESCRIPTION' => $message,
					'ITEM_ID' => $userId
				)
			);
		}
	}

	/**
	 * Переводит результат в json с нужным статусом
	 * @param bool $arData
	 * @return false|string
	 */
	protected function result($arData = false)
	{
		if (count($this->errors)) {
			$errorData = ['errorMessage' => implode("\n", $this->errors)];
			if ($this->d) {
				$errorData['d'] = $this->d;
			}
			$result = [
				'status' => 'error',
				'data' => $errorData
			];
		} else {
			$result = [
				'status' => 'ok',
			];
			if ($arData) {
				$result['data'] = $arData;
			}
		}
		if ($this->status !== false) {
			$result['status'] = $this->status;
		}
		self::toLog(serialize($result));
		return json_encode($result);
	}
	function applyBonuses() {
		$aOrderInfo = $this->order_info;
		if (isset($aOrderInfo['bonuses_used']) && (float) $aOrderInfo['bonuses_used'] > 0){
			$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_BONUSES_OFF"));
			$this->status=8;
			return false;
		}
		return true;
	}
	function extractOrder($oOrder)
	{
		$aResult = [
			'items'=>[],
			'price'=>(float)$oOrder->getPrice() ?? 0,
			'region'=>[],
		];

		foreach ($oOrder->getBasket()->getBasketItems() as $oBasketItem) {
			$aResult['items'][] = [
				'id'=>$oBasketItem->getProductId(),
				'count'=>$oBasketItem->getQuantity(),
				'price'=>$oBasketItem->getPrice(),
			];
		}
		return $aResult;
	}

	/**
	 * Получаем свойства объекта из data
	 * @param $name string - имя свойства
	 * @return mixed - значение свойства или false
	 */
	public function __get($name)
	{
		return $this->data[$name] ?: false;
	}

	/**
	 * Устанавливаем свйоство, записывая в data
	 * @param $name string - имя свойства
	 * @param $value mixed - новое значение свойства
	 * @return boolean - флаг
	 */
	public function __set($name, $value)
	{
		$this->data[$name] = $value;
	}

	/**
	 * Установлено ли свойство
	 * @param $name
	 * @return bool
	 */
	public function __isset($name)
	{
		return isset($this->data[$name]);
	}

	/**
	 * Уничтожение свойства
	 * @param $name string - имя свойства
	 */
	public function __unset($name)
	{
		unset($this->data[$name]);
	}

	public function __call($name, $args)
	{
		$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_NO_METHOD", ["#name#"=>$name]));
		return $this->result();
	}
}