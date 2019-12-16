<?php

namespace Mobium\Api;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class Main implements iMain
{

	protected $errors;
	/**
	 * @var array - массив свойств
	 */
	protected $data;
	protected $d;

	public function __construct()
	{
		mb_detect_order("CP1251,CP1252,ASCII,UTF-8");
		$this->errors = [];
		$this->d = false;

		$aInputData = json_decode(file_get_contents('php://input'), true);
		if (JSON_ERROR_NONE === json_last_error()) {
			$this->data = $aInputData;
		}

		self::toLog(json_encode(getallheaders()));
		self::toLog(json_encode($this->data));
	}
	function iconvb($text){
		if (LANG_CHARSET != "UTF-8"){
			return iconv("UTF-8" , LANG_CHARSET, $text);
		}
		return $text;
	}
	function iconv(string $text): string
	{
		return iconv(mb_detect_encoding($text, mb_detect_order()), "UTF-8", $text);
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
			$aBonusField=[];
			$aDisciuntField=[];
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
					if (false !== $aCardData && $aCardData['BONUS']) {
                        // $aTempField['id'] = $aField['SLUG'];
						// $aTempField['title'] = $this->iconv($aField['PROFILE_TITLE']);
						// $aTempField['value'] = (string) $aCardData['BONUS_POINTS'];
                        $aBonusField['amount'] = (float) $aCardData['BONUS_POINTS'];
                        $aBonusField['available_proportion'] = 0.5; // todo: GET available proportion from DB
					} else {
						$bAddToResult = false;
					}
					break;
				case 'barcode_field':
					$aTempField['image'] = ApiHelper::getProfileFieldValue($aField['SLUG']);
					$aTempField['id'] = $aField['SLUG'];
					$oUri = new \Bitrix\Main\Web\Uri('https://' . $_SERVER['HTTP_HOST'] . '/api/mobium.php?method=generateBarcode&userId=' . $USER->GetId());
					$aTempField['image'] = $oUri->getUri();
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
		if($aBonusField['amount']) {
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
		$aFilter = [
			'EDITABLE' => 'Y',
			'PROFILE_ACTIVE' => 'Y'
		];
		$aOrder = [
			'PROFILE_SORT' => 'ASC'
		];
		$aFields = [];
		$oResult = RegistrationField\RegistrationFieldTable::getList([
			'filter' => $aFilter,
			'order' => $aOrder,
		]);
		while ($aField = $oResult->fetch()) {
			if (isset($this->fields[$aField['SLUG']])) {
				if (!($bRes = ApiHelper::setProfileFieldValue($aField['SLUG'],
					$this->fields[$aField['SLUG']]['value']))) {
					$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_FIELD_ERROR",[
                        "#field#" => $aField['PROFILE_TITLE']
                    ]));
				}
			}
		}
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
			"ACTIVE"            => "Y",
			"PASSWORD"          => $this->password,
			"CONFIRM_PASSWORD"  => $this->password,
		);
		if (isset($this->birthday)) {
			$sInputFormat = $sOutputFormat = 'd.m.Y';
			$oDateTime = \DateTime::createFromFormat($sInputFormat, $this->birthday);
			if (false !== $oDateTime) {
				$arFields['PERSONAL_BIRTHDAY'] = $oDateTime->format($sOutputFormat);
			}
		} else {
			$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_BDAY_REQUIRE"));
			\COption::SetOptionString("main", "captcha_registration", "Y");
			return $this->result();
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
				$USER->Update($ID, [
					'ACTIVE' => 'N'
				]);
				return $this->result();
			}
			/*
			if ($profileId = ApiHelper::createUserAppProfile($USER)) {
				$USER->Update($ID, [
					// 'UF_APP_PROFILE' => $profileId,
					'ACTIVE' => 'Y'
				]);
			}
			*/
			return $this->result(['accessToken' => $sToken]);
		}
		else {
			$this->errors[]=$this->iconv($obUser->LAST_ERROR);
		}
		return $this->result();
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
	function userOrderHistory(): string
	{
		$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_ORDERS_OFF"));
		return $this->result();
	}

	/**
	 * Commit order
	 * @return string json ответа
	 */
	function commitOrder(): string
	{
		$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_ORDERS_OFF"));
		return $this->result();
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
		$this->errors[] = $this->iconv(Loc::getMessage("MOBIUM_API_MODULE_MAIN_ORDERS_OFF"));
		return $this->result();
	}

	/**
	 * Генерирует Bar код
	 * @return string json ответа
	 */
	function generateBarcode(): string
	{
		$oGenerator = new \Picqer\Barcode\BarcodeGeneratorPNG();
		$sUserId = isset($_GET['userId']) ? $_GET['userId'] : null;
		$rUser = \CUser::GetByID($sUserId)->Fetch();
		if (!$rUser) {
			exit();
		}
		/**@var CUser $USER * */
		global $USER;
		$USER->Authorize((int)$sUserId);
		$aCardData = ApiHelper::getCurrentUserCardData($sUserId);
		$sContent = $oGenerator->getBarcode($aCardData['UF_CODE'], $oGenerator::TYPE_CODE_128, 2, 70);
	}

	/**
	 * Верификация поля
	 * @return string json ответа
	 */
	function verifyData(): string
	{
		$this->errors[] = "error!";
		return $this->result();
	}

	/**
	 * Повторный запрос кода верификации
	 * @return string json ответа
	 */
	function getNewCode(): string
	{
		$this->errors[] = "error!";
		return $this->result();
	}

	/**
	 * Загрузка ихображения пользователя
	 * @return string json ответа
	 */
	function userPhoto(): string
	{
		$this->errors[] = "error!";
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
		$res = \CUser::GetList($by = "ID", $order = "ASC", ["EMAIL" => $this->email]);
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

		if (!count($this->errors)) {
			$result = [
				'status' => 'ok',
			];
			if ($arData) {
				$result['data'] = $arData;
			}
		} else {
			$errorData = ['errorMessage' => implode("\n", $this->errors)];
			if ($this->d) {
				$errorData['d'] = $this->d;
			}
			$result = [
				'status' => 'error',
				'data' => $errorData
			];
		}
		self::toLog(serialize($result));
		return json_encode($result);
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