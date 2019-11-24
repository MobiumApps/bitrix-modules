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
