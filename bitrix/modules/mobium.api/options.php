<?php

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Mobium\Api\ApiHelper;

defined('ADMIN_MODULE_NAME') or define('ADMIN_MODULE_NAME', 'mobium.api');
Loader::includeModule('mobium.api');
if (!$USER->isAdmin()) {
    $APPLICATION->authForm('Nope');
}

$app = Application::getInstance();
$context = $app->getContext();
$request = $context->getRequest();

Loc::loadMessages($context->getServer()->getDocumentRoot()."/bitrix/modules/main/options.php");
Loc::loadMessages(__FILE__);

$tabControl = new CAdminTabControl("tabControl", array(
    array(
        "DIV" => "edit1",
        "TAB" => Loc::getMessage("MAIN_TAB_SET"),
        "TITLE" => Loc::getMessage("MAIN_TAB_TITLE_SET"),
    ),
));

if ((!empty($save) || !empty($restore)) && $request->isPost() && check_bitrix_sessid()) {
    if (!empty($restore)) {
        Option::delete(ADMIN_MODULE_NAME);
        CAdminMessage::showMessage(array(
            "MESSAGE" => Loc::getMessage("REFERENCES_OPTIONS_RESTORED"),
            "TYPE" => "OK",
        ));
    } elseif ($request->isPost() ) {
        $aKeys = ['api_key', 'server_url', 'products_iblock', 'offers_iblock', 'filter_name', 'catalog_group', 'logger'];
        foreach ($aKeys as $sKey){

            if (null !== $request->getPost($sKey)){
                Option::set(
                    ADMIN_MODULE_NAME,
                    $sKey,
                    $request->getPost($sKey)
                );
            }
            if ($sKey == "logger" && null === $request->getPost($sKey)) {
				Option::set(
					ADMIN_MODULE_NAME,
					$sKey,
					0
				);
            }
        }

        CAdminMessage::showMessage(array(
            "MESSAGE" => Loc::getMessage("MOBIUM_API_MODULE_OPTIONS_SAVED"),
            "TYPE" => "OK",
        ));
    } else {
        CAdminMessage::showMessage(Loc::getMessage("REFERENCES_INVALID_VALUE"));
    }
}

$tabControl->begin();
?>

<form method="post" action="<?=sprintf('%s?mid=%s&lang=%s', $request->getRequestedPage(), urlencode($mid), LANGUAGE_ID)?>">
    <?php
    echo bitrix_sessid_post();
    $tabControl->beginNextTab();
    ?>
    <tr>
        <td width="40%">
            <label for="api_key"><?=Loc::getMessage("MOBIUM_API_MODULE_OPTIONS_api_key_OPTION")?>:</label>
        <td width="60%">
            <input type="text"
                   size="50"
                   name="api_key"
                   value="<?=\Bitrix\Main\Text\HtmlFilter::encode(Option::get(ADMIN_MODULE_NAME, "api_key", ''));?>"
            />
        </td>
    </tr>
    <tr>
        <td width="40%">
            <label for="server_url"><?=Loc::getMessage("MOBIUM_API_MODULE_OPTIONS_server_url_OPTION")?>:</label>
        <td width="60%">
            <input type="text"
                   size="50"
                   name="server_url"
                   value="<?=\Bitrix\Main\Text\HtmlFilter::encode(Option::get(ADMIN_MODULE_NAME, "server_url", 'https://core.mobium.pro'));?>"
            />
        </td>
    </tr>
    <tr>
        <td width="40%">
            <label for="products_iblock"><?=Loc::getMessage("MOBIUM_API_MODULE_OPTIONS_products_iblock_OPTION")?>:</label>
        <td width="60%">
            <input type="text"
                   size="50"
                   name="products_iblock"
                   value="<?=\Bitrix\Main\Text\HtmlFilter::encode(Option::get(ADMIN_MODULE_NAME, "products_iblock", ''));?>"
            />
        </td>
    </tr>
    <tr>
        <td width="40%">
            <label for="offers_iblock"><?=Loc::getMessage("MOBIUM_API_MODULE_OPTIONS_offers_iblock_OPTION")?>:</label>
        <td width="60%">
            <input type="text"
                   size="50"
                   name="offers_iblock"
                   value="<?=\Bitrix\Main\Text\HtmlFilter::encode(Option::get(ADMIN_MODULE_NAME, "offers_iblock", ''));?>"
            />
        </td>
    </tr>
    <tr>
	    <?
		$curr = Option::get(ADMIN_MODULE_NAME, "catalog_group", 0);
	    ?>
	    <td width="40%">
		    <label for="catalog_group"><?=Loc::getMessage("MOBIUM_API_MODULE_OPTIONS_catalog_group_OPTION")?>:</label>
	    <td width="60%">
		    <select name="catalog_group">
			    <? foreach (ApiHelper::getPriceTypes() as $price) {
			    	?>
				    <option value="<?=@$price["ID"]?>" <? if($curr==@$price["ID"]){print " selected='selected'";} ?>><?=@$price["NAME"]?></option>
			    <?}?>
		    </select>
	    </td>
    </tr>
    <tr>
        <td width="40%">
            <label for="filter_name"><?=Loc::getMessage("MOBIUM_API_MODULE_OPTIONS_filter_name_OPTION")?>:</label>
        <td width="60%">
            <input type="text"
                   size="50"
                   name="filter_name"
                   value="<?=\Bitrix\Main\Text\HtmlFilter::encode(Option::get(ADMIN_MODULE_NAME, "filter_name", ''));?>"
            />
        </td>
    </tr>
    <tr>
        <td width="40%">
            <label for="logger"><?=Loc::getMessage("MOBIUM_API_MODULE_OPTIONS_logger_OPTION")?>:</label>
        <td width="60%">
            <input type="checkbox"
				<? if(Option::get(ADMIN_MODULE_NAME, "logger", '0') > 0 ) { echo " checked"; }?>
                   name="logger"
                   value="1"
            />
        </td>
    </tr>

    <?php
    $tabControl->buttons();
    ?>
    <input type="submit"
           name="save"
           value="<?=Loc::getMessage("MAIN_SAVE") ?>"
           title="<?=Loc::getMessage("MAIN_OPT_SAVE_TITLE") ?>"
           class="adm-btn-save"
    />
    <input type="submit"
           name="restore"
           title="<?=Loc::getMessage("MAIN_HINT_RESTORE_DEFAULTS") ?>"
           onclick="return confirm('<?= AddSlashes(GetMessage("MAIN_HINT_RESTORE_DEFAULTS_WARNING")) ?>')"
           value="<?=Loc::getMessage("MAIN_RESTORE_DEFAULTS") ?>"
    />
    <?php
    $tabControl->end();
    ?>
</form>
