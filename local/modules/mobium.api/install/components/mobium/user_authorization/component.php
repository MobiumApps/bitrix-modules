<?php
use \Mobium\Api\AccessToken;
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
\Bitrix\Main\Loader::includeModule('mobium.api');
if ($_SERVER['REQUEST_METHOD'] == 'DELETE'){
    $sHeader = $_SERVER['REMOTE_USER'] ?? '';
    if (!empty($sHeader)){
        $oResult = AccessToken\AccessTokenTable::getList([
            'filter'=>[
                'BODY'=>$sHeader,
                'TYPE'=>'auth'
            ]
        ]);
        $aResult = $oResult->fetchAll();
        if (count($aResult) === 1){
            $aResult = $aResult[0];
            $oResult = AccessToken\AccessTokenTable::delete($aResult['ID']);
            if ($oResult->isSuccess()){
                $this->arResult = [
                    'status'=>'ok'
                ];
            } else {
                $this->arResult = [
                    'status'=>'error',
                    'data'=>[
                        'errorMessage'=>'Ошибка при работе с токеном'
                    ]
                ];
            }
        } else {
            $this->arResult = [
                'status'=>'error',
                'data'=>[
                    'errorMessage'=>'Токен не валидный'
                ],
            ];
        }
    } else {
        $this->arResult = [
            'status'=>'error',
            'data'=>[
                'errorMessage'=>'Токен не передан'
            ]
        ];
    }
    $this->IncludeComponentTemplate();
}
if ($_SERVER["REQUEST_METHOD"] == "POST"){

    $aInputData = json_decode(file_get_contents('php://input'), true);
    if (JSON_ERROR_NONE !== json_last_error()){
        exit();
    }
    if (isset($aInputData['appId']) && !ctype_digit($aInputData['appId'])){
        exitWithError($this, 'Ошибка в запросе.');
    }
    if ($arParams['method'] === 'auth_by_code'){
        $sPhone = \Bethowen\Helpers\Other::sanitizePhone($aInputData['phone']);
        if (strlen($sPhone) !== 11){
            exitWithError($this, 'Некорректный номер телефона. '.$sPhone.' '.strlen($sPhone));
        }
        $iUserId = \Bethowen\Helpers\User::getDoubledUserID($sPhone);
        if ($iUserId > 0) {
            if (\Bethowen\Services\LoymaxDsqDecorator::existPhone($sPhone)) { // Пользователь в лоймаксе
                $aLoymaxResult = \Bethowen\Services\LoymaxDsq::resetPassword($sPhone);
                if ($aLoymaxResult['result']['state'] !== 'Success') {
                    exitWithError($this, $aLoymaxResult['result']['message']);
                }
            } else { // Отправляем через битрикс
                $sCode = \Bethowen\Helpers\Other::makeNumbersPass(6);
                \Bethowen\Helpers\User::setAuthCode($iUserId, $sCode);
                \Bethowen\Helpers\Sms::send($sPhone, 'Код авторизации: ' . $sCode, 'Bethowen', 1);
            }
            $this->arResult = [
                'status' => 'ok'
            ];
        } else {
            exitWithError($this, 'Пользователь не найден.');
        }
    }
    if ($arParams['method'] === 'verify_code') {
        $sPhone = \Bethowen\Helpers\Other::sanitizePhone($aInputData['phone']);
        if (strlen($sPhone) !== 11){
            exitWithError($this, 'Некорректный номер телефона.');
        }
        $sCode = $aInputData['code'];
        $aLoymaxResult = \Bethowen\Services\LoymaxDsq::setNewPassword($sPhone, $sCode);
        if ($aLoymaxResult['result']['state'] === 'Success') {
            $iUserId = \Bethowen\Helpers\User::getDoubledUserID($sPhone);
            if ($iUserId > 0){
                $dCard =  \Bethowen\Services\LoymaxDsqDecorator::getCardByPhone($sPhone);
                \Bethowen\Helpers\User::addLoymaxCardToUser($iUserId, $dCard);
                /**@var CUser $USER */
                global $USER;
                $USER->Authorize($iUserId);
                try {
                    $sToken = \Mobium\Api\ApiHelper::generateAccessToken($USER->GetID());
                } catch (\Exception $e) {
                    exitWithError($this, 'Ошибка создания токена.');
                }
                if ($sToken){
                    \Mobium\Api\ApiHelper::deleteAuthData($aInputData['appId']);
                    $this->arResult = [
                        'status' => 'ok',
                        'data' => [
                            'accessToken' => $sToken
                        ]
                    ];
                } else {
                    exitWithError($this, 'Ошибка создания токена.');
                }
            } else {
                exitWithError($this, 'Пользователь не найден.');
            }
        } else {
            $sMessage = 'Неверный код.';
            if (isset($aLoymaxResult['result']['validationErrors'])){
                $sMessage = implode("\n", array_map(function($aItem){
                    return implode(', ', $aItem);
                }, array_column($aLoymaxResult['result']['validationErrors'], 'errorMessages')));
            } elseif (isset($aLoymaxResult['result']['message'])) {
                $sMessage = $aLoymaxResult['result']['message'];
            }
            exitWithError($this, $sMessage);
        }
    }
    if ($arParams['method'] === 'auth_by_login'){
        if (isset($aInputData['recaptchaToken'])){
            try {
                \Mobium\Api\ApiHelper::checkRecaptcha($aInputData['recaptchaToken'], $aInputData['platform']);
            } catch (\Exception $e) {
                exitWithError($this, 'Капча введена не правильно.');
            }
        } else {
            exitWithError($this, 'Пожалуйста, обновите приложение, что бы воспользоваться личным кабинетом.');
        }
        try {
            $aAppData = \Mobium\Api\ApiHelper::getAuthData($aInputData['appId']);
        } catch (\Exception $e) {
            exitWithError($this, 'Ошибка в БД.');
        }
        if ($aAppData['DATA']['tries'] >= 5){
            $fDiff = microtime(true) - (float) $aAppData['DATA']['last_try'];
            if (($aAppData['DATA']['tries'] <= 10 && $fDiff <= 10) || ($aAppData['DATA']['tries'] > 10 && $fDiff <= 600)){
                if (!\Mobium\Api\ApiHelper::saveAppData($aAppData)){
                    exitWithError($this, 'Ошибка в БД.');
                }
                $fNextTry = $aAppData['DATA']['tries'] <= 10 ? 10-$fDiff : 600 - $fDiff;
                $iMins = floor($fNextTry / 60.0);
                $iSecs = $fNextTry % 60;
                exitWithError(
                    $this,
                    'Превышен лимит запросов. Повторите попытку через '.($iMins > 0 ? $iMins.' мин. и ' : '').$iSecs.' сек.'
                );
            }
        }
        $aAppData['DATA']['tries']++;
        $aAppData['DATA']['last_try'] = microtime(true);
        if (!\Mobium\Api\ApiHelper::saveAppData($aAppData)){
            exitWithError($this, 'Ошибка в БД.');
        }
        /**@var CUser $USER */
        global $USER;
        if (!is_object($USER)){
            $USER = new CUser();
        }
        if (!isset($aInputData['login'], $aInputData['password']) || empty($aInputData['login']) || empty($aInputData['password'])){
            exitWithError($this, 'Логин или пароль не могут быть пустыми.');
        }
        $sLogin = $aInputData['login'];
        if (!($aInputData['login'] = filter_var($sLogin, FILTER_VALIDATE_EMAIL))){
            $aInputData['login'] = \Bethowen\Helpers\Other::sanitizePhone(trim($sLogin));
        }
        if (0 >= ($iUserId = \Bethowen\Helpers\User::getDoubledUserID($aInputData['login']))){
            exitWithError($this, 'Пользователь не найден.');
        }

        $mInputData = $USER->Login($aInputData['login'], $aInputData['password']);
        if (true === $mInputData){
            try {
                $sToken = \Mobium\Api\ApiHelper::generateAccessToken($USER->GetID());
            } catch (\Exception $e) {
                exitWithError($this, 'Ошибка создания токена.');
            }
            if ($sToken){
                \Mobium\Api\ApiHelper::deleteAuthData($aInputData['appId']);
                $this->arResult = [
                    'status' => 'ok',
                    'data' => [
                        'accessToken' => $sToken
                    ]
                ];
            } else {
                exitWithError($this, 'Ошибка создания токена.');
            }
        } else {
            exitWithError($this, strip_tags($mInputData['MESSAGE']), [ $iUserId, $aInputData['login']]);
        }

    }
    $this->IncludeComponentTemplate();
}

function exitWithError($oObj, $sError, $aTemp=[])
{
    $oObj->arResult = [
        'status'=>'error',
        'data'=>['errorMessage'=>$sError, 'd'=>$aTemp],
    ];
    $oObj->IncludeComponentTemplate();
    exit();
}