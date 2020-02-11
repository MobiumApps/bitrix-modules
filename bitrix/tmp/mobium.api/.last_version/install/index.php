<?php
IncludeModuleLangFile(__FILE__);

use Bitrix\Main\{
    Application, Config\Option, ModuleManager, Localization, Loader, IO
};
use \Bitrix\Catalog;

Localization\Loc::loadMessages(__FILE__);

Class mobium_api extends CModule
{
    public $MODULE_ID = "mobium.api";

    function __construct()
    {
        include("version.php");
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
                    $this->loadDemo();
                    /**
                     * Запускаем Экспорт каталога
                     */
                    CAgent::AddAgent(
                        'Mobium\Api\ExportYML::run();',
                        'mobium.api',
                        'Y',
                        3600,
                        ConvertTimeStamp(time(), "FULL"),
                        "Y",
                        ConvertTimeStamp(time(), "FULL")
                    );
                    /**
                     * Запускаем Экспорт остатков
                     */
                    CAgent::AddAgent(
                        'Mobium\Api\ExportBalance::run();',
                        'mobium.api',
                        'Y',
                        3600,
                        ConvertTimeStamp(time(), "FULL"),
                        "Y",
                        ConvertTimeStamp(time(), "FULL")
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
            $APPLICATION->ThrowException(Localization\Loc::getMessage("MOBIUM_API_INSTALL_DB_ERROR", ["#errors#" => implode(", ", $this->errors)]));;
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

    function loadDemo() {
        $demoData = [
            ['login', 'N', 'N', 'N', 'text', GetMessage("MOBIUM_API_LOGIN"), 1, 'N', 0, '', '', '', 'N', 1, 'name_field', '', '', GetMessage("MOBIUM_API_PROFILQ"), 'N', 1],
            ['password', 'N', 'Y', 'Y', 'password', GetMessage("MOBIUM_API_PAROLQ"), 5, 'N', 0, '', '', '', 'N', 0, '', '', '', '', 'N', 0],
            ['password_confirm', 'N', 'Y', 'Y', 'password', GetMessage("MOBIUM_API_PODTVERJDENIE_PAROLA"), 6, 'N', 0, '', '', '', 'N', 0, '', '', '', '', 'N', 0],
            ['email', 'N', 'Y', 'Y', 'email', GetMessage("MOBIUM_API_ELEKTRONNAA_POCTA"), 3, 'N', 0, '', '', '', 'Y', 3, 'title_text_field', '', '', GetMessage("MOBIUM_API_ELEKTRONNAA_POCTA1"), 'Y', 0],
            ['phone', 'N', 'Y', 'Y', 'phone', GetMessage("MOBIUM_API_TELEFON"), 4, 'Y', 120, GetMessage("MOBIUM_API_VVEDITE_KOD_IZ"), 'text', 'sms', 'Y', 4, 'title_text_field', '', '', GetMessage("MOBIUM_API_TELEFON1"), 'N', 0],
            ['name', 'Y', 'Y', 'Y', 'text', GetMessage("MOBIUM_API_IMA"), 1, 'N', 0, '', '', '', 'Y', 1, 'name_field', '', '', GetMessage("MOBIUM_API_IMA1"), 'N', 0],
            ['bonuses', 'N', 'N', 'N', '', '', 0, 'N', 0, '', '', '', 'N', 10, 'bonus_field', '', '', GetMessage("MOBIUM_API_PROGRAMMA_LOALQNOSTI"), 'N', 0],
            ['card_code', 'Y', 'Y', 'N', 'text', GetMessage("MOBIUM_API_NOMER_BONUSNOY_KARTY"), 9, 'N', 0, '', '', '', 'Y', 7, 'text_field', '', '', GetMessage("MOBIUM_API_BONUSNAA_KARTA"), 'N', 0],
            ['barcode', 'N', 'N', 'N', '', '', 0, 'N', 0, '', '', '', 'Y', 22, 'barcode_field', '', '', GetMessage("MOBIUM_API_DISKONTNAA_KARTA"), 'N', 0],
            ['sex', 'N', 'Y', 'Y', 'sex_select', GetMessage("MOBIUM_API_POL"), 8, 'N', 0, '', '', '', 'Y', 6, 'title_text_field', '', '', GetMessage("MOBIUM_API_POL1"), 'N', 0],
            ['birthday', 'N', 'Y', 'Y', 'date_picker', GetMessage("MOBIUM_API_DATA_ROJDENIA"), 7, 'N', 0, '', '', '', 'Y', 5, 'title_text_field', '', '', GetMessage("MOBIUM_API_DATA_ROJDENIA1"), 'N', 0],
            ['last_name', 'Y', 'Y', 'Y', 'text', GetMessage("MOBIUM_API_FAMILIA"), 2, 'N', 0, '', '', '', 'Y', 2, 'title_text_field', '', '', GetMessage("MOBIUM_API_FAMILIA1"), 'N', 0]
        ];
        $fields = [
            'SLUG',
            'EDITABLE',
            'REGISTER_ACTIVE',
            'REGISTER_REQUIRED',
            'REGISTER_TYPE',
            'REGISTER_TITLE',
            'REGISTER_SORT',
            'VERIFICATION_ACTIVE',
            'VERIFICATION_TIME',
            'VERIFICATION_TEXT',
            'VERIFICATION_TYPE',
            'VERIFICATION_DRIVER',
            'PROFILE_ACTIVE',
            'PROFILE_SORT',
            'PROFILE_TYPE',
            'PROFILE_ACTION',
            'PROFILE_ACTION_PARAM',
            'PROFILE_TITLE',
            'RESTORE_ACTIVE',
            'RESTORE_SORT'
        ];
        foreach ($demoData as $row) {
            $profile = \Mobium\Api\RegistrationField\RegistrationFieldTable::createObject();
            foreach ($row as $k => $value) {
                $profile->set($fields[$k], $value);
            }
            $profile->save();
        }
        return true;
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
        CopyDirFiles($this->GetPath() . "/install/components", $_SERVER["DOCUMENT_ROOT"] . "/local/components", true, true);
        CopyDirFiles($this->GetPath() . "/install/api", $_SERVER["DOCUMENT_ROOT"] . "/api", true, true);
        if (IO\Directory::isDirectoryExists($path = $this->GetPath() . '/admin')) {
            // CopyDirFiles($this->GetPath()."/install/admin/", $_SERVER["DOCUMENT_ROOT"]."/bitrix/admin"); // если есть файлы админки
            if ($dir = opendir($path)) {
                while (false !== $item = readdir($dir)) {
                    if (in_array($item, $this->exclusionAdminFiles))
                        continue;
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/' . $this->MODULE_ID . '_' . $item,
                        '<' . '? require($_SERVER["DOCUMENT_ROOT"]."' . $this->GetPath(true) . '/admin/' . $item . '");?' . '>');
                }
                closedir($dir);
            }
        }
        return true;
    }

    function UnInstallFiles()
    {
        IO\Directory::deleteDirectory($_SERVER["DOCUMENT_ROOT"] . '/local/components/mobium/');
        if (IO\Directory::isDirectoryExists($path = $this->GetPath() . '/admin')) {
            // DeleteDirFiles($_SERVER["DOCUMENT_ROOT"] . $this->GetPath() . '/install/admin/', $_SERVER["DOCUMENT_ROOT"] . '/bitrix/admin');
            if ($dir = opendir($path)) {
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
            return str_ireplace(Application::getDocumentRoot(), '', $parentDir);
        } else {
            return $parentDir;
        }
    }

    protected function checkDependencies()
    {
        global $APPLICATION;
        $arModules = require($this->GetPath() . "/modules.php");
        foreach ($arModules as $module) {
            if (!Loader::includeModule($module)) {
                $result[] = $module;
            }
        }
        if (!empty($result)) {
            $APPLICATION->ThrowException(Localization\Loc::getMessage("MOBIUM_API_INSTALL_MODULE_REQUIRE", ['#modules#' => implode(", ", $result)]));
        }
        return true;
    }

    protected function SetOptions()
    {
        Loader::IncludeModule('catalog');
        $res = Catalog\CatalogIblockTable::GetList();
        $arProductsIds = $arOffersIds = [];
        while ($arCatalog = $res->Fetch()) {
            if (!$arCatalog['PRODUCT_IBLOCK_ID']) $arProductsIds[] = $arCatalog['IBLOCK_ID'];
            else $arOffersIds[$arCatalog['PRODUCT_IBLOCK_ID']] = $arCatalog['IBLOCK_ID'];
        }
        foreach ($arProductsIds as $pId) {
            $fId = $arOffersIds[$pId];
            if ($fId) break;
        }
        Option::set($this->MODULE_ID, "products_iblock", $pId);
        Option::set($this->MODULE_ID, "offers_iblock", $fId);
    }
}
