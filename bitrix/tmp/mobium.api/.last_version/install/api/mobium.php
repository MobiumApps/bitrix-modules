<?php
use Bitrix\Main\{Context,Loader};

require_once ($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

$request = Context::getCurrent()->getRequest();
$sMethod = trim($request['method']);
header('Cache-Control: private, max-age=0, no-cache');
header('Content-Type: application/json');

Loader::includeModule("mobium.api");
$main = new Mobium\Api\Main;
if ($request['limit'] && $request['offset']) {
	echo $main->$sMethod($request['limit'], $request['offset']);
}
else {
	echo $main->$sMethod();
}
