<?php
/**
 * @var array $arResult
 * @var array $arParams
 */
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
header('Content-Type: application/json');
echo json_encode($arResult, JSON_PRETTY_PRINT);