<?php

namespace Mobium\Api;
use \Bitrix\Main\Config\Option;

class Helper
{
	public static function getProductsProps()
	{
		$iIblockId = Option::get('mobium.api', 'products_iblock', '0');
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
}