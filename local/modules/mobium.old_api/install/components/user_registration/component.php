<?php
/**
 * @var array $arParams
 */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
\Bitrix\Main\Loader::includeModule('mobium.api');
if ($_SERVER["REQUEST_METHOD"] == "POST"){
    $aInputData = json_decode(file_get_contents('php://input'), true);
    if ($arParams['method'] == 'user_register'){
        \Mobium\Api\OptionsHelper::changeOptions('main', 'captcha_registration', 'N');

        if (JSON_ERROR_NONE !== json_last_error()){
            exit();
        }
        $oEventManager = \Bitrix\Main\EventManager::getInstance();
        $oEventManager->addEventHandler("main", "OnAfterUserRegister", '\Mobium\Api\EventHandler::onAfterUserRegister');
        //RegisterModuleDependences("main", "OnAfterUserRegister", "mobium.api", "Mobium\Api\EventHandler", "onAfterUserRegister");
        /**@var CUser $USER */
        global $USER;
        $aRegisterResult = $USER->Register(
            $aInputData['login'],
            $aInputData['name'],
            $aInputData['last_name'] ?? '',
            $aInputData['password'],
            $aInputData['password_confirm'],
            $aInputData['email']
        );

        if ($aRegisterResult['TYPE'] == 'OK'){
            $iTime = time();
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
                    'status'=>'error',
                    'data'=>[
                        'errorMessage'=>'Не смог создать токен'
                    ]
                ];
            }
            $USER->Update($USER->GetID(), [
                'PERSONAL_PHONE'=>$aInputData['phone'],
            ]);
            $arProfileFields = array(
                "NAME" => "Профиль покупателя ".$aInputData['name'].'',
                "USER_ID" => $aRegisterResult['ID'],
                "PERSON_TYPE_ID" => 1
            );
            $PROFILE_ID = CSaleOrderUserProps::Add($arProfileFields);
            //если профиль создан
            if ($PROFILE_ID) {
                //формируем массив свойств
                $PROPS=[
                    [
                        "USER_PROPS_ID" => $PROFILE_ID,
                        "ORDER_PROPS_ID" => 3,
                        "NAME" => "Телефон",
                        "VALUE" => $aInputData['phone']
                    ],
                    [
                        "USER_PROPS_ID" => $PROFILE_ID,
                        "ORDER_PROPS_ID" => 1,
                        "NAME" => "Ф.И.О.",
                        "VALUE" => $aInputData['name']
                    ]
                ];
                //добавляем значения свойств к созданному ранее профилю
                foreach ($PROPS as $prop){
                    $iPropId = CSaleOrderUserPropsValue::Add($prop);
                }
            }
            $this->arResult = [
                'status'=>'ok',
                'data'=>[
                    'accessToken'=>$sToken
                ]
            ];
        } else {
            $this->arResult = [
                'status'=>'error',
                'data'=>[
                    'errorMessage'=>preg_replace('/\<br(\s*)?\/?\>/i', "\n", $aRegisterResult['MESSAGE']),
                ]
            ];
        }
    } elseif ($arParams['method'] === 'user_restore_password') {
        if (!isset($aInputData['login'], $aInputData['email'])){
            $this->arResult = [
                'status'=>'error',
                'data'=>[
                    'errorMessage'=>'Введены не все данные для восстановления пароля'
                ]
            ];
        } else {
            /**@var CUser $USER */
            global $USER;
            $aResult = $USER->SendPassword($aInputData['login'], $aInputData['email']);
            if ($aResult['TYPE'] == 'OK') {
                $this->arResult = [
                    'status'=>'ok',
                    'data'=>[
                        'message'=>'Письмо отправлено Вам на email!'
                    ]
                ];
            } else {
                $this->arResult = [
                    'status'=>'error',
                    'data'=>[
                        'errorMessage' => ''
                    ]
                ];
            }

        }
    }


    $this->IncludeComponentTemplate();

}
