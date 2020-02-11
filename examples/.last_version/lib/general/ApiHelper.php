<?php
namespace Mobium\Api;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Catalog;
use Bitrix\Highloadblock as HL;

/**
 * Class ApiHelper - Вызывает методы API со стороны битрикса
 * @package Mobium\Api
 */
class ApiHelper extends Helper
{
	/**
	 * @var HttpClient - Объект-обертка над CURL
	 */
	protected $client;
	/**
	 * @var string - URL к API
	 */
	protected $url;
	/**
	 * @var string - авторизационный токен
	 */
	protected $token;

	/**
	 * ApiHelper constructor.
	 * @param string $path - путь к методам API, без названия метода
	 */
	public function __construct(string $path = "api")
	{
		$this->url = Option::get('mobium.api', 'server_url', 'https://core.mobium.pro') . "/" . trim($path,"/"). "/";
		$this->token = Option::get('mobium.api', "api_key", '');
		$this->client = new HttpClient();
	}

	/**
	 * Если метод не объявлен, значит надо запросить у API
	 * @param str $name - имя отсутствующего метода
	 * @param array $arguments - массив параметров
	 * @return array - данные из API
	 */
	public function __call (string $name, array $arguments=[]): array
	{
		if(count($arguments)) {
			$jsonData = $this->post($name, $arguments);
		}
		else {
			$jsonData = $this->get($name);
		}
		$aData = json_decode($jsonData, true);
		if (JSON_ERROR_NONE === json_last_error()) {
			return $aData;
		}
	}

	/**
	 * Отправляем в API GET запрос
	 * @param str $method - Имя метода
	 * @return array необходимые данные
	 */
	public function get (string $method)
	{
		$this->client->setHeader('Authorization', $this->token);
		return $this->client->get($this->url.$method);
	}

	/**
	 * Отправляем в API POST запрос
	 * @param str $method - Имя метода
	 * @param array $arData - параметры для запроса
	 * @return array необходимые данные
	 */
	public function post (string $method, array $arData)
	{
		$this->client->setHeader('Authorization', $this->token);
		return $this->client->post($this->url.$method, $arData);
	}
	public function getCurrentUserCardData($userId) {
		// vbcherepanov.bonus
		if(Loader::includeModule('vbcherepanov.bonus')) {
			$res = \ITRound\Vbchbbonus\BonusTable::GetList([
				'filter' => [
					'USERID' => $userId,
					'ACTIVE' => 'Y'
				]
			]);
			$bonuses = 0;
			while($row = $res->Fetch()) {
				$bonuses+=$row["BONUS"];
			}
			$arCard["BONUS_POINTS"] = $bonuses;
			$arCard["BONUS_AVAILABLE"] = 1;
			return $arCard;
		}
		// DEFAULT
		$row = \CSaleUserAccount::GetByUserID ($userId,"RUB");
		$arCard["BONUS_POINTS"] = $row["CURRENT_BUDGET"];
		$arCard["BONUS_AVAILABLE"] = 1;
		return $arCard;
		// Белый дом
		$arUser = \CUser::GetByID($userId)->Fetch();
		if($arUser["PERSONAL_PHONE"]){
			Loader::includeModule("highloadblock");
			$phone = str_replace("+7","", $arUser["PERSONAL_PHONE"]);
			$phone =  preg_replace("/[^0-9]/", '', $phone);
			$hlblock = HL\HighloadBlockTable::getRow([
				'filter' => [
					'TABLE_NAME' => "b_bddiskontnyekarty"
				]
			]);
			if(!$hlblock) return false;
			$Karty = HL\HighloadBlockTable::compileEntity($hlblock)->getDataClass();
			$row = $Karty::GetRow([
				"filter" => [
					"UF_TELEFON" => substr($phone,-10)
				]
			]);
			if (@$row["UF_BONUSOVAKTIVNYKH"]>0) {
				return [
					"BONUS" => true,
					"BONUS_POINTS" => $row["UF_BONUSOVAKTIVNYKH"]
				];
			}
		}
		return false;
	}
	public static function getPriceTypes() {
		Loader::includeModule("catalog");
		$rsGroup = Catalog\GroupTable::getList();
		$arResult = [];
		while($arGroup=$rsGroup->fetch())
		{
			$arResult[] = $arGroup["NAME"];
		}
		return $arResult;
	}
	public static function getPaymentTypes() {
		return [
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
	}
	public static function getDiscount() {
		return 0;
	}
}
