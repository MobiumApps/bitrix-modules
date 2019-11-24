<?php
/**
 * @var array $arParams
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
\Bitrix\Main\Loader::includeModule('mobium.api');

/** @see https://dev.1c-bitrix.ru/api_help/main/reference/cagent/addagent.php */
$this->arResult = CAgent::AddAgent(
    'Mobium\Api\ExportYML::run();',
    'mobium.api',
    'N',
    86400
);

$this->arResult = CAgent::AddAgent(
    'Mobium\Api\ExportBalance::run();',
    'mobium.api',
    'N',
    86400
);

$this->IncludeComponentTemplate();