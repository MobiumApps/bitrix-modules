<?php

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;

defined('ADMIN_MODULE_NAME') or define('ADMIN_MODULE_NAME', 'mobium.api');

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
        $aKeys = ['api_key', 'pool_start', 'pool_total', 'pool_last_card', 'server_url', 'products_iblock', 'offers_iblock'];
        foreach ($aKeys as $sKey){

            if (null !== $request->getPost($sKey)){
                Option::set(
                    ADMIN_MODULE_NAME,
                    $sKey,
                    $request->getPost($sKey)
                );
            }
        }



        CAdminMessage::showMessage(array(
            "MESSAGE" => "Параметры сохранены",
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
            <label for="max_image_size"><?='Ключ API'?>:</label>
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
            <label for="max_image_size"><?='Начальное значение пула вирт. карт'?>:</label>
        <td width="60%">
            <input type="text"
                   size="50"
                   name="pool_start"
                   value="<?=\Bitrix\Main\Text\HtmlFilter::encode(Option::get(ADMIN_MODULE_NAME, "pool_start", ''));?>"
            />
        </td>
    </tr>
    <tr>
        <td width="40%">
            <label for="max_image_size"><?='Всего вирт. карт'?>:</label>
        <td width="60%">
            <input type="text"
                   size="50"
                   name="pool_total"
                   value="<?=\Bitrix\Main\Text\HtmlFilter::encode(Option::get(ADMIN_MODULE_NAME, "pool_total", ''));?>"
            />
        </td>
    </tr>
    <tr>
        <td width="40%">
            <label for="max_image_size"><?='Последняя выданная карта'?>:</label>
        <td width="60%">
            <input type="text"
                   size="50"
                   name="pool_last_card"
                   value="<?=\Bitrix\Main\Text\HtmlFilter::encode(Option::get(ADMIN_MODULE_NAME, "pool_last_card", ''));?>"
            />
        </td>
    </tr>
    <tr>
        <td width="40%">
            <label for="max_image_size"><?='URL сервера'?>:</label>
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
            <label for="max_image_size"><?='ID инфоблока товаров'?>:</label>
        <td width="60%">
            <input type="text"
                   size="50"
                   name="products_iblock"
                   value="<?=\Bitrix\Main\Text\HtmlFilter::encode(Option::get(ADMIN_MODULE_NAME, "products_iblock", '26'));?>"
            />
        </td>
    </tr>
    <tr>
        <td width="40%">
            <label for="max_image_size"><?='ID инфоблока товарных предложений'?>:</label>
        <td width="60%">
            <input type="text"
                   size="50"
                   name="offers_iblock"
                   value="<?=\Bitrix\Main\Text\HtmlFilter::encode(Option::get(ADMIN_MODULE_NAME, "offers_iblock", '27'));?>"
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
