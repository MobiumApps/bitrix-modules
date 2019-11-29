<?php

use Bitrix\Main\Application;
use \Bitrix\Main\Localization\Loc;

if (!check_bitrix_sessid())
	return;

Loc::loadMessages(__FILE__);
$request = Application::getInstance()->getContext()->getRequest();
global $APPLICATION;
?>
<form action="<?=$APPLICATION->GetCurPage();?>">
	<input type="hidden" name="lang" value="<?=LANGUAGE_ID?>">
	<input type="hidden" name="id" value="mobium.api">
	<input type="hidden" name="install" value="Y">
	<input type="hidden" name="step" value="2">
	<input type="hidden" name="sessid" value="<?=$request["sessid"]?>">
	<p><?=Loc::getMessage("MOBIUM_INSTALL_SETTINGS")?></p>

	<input type="submit" name="" value="<?=Loc::getMessage("SAVE")?>">
	<input type="reset"  name="" value="<?=Loc::getMessage("RESET")?>">
</form>