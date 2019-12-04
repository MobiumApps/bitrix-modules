<?php
/**
 * @var array $arParams
 */

use Bitrix\Main\Config\Option;

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
        if (!filter_var($aInputData['email'], FILTER_VALIDATE_EMAIL)){
            $this->arResult = [
                'status'=>'error',
                'data'=>[
                    'errorMessage'=>'Введен некорректные email'
                ]
            ];
            $this->IncludeComponentTemplate();
            exit();
        }
        $aInputData['phone'] = \Bethowen\Helpers\Other::sanitizePhone(trim($aInputData['phone']));;

        $iCheckEmail = \Bethowen\Helpers\User::getDoubledUserID($aInputData['email']);
        $iCheckPhone = \Bethowen\Helpers\User::getDoubledUserID($aInputData['phone']);
        if (0 !== $iCheckEmail) {
            $this->arResult = [
                'status'=>'error',
                /*'data'=>[
                    'errors'=>[
                        [
                            'fieldId'=>'email',
                            'errorMessage'=>'Данный email уже зарегистрирован.'
                        ]
                    ]
                ]
                */
                'data'=>[
                    'errorMessage'=>'Данный email уже зарегистрирован.'
                ]
            ];
            $this->IncludeComponentTemplate();
            exit();
        }
        if (0 !== $iCheckPhone){
            $this->arResult = [
                'status'=>'error',
                'data'=>[
                    'errorMessage'=>'Данный телефон уже зарегистрирован.'
                ]
                /*'data'=>[
                    'errors'=>[
                        [
                            'fieldId'=>'email',
                            'errorMessage'=>'Данный телефон уже зарегистрирован.'
                        ]
                    ]
                ]*/
            ];
            $this->IncludeComponentTemplate();
            exit();
        }



        //RegisterModuleDependences("main", "OnAfterUserRegister", "mobium.api", "Mobium\Api\EventHandler", "onAfterUserRegister");
        $sLogin = md5(microtime(true));
        if (isset($aInputData['login']) && !empty(trim($aInputData['login']))){
            $sLogin = $aInputData['login'];
        }
        $sLogin = $aInputData['login'];
        /**@var CUser $USER */
        global $USER;
        $aRegisterResult = $USER->Register(
            $sLogin,
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
                $this->IncludeComponentTemplate();
                exit();
            }
            //$sPhone = ltrim($aInputData['phone'], '+');
            /*
            if (strpos($sPhone, '8') === 0){
                $sPhone = '7'.substr($sPhone, 1);
            }
            */
            //$aInputData['phone'] = '+'.$sPhone;
            $USER->Update($USER->GetID(), [
                'PERSONAL_PHONE'=>$aInputData['phone'],
            ]);
            $arProfileFields = array(
                "NAME" => "Профиль покупателя ".$aInputData['name'].'',
                "USER_ID" => $aRegisterResult['ID'],
                "PERSON_TYPE_ID" => 1
            );

            if (isset($aInputData['card_code']) && !empty(trim($aInputData['card_code']))){
                $sCardCode = trim($aInputData['card_code']);
                if (!is_numeric($sCardCode)){
                    $this->arResult = [
                        'status'=>'error',
                        'data'=>[
                            'errorMessage'=>'Проверьте номер карты'
                        ]
                    ];
                    $this->IncludeComponentTemplate();
                    exit();
                }
                if (false === \Mobium\Api\ApiHelper::attachCardToCurrentUser($aInputData['card_code'], $aInputData)){
                    $this->arResult = [
                        'status'=>'error',
                        'data'=>[
                            'errorMessage'=>'Ошибка при привязке карты'
                        ]
                    ];
                    $this->IncludeComponentTemplate();
                    exit();
                }
            } else {
                $iPoolStart = (int) Option::get('mobium.api', "pool_start", 0);
                $iPoolTotal = (int) Option::get('mobium.api', "pool_total", 0);
                $iPoolLastCard = (int) Option::get('mobium.api', "pool_last_card", 0);
                if ($iPoolStart > 0 && $iPoolStart+$iPoolTotal >= $iPoolLastCard){
                    if ($iPoolLastCard === 0){
                        $iPoolNextCard = $iPoolStart;
                    } else {
                        $iPoolNextCard = $iPoolLastCard+1;
                    }
                    $sCardCode = (string) $iPoolNextCard;
                    if (false !== \Mobium\Api\ApiHelper::attachCardToCurrentUser((string) $iPoolNextCard, $aInputData)){
                        Option::set('mobium.api', "pool_last_card", $iPoolNextCard);
                    }
                }
            }

            if ($iProfileId = \Mobium\Api\ApiHelper::createUserAppProfile($USER)){
                $USER->Update($USER->GetID(), [
                    'UF_APP_PROFILE'=>$PROFILE_ID
                ]);
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
        $aRequestData = null;
        if (isset($aInputData['login'])){
            $oUserResult = CUser::GetByLogin(trim($aInputData['login']));
            if ($aUser = $oUserResult->Fetch()){
                $aRequestData = ['login'=>$aUser['LOGIN'], 'email'=>$aUser['EMAIL']];
            }
        } elseif (isset($aInputData['email'])){
            $oUserResult = CUser::GetList($by, $order, ['EMAIL'=>$aInputData['email']]);
            if ($oUserResult->SelectedRowsCount() >= 1){
                if ($aUser = $oUserResult->Fetch()){
                    $aRequestData = ['login'=>$aUser['LOGIN'], 'email'=>$aUser['EMAIL']];
                }
            }
        }
        if ($aRequestData){
            /**@var CUser $USER */
            global $USER;
            $aResult = $USER->SendPassword($aRequestData['login'], $aRequestData['email']);
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
        } else {
            $this->arResult = [
                'status'=>'error',
                'data'=>[
                    'errorMessage'=>'Введены не все данные для восстановления пароля'
                ]
            ];
        }
        /*if (!isset($aInputData['login'], $aInputData['email'])){
            $this->arResult = [
                'status'=>'error',
                'data'=>[
                    'errorMessage'=>'Введены не все данные для восстановления пароля'
                ]
            ];
        } else {

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

        }*/

    }


    $this->IncludeComponentTemplate();

}
