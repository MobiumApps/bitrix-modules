<?php
/**
 * @var array $arResult
 * @var array $arParams
 */
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
header("Content-Type: application/json; charset=utf-8");
echo json_encode($arResult);