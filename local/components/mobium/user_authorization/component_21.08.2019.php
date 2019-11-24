<?php
/**
 * @var array $arParams
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
\Bitrix\Main\Loader::includeModule('mobium.api');
if ($_SERVER['REQUEST_METHOD'] == 'DELETE'){
    $sHeader = $_SERVER['REMOTE_USER'] ?? '';
    if (!empty($sHeader)){
        $oResult = \Mobium\Api\AccessToken\AccessTokenTable::getList([
            'filter'=>[
                'BODY'=>$sHeader,
                'TYPE'=>'auth'
            ]
        ]);
        $aResult = $oResult->fetchAll();
        if (count($aResult) === 1){
            $aResult = $aResult[0];
            $oResult = \Mobium\Api\AccessToken\AccessTokenTable::delete($aResult['ID']);
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
    if (!isset($aInputData['appId']) || !isset($aInputData['platform']) || !ctype_digit((string) $aInputData['appId'])){
        $this->arResult = [
            'status' => 'error',
            'data' => [
                //'status'=>1,
                'errorMessage' => 'Входные данные не валидные.'
            ],
        ];
        $this->IncludeComponentTemplate();
        exit();
    }
    $oConnection = Bitrix\Main\Application::getConnection();
    $oSqlHelper = $oConnection->getSqlHelper();
    $sSql = 'SELECT * FROM `mobium_auth_tries` WHERE `APP_ID`='.$oSqlHelper->convertToDbInteger((string) $aInputData['appId']);
    try {
        $oRecordSet = $oConnection->query($sSql);
    } catch (\Bitrix\Main\Db\SqlQueryException $e) {
        $this->arResult = [
            'status' => 'error',
            'data' => [
                //'status'=>2,
                'errorMessage' => 'Ошибка БД.'
            ],
        ];
        $this->IncludeComponentTemplate();
        exit();
    }
    $bIsNew = false;
    if (false === ($aAppData = $oRecordSet->fetch())){
        $bIsNew = true;
        $aAppData['APP_ID'] = (int) $aInputData['appId'];
        $aAppData['DATA'] = [
            'tries'=>0,
            'last_try'=>0
        ];
    } else {
        $aAppData['DATA'] = unserialize($aAppData['DATA']);
    }

    if ($aAppData['DATA']['tries'] >= 5){
        $fDiff = microtime(true) - (float) $aAppData['DATA']['last_try'];
        if (($aAppData['DATA']['tries'] <= 10 && $fDiff <= 10) || ($aAppData['DATA']['tries'] > 10 && $fDiff <= 600)){
            if (!saveAppData($bIsNew, $aAppData)){
                $this->arResult = [
                    'status' => 'error',
                    'data' => [
                        'errorMessage' => 'Ошибка БД.'
                    ],
                ];
                $this->IncludeComponentTemplate();
                exit();
            }
            $fNextTry = $aAppData['DATA']['tries'] <= 10 ? 10-$fDiff : 600 - $fDiff;
            $iMins = floor($fNextTry / 60.0);
            $iSecs = $fNextTry % 60;

            $this->arResult = [
                'status' => 'error',
                'data' => [
                    'status'=>4,
                    'errorMessage' => 'Превышен лимит запросов. Повторите попытку через '.($iMins > 0 ? $iMins.' мин. и ' : '').$iSecs.' сек.'
                ],
            ];
            $this->IncludeComponentTemplate();
            exit();
        }
    }
    $aAppData['DATA']['tries']++;
    $aAppData['DATA']['last_try'] = microtime(true);
    if (!saveAppData($bIsNew, $aAppData)){
        $this->arResult = [
            'status' => 'error',
            'data' => [
                //'status'=>7,
                'errorMessage' => 'Ошибка БД.'
            ],
        ];
        $this->IncludeComponentTemplate();
        exit();
    }
    /**@var CUser $USER */
    global $USER;
    if (!is_object($USER)){
        $USER = new CUser();
    }

    if (!isset($aInputData['login'], $aInputData['password']) || empty($aInputData['login']) || empty($aInputData['password'])){
        $this->arResult = [
            'status'=>'error',
            'data'=>[
                'errorMessage'=>'Логин или пароль не могут быть пустыми'
            ]
        ];
    }
    if (!filter_var($aInputData['login'], FILTER_VALIDATE_EMAIL)){
        $aInputData['login'] = \Bethowen\Helpers\Other::sanitizePhone(trim($aInputData['login']));
    }
    /*
    $userBy = "id";
    $userOrder = "desc";
    if(strpos($aInputData['login'], '@')!==false){
        $userFilter = array(
            'ACTIVE' => 'Y',
            '=EMAIL' => $aInputData['login'],
        );
    }else{
        $aInputData['login'] = str_replace("+","",$aInputData['login']);
        $aInputData['login'] = substr( $aInputData['login'], 1);
        $aInputData['login'] = "7" . $aInputData['login'];
        $userFilter = array(
            'ACTIVE' => 'Y',
            'PERSONAL_PHONE' => $aInputData['login'],
        );
    }
    $userParams = array(
        'SELECT' => array(),
        'FIELDS' => array(
            'ID',
            'LOGIN'
        ),
    );
    $rsUser = CUser::GetList(
        $userBy,
        $userOrder,
        $userFilter,
        $userParams
    );
    if ($arResult['USER'] = $rsUser->Fetch())
    {
        $aInputData['login'] = $arResult['USER']['LOGIN'];
    }else{
        $this->arResult = [
            'status' => 'error',
            'data' => [
                'errorMessage' => 'Данные введены не правильно'
            ]
        ];
    }*/
    $mInputData = $USER->Login($aInputData['login'], $aInputData['password'], 'N', 'Y');
    if (true === $mInputData){
        $oResult = \Mobium\Api\AccessToken\AccessTokenTable::getList([
            'filter'=>[
                'USER_ID'=>$USER->GetID(),
                'TYPE'=>'auth'
            ]
        ]);
        $aResult = $oResult->fetchAll();
        $iTime = time();
        if (count($aResult) === 0) {
            $sToken = md5('userMobium' . $USER->GetID() . $iTime);
            try {
                $oAddResult = \Mobium\Api\AccessToken\AccessTokenTable::add([
                    'BODY' => $sToken,
                    'CREATED_AT' => $iTime,
                    'LIFETIME' => 3600,
                    'TYPE' => 'auth',
                    'USER_ID' => $USER->GetID(),
                ]);
            } catch (Exception $e) {
                $this->arResult = [
                    'status' => 'error',
                    'data' => [
                        'errorMessage' => 'Не смог создать токен'
                    ]
                ];
            }
        } elseif (count($aResult) === 1) {
            $sToken = $aResult[0]['BODY'];
        } else {
            $this->arResult = [
                'status' => 'error',
                'data' => [
                    'errorMessage' => 'Ошибка токена'
                ],
            ];
        }
        if (isset($sToken)){
            $sSql = 'DELETE FROM `mobium_auth_tries` WHERE `APP_ID`='.$oSqlHelper->convertToDbInteger((string) $aInputData['appId']);
            try {
                $oConnection->queryExecute($sSql);
            } catch (\Bitrix\Main\Db\SqlQueryException $e) {
            }
            $this->arResult = [
                'status' => 'ok',
                'data' => [
                    'accessToken' => $sToken
                ]
            ];
        }
    } else {
        $this->arResult = [
            'status'=>'error',
            'data'=>[
                'errorMessage'=>strip_tags($mInputData['MESSAGE']),
            ]
        ];
    }
    $this->IncludeComponentTemplate();

}


function saveAppData($bIsNew, $aAppData)
{
    $oConnection = Bitrix\Main\Application::getConnection();
    $oSqlHelper = $oConnection->getSqlHelper();
    if ($bIsNew){
        $sSql = "INSERT INTO `mobium_auth_tries`(`APP_ID`, `DATA`) VALUES (".$oSqlHelper->convertToDbInteger((string) $aAppData['APP_ID']).",'".$oSqlHelper->forSql(serialize($aAppData['DATA']))."')";
    } else {
        $sSql = "UPDATE `mobium_auth_tries` SET `DATA`='".$oSqlHelper->forSql(serialize($aAppData['DATA']))."' WHERE `APP_ID`=".$oSqlHelper->convertToDbInteger((string) $aAppData['APP_ID']);
    }

    try {
        $oConnection->queryExecute($sSql);
    } catch (\Bitrix\Main\Db\SqlQueryException $e) {
        return false;
    }
    return true;
}