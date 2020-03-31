<?php
IncludeModuleLangFile(__FILE__);

use Bitrix\Main\{Application, Config\Option, ModuleManager, Localization, Loader, IO};
use \Bitrix\Catalog;

Localization\Loc::loadMessages(__FILE__);

Class mobium_api extends CModule
{
	function __construct()
	{
		$arModuleVersion = require("version.php");
		$this->MODULE_ID = "mobium.api";
		$this->MODULE_VERSION = $arModuleVersion["VERSION"];
		$this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
		$this->MODULE_NAME = "API Mobium";
		$this->MODULE_DESCRIPTION = Localization\Loc::getMessage('MOBIUM_API_MODULE_DESCR');
		$this->PARTNER_NAME = "Mobium";
		$this->PARTNER_URI = "https://mobiumapps.com";
		$this->MODULE_GROUP_RIGHTS = 'Y';
		$this->exclusionAdminFiles = [
			'..',
			'.',
			'menu.php'
		];
	}

	function DoInstall()
	{
		global $APPLICATION;
		if (!$this->isVersionD7()) {
			$APPLICATION->ThrowException(Localization\Loc::getMessage("MOBIUM_API_INSTALL_D7VERSION_ERROR"));
		}
		if (version_compare("7.0.0", phpversion()) > 0) {
			$APPLICATION->ThrowException(Localization\Loc::getMessage("MOBIUM_API_INSTALL_PHPVERSION_ERROR"));
		}
		$this->checkDependencies();
		$context = Application::getInstance()->getContext();
		$request = $context->getRequest();
		switch ($request['step']) {
			case 2:
				$APPLICATION->IncludeAdminFile(Localization\Loc::getMessage("MOBIUM_API_step2_TITLE"),
					$this->GetPath() . "/install/step2.php");
				break;
			case 1:
				$APPLICATION->IncludeAdminFile(Localization\Loc::getMessage("MOBIUM_API_step1_TITLE"),
					$this->GetPath() . "/install/step1.php");
			default:
				$this->InstallDB();
				$this->InstallEvents();
				$this->InstallFiles();
				$this->SetOptions();
				if (!$ex = $APPLICATION->GetException()) {
					ModuleManager::registerModule($this->MODULE_ID);
					Loader::includeModule($this->MODULE_ID);
					/**
					 * Запускаем Экспорт каталога
					 */
					CAgent::AddAgent(
						'Mobium\Api\ExportYML::run();',
						'mobium.api',
						'Y',
						3600,
						ConvertTimeStamp(time(),"FULL"),
						"Y",
						ConvertTimeStamp(time(),"FULL")
					);
					/**
					 * Запускаем Экспорт остатков
					 */
					CAgent::AddAgent(
						'Mobium\Api\ExportBalance::run();',
						'mobium.api',
						'Y',
						3600,
						ConvertTimeStamp(time(),"FULL"),
						"Y",
						ConvertTimeStamp(time(),"FULL")
					);
				}
				$APPLICATION->IncludeAdminFile(Localization\Loc::getMessage("MOBIUM_API_INSTALL_TITLE"),
					$this->GetPath() . "/install/step.php");
		}
		return true;
	}

	function DoUninstall()
	{
		$this->UnInstallDB();
		$this->UnInstallEvents();
		$this->UnInstallFiles();
		CAgent::RemoveModuleAgents($this->MODULE_ID);
		ModuleManager::unRegisterModule($this->MODULE_ID);
		return true;
	}

	function InstallDB()
	{
		global $DB, $APPLICATION;
		$this->errors = false;
		$this->errors = $DB->RunSQLBatch($this->GetPath() . "/install/db/mysql/install.sql");
		if ($this->errors) {
			$APPLICATION->ThrowException(Localization\Loc::getMessage("MOBIUM_API_INSTALL_DB_ERROR",["#errors#" => implode(", ",$this->errors)])); ;
		}
	}

	function UnInstallDB()
	{
		global $DB;
		$this->errors = false;
		$this->errors = $DB->RunSQLBatch($this->GetPath() . "/install/db/mysql/uninstall.sql");
		if (!$this->errors) {
			return true;
		} else {
			return $this->errors;
		}
	}

	function InstallEvents()
	{
		return true;
	}

	function UnInstallEvents()
	{
		return true;
	}

	function InstallFiles()
	{
		CopyDirFiles($this->GetPath()."/install/components", $_SERVER["DOCUMENT_ROOT"]."/local/components", true, true);
		if (IO\Directory::isDirectoryExists($path = $this->GetPath() . '/admin')) {
			// CopyDirFiles($this->GetPath()."/install/admin/", $_SERVER["DOCUMENT_ROOT"]."/bitrix/admin"); // если есть файлы админки
			if ($dir = opendir($path)) {
				while (false !== $item = readdir($dir)) {
					if (in_array($item, $this->exclusionAdminFiles))
						continue;
					file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/' . $this->MODULE_ID.'_'.$item,
						'<'.'? require($_SERVER["DOCUMENT_ROOT"]."'.$this->GetPath(true).'/admin/'.$item.'");?'.'>');
				}
				closedir($dir);
			}
		}
		return true;
	}

	function UnInstallFiles()
	{
		IO\Directory::deleteDirectory($_SERVER["DOCUMENT_ROOT"] . '/local/components/mobium/');
		if(IO\Directory::isDirectoryExists($path = $this->GetPath().'/admin')) {
			// DeleteDirFiles($_SERVER["DOCUMENT_ROOT"] . $this->GetPath() . '/install/admin/', $_SERVER["DOCUMENT_ROOT"] . '/bitrix/admin');
			if($dir = opendir($path)) {
				while (false !== $item = readdir($dir)) {
					if (in_array($item, $this->exclusionAdminFiles)) continue;
					IO\File::deleteFile($_SERVER["DOCUMENT_ROOT"] . '/bitrix/admin/' . $this->MODULE_ID . '_' . $item);
				}
				closedir($dir);
			}
		}
		return true;
	}

	function isVersionD7()
	{
		return CheckVersion(ModuleManager::getVersion('main'), '14.00.00');
	}

	function GetPath($notDocumentRoot = false)
	{
		$parentDir = dirname(__DIR__);
		if ($notDocumentRoot) {
			return str_ireplace(Application::getDocumentRoot(),'', $parentDir);
		}
		else {
			return $parentDir;
		}
	}

	protected function checkDependencies()
	{
		global $APPLICATION;
		$arModules = require ($this->GetPath()."/modules.php");
		foreach ($arModules as $module){
			if (!Loader::includeModule($module)){
				$result[] = $module;
			}
		}
		if (!empty($result)){
			$APPLICATION->ThrowException(Localization\Loc::getMessage("MOBIUM_API_INSTALL_MODULE_REQUIRE", ['#modules#'=>implode(", ",$result)]));
		}
		return true;
	}
	protected function SetOptions ()
	{
		Loader::IncludeModule('catalog');
		$res = Catalog\CatalogIblockTable::GetList();
		$arProductsIds = $arOffersIds = [];
		while( $arCatalog = $res->Fetch()) {
			if(!$arCatalog['PRODUCT_IBLOCK_ID'])	$arProductsIds[] = $arCatalog['IBLOCK_ID'];
			else $arOffersIds[$arCatalog['PRODUCT_IBLOCK_ID']] = $arCatalog['IBLOCK_ID'];
		}
		foreach($arProductsIds as $pId){
			$fId = $arOffersIds[$pId];
			if($fId) break;
		}
		Option::set($this->MODULE_ID, "products_iblock", $pId);
		Option::set($this->MODULE_ID, "offers_iblock", $fId);
	}
}
