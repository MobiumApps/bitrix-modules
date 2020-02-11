<?php

namespace Mobium\Api\Verify;

use Bitrix\Main\Type\DateTime;

class verify extends EO_Verify
{
	const lifeTime = 180;
	/**
	 * Возвращает сущность по id в виде объекта
	 * @param $id - id предложения
	 * @return |null
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\ObjectPropertyException
	 * @throws \Bitrix\Main\SystemException
	 */
	public function getById($id)
	{
		return VerifyTable::getById($id)->fetchObject();
	}

	/**
	 * Возвращает сущность по id в виде массива
	 * @param $id - id предложения
	 * @return array|false
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\ObjectPropertyException
	 * @throws \Bitrix\Main\SystemException
	 */
	public function arById($id)
	{
		return VerifyTable::getById($id)->fetch();
	}

	public static function checkCode($code, $appId) {
		$now = new DateTime();
		$verify = VerifyTable::GetList([
			'filter' => [
				"=CODE" => $code,
                "=APP_ID" => $appId
			]
		])->fetchObject();
		$sec = $verify->getLifetime();
		if ($verify->getCreatedAt()->add("T{$sec}S") >= $now){
			return $verify->getUserId();
		}
		return false;
	}

	public static function createCode($appId, $userId, $time = self::LifeTime) {
		$verify = new self();
		$code = randString(6,["0123456789"]);
		$verify->setCode($code);
		$verify->setAppId($appId);
		$verify->setUserId($userId);
		$verify->setLifetime($time);
		$verify->save();
		return $code;
	}
}