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
            exitWithError($this, 'Данный email уже зарегистрирован.');
        }
        if (0 !== $iCheckPhone){
            exitWithError($this, 'Данный телефон уже зарегистрирован.');
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
            $USER->Update($USER->GetID(), [
                'ACTIVE'=>'N'
            ]);
            try {
                $oAddResult = \Mobium\Api\AccessToken\AccessTokenTable::add([
                    'BODY' => $sToken,
                    'CREATED_AT' => $iTime,
                    'LIFETIME' => 3600,
                    'TYPE' => 'auth',
                    'USER_ID' => $USER->GetID(),
                ]);
            } catch (Exception $e) {
                exitWithError($this, 'Возникла ошибка при создании ключа доступа.');
            }
            $aUserUpdateParams = [
                'PERSONAL_PHONE'=>$aInputData['phone'],
            ];
            if (isset($aInputData['birthday'])){
                $sInputFormat = $sOutputFormat = 'd.m.Y';
                $oDateTime = \DateTime::createFromFormat($sInputFormat, $aInputData['birthday']);
                if (false !== $oDateTime){
                    $aUserUpdateParams['PERSONAL_BIRTHDAY'] = $oDateTime->format($sOutputFormat);
                }
            } else {
                exitWithError($this, 'Дата рождения - обязательное поле.');

            }
            if (isset($aInputData['sex'])){
                switch ($aInputData['sex']){
                    case 'male':
                        $aUserUpdateParams['PERSONAL_GENDER'] = 'M';
                        break;
                    case 'female':
                        $aUserUpdateParams['PERSONAL_GENDER'] = 'F';
                        break;
                }
            }
            $USER->Update($USER->GetID(), $aUserUpdateParams);
            $arProfileFields = array(
                "NAME" => "Профиль покупателя ".$aInputData['name'].'',
                "USER_ID" => $aRegisterResult['ID'],
                "PERSON_TYPE_ID" => 1
            );

            if (isset($aInputData['card_code']) && !empty(trim($aInputData['card_code']))){
                $sCardCode = trim($aInputData['card_code']);
                if (!is_numeric($sCardCode)){
                    exitWithError($this, 'Проверьте номер карты.');
                }
                /*
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
                */
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
            $sPhone = $aInputData['phone'];
            if (isset($sCardCode) && !empty($sCardCode) && $sPhone){
                //\Mobium\Api\ApiHelper::authorizeUserCard($sCardCode);
                //по номеру ДК чекаем есть ли она в базе и активирована ли
                $dcRegistration = \Bethowen\Services\LoymaxDsq::beginRegistration($sCardCode);
                if ($dcRegistration['data']['state'] == 'RegistrationAlreadyCompleted') {
                    //есть и активирована, смотрин на кого и совпадает ли с телефоном, который у юзера
                    $secretCheck = \Bethowen\Services\LoymaxDsq::secretCheckPhone($sPhone);
                    $secretCheckByDC = \Bethowen\Services\LoymaxDsq::secretGetInfoByCard($secretCheck['data'][0]['groupId']);
                    foreach ($secretCheckByDC['data'] as $dcData) {
                        if ($dcData['block'] == false) {
                            $dcNubmerLoymax = $dcData['number'];
                        }
                    }
                    if (isset($dcNubmerLoymax) && $sCardCode == $dcNubmerLoymax && !empty($dcNubmerLoymax)) {
                        $resetPswd = \Bethowen\Services\LoymaxDsq::resetPassword($sPhone);
                        $aRegisterSessionData = \Mobium\Api\ApiHelper::getRegisterSessionData((int) $aInputData['appId']);
                        $aRegisterSessionData['DATA']['ACTION'] = 'reset_password';
                        $aRegisterSessionData['DATA']['USER_ID'] = $USER->GetID();
                        $aRegisterSessionData['DATA']['CARD_CODE'] = $sCardCode;
                        \Mobium\Api\ApiHelper::setRegisterSessionData((int) $aInputData['appId'], $aRegisterSessionData);
                        $this->arResult = [
                            'status'=>'verification',
                        ];
                    } else {
                        exitWithError($this, 'Карта зарегистрирована на другой телефон.');
                    }
                    $this->IncludeComponentTemplate();
                    exit();
                } elseif ($dcRegistration['data']['state'] == 'Success') {
                    //есть, но неактивирована, аналогично чекаем на совпадение телефонов
                    $secretCheck = \Bethowen\Services\LoymaxDsq::secretCheckPhone($sPhone);
                    $secretCheckByDC = \Bethowen\Services\LoymaxDsq::secretGetInfoByCard($secretCheck['data'][0]['groupId']);
                    foreach ($secretCheckByDC['data'] as $dcData) {
                        if ($dcData['block'] == false) {
                            $dcNubmerLoymax = $dcData['number'];
                        }
                    }
                    if (!empty($dcNubmerLoymax) && $dcNubmerLoymax != $sCardCode) {
                        //карта зарегана на другой телефон
                        exitWithError($this, 'На этот номер телефона уже зарегистрирована другая карта.');
                    } else {
                        //карта зарегана на нужный телефон, продолжим активацию, проверим шаги реги
                        $isActive = \Bethowen\Services\LoymaxDsq::checkActiveCard($sCardCode, $dcRegistration['data']['authToken']);
                        $countDone = 0;
                        foreach ($isActive['data']['actions'] as $action) {
                            if ($action['isDone'] == true) {
                                $countDone++;
                            }
                        }
                        if ($countDone == count($isActive['data']['actions'])) {
                            //оказывается все шаги пройдены, тут можно Обратитесь в КЦ выдать, но такое будет 1 на миллион
                            exitWithError($this, 'Произошла непредвиденная ошибка. Обратитесь в КЦ.');
                        } else {
                            //шаги пройдены не все и надо их пройти чтобы дальше зарегать, открываем поля анкеты, юзер заполняет их и мы обрабатываем ниже

                            //подтверждение оферты
                            $acceptTenderOffer = \Bethowen\Services\LoymaxDsq::acceptRegisterTenderOffer($sPhone, $dcRegistration['data']['authToken']);
                            //подтверждение телефона(отправляется смс)
                            $sendConfirmPhone = \Bethowen\Services\LoymaxDsq::setPhoneNumber($sPhone, $dcRegistration['data']['authToken']);
                            //получаем вопросы анкеты и заполняем их
                            $anketa = \Bethowen\Services\LoymaxDsq::anketaUserInfo($sCardCode, $dcRegistration['data']['authToken']);
                            $arAnketa = [];
                            foreach ($anketa['data']['questions'] as $question) {
                                if ($question['nodeType'] == 'Header') {
                                    foreach ($question['children'] as $singleQ) {
                                        if ($singleQ['logicalName'] == 'LastName') {
                                            $arAnketa['answers'][] = [
                                                'groupID' => $singleQ['groupID'],
                                                'id' => $singleQ['id'],
                                                'value' => $aInputData['last_name'],//фамилия
                                                'selected' => $singleQ['selected']
                                            ];
                                        } elseif ($singleQ['logicalName'] == 'FirstName') {
                                            $arAnketa['answers'][] = [
                                                'groupID' => $singleQ['groupID'],
                                                'id' => $singleQ['id'],
                                                'value' => $aInputData['name'],//имя
                                                'selected' => $singleQ['selected']
                                            ];
                                        } elseif ($singleQ['logicalName'] == 'Sex') {
                                            foreach ($singleQ['children'] as $singleQSex) {
                                                if ($singleQSex['displayName'] == 'Женский') {
                                                    if ($aInputData['sex'] == 'female') {//если выбран женский пол
                                                        $arAnketa['answers'][] = [
                                                            'groupID' => $singleQSex['groupID'],
                                                            'id' => $singleQSex['id'],
                                                            'value' => 'null',
                                                            'selected' => true
                                                        ];
                                                    }
                                                }
                                                if ($singleQSex['displayName'] == 'Мужской') {
                                                    if ($aInputData['sex'] == 'male') {//если выбран иужской пол
                                                        $arAnketa['answers'][] = [
                                                            'groupID' => $singleQSex['groupID'],
                                                            'id' => $singleQSex['id'],
                                                            'value' => 'null',
                                                            'selected' => true
                                                        ];
                                                    }
                                                }
                                            }
                                        } elseif ($singleQ['logicalName'] == 'BirthDay') {
                                            $bd = date('Y-m-d', strtotime($aInputData['birthday']));//дата рождения
                                            $arAnketa['answers'][] = [
                                                'groupID' => $singleQ['groupID'],
                                                'id' => $singleQ['id'],
                                                'value' => $bd,
                                                'selected' => $singleQ['selected']
                                            ];
                                        } elseif ($singleQ['logicalName'] == 'BirthDayAnimal') {
                                            $bdp = date('Y-m-d', strtotime($aInputData['pet_created']));//дата рождения питомца(необязательно)
                                            $arAnketa['answers'][] = [
                                                'groupID' => $singleQ['groupID'],
                                                'id' => $singleQ['id'],
                                                'value' => $bdp,
                                                'selected' => $singleQ['selected']
                                            ];
                                        }

                                    }
                                }
                            }
                            //запрос на изменение данных в анкете
                            $changeAnketa = \Bethowen\Services\LoymaxDsq::changeUserAnswers($sCardCode, $arAnketa, $dcRegistration['data']['authToken']);
                            //запрашиваем смену емейла через ссылку в письме, главное просто вызвать этот метод, юзер там сам разберется
                            \Bethowen\Services\LoymaxDsq::changeEmailUserInfo($sCardCode, $aInputData['email']);
                            $aRegisterSessionData = \Mobium\Api\ApiHelper::getRegisterSessionData((int) $aInputData['appId']);


                            $aRegisterSessionData['DATA']['ACTION'] = 'finish_registration';
                            $aRegisterSessionData['DATA']['USER_ID'] = $USER->GetID();
                            $aRegisterSessionData['DATA']['CARD_CODE'] = $sCardCode;
                            \Mobium\Api\ApiHelper::setRegisterSessionData((int) $aInputData['appId'], $aRegisterSessionData);
                            $this->arResult = [
                                'status'=>'verification',
                            ];
                            $this->IncludeComponentTemplate();
                            exit();

                        }

                    }

                }
            }
            $USER->Update($USER->GetID(), [
                'ACTIVE'=>'Y'
            ]);
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

    } elseif ($arParams['method'] === 'verify_data') {
        if (isset($aInputData['appId'])){
            $aRegisterSessionData = \Mobium\Api\ApiHelper::getRegisterSessionData((int) $aInputData['appId']);
            if (isset($aRegisterSessionData['ID'])){
                $aData = $aRegisterSessionData['DATA'];
                if (!isset($aData['USER_ID'])){
                    $this->arResult = [
                        'status'=>'error',
                        'data'=>[
                            'errorMessage'=>'Невалидные данные.'
                        ]
                    ];
                    $this->IncludeComponentTemplate();
                    exit();
                }
                global $USER;
                $USER->Authorize($aData['USER_ID']);
                switch ($aData['ACTION']){
                    case 'finish_registration':
                        $sPhone = \Mobium\Api\ApiHelper::getProfileFieldValue('phone');
                        $sCardCode = $aData['CARD_CODE'];
                        $dcRegistration = \Bethowen\Services\LoymaxDsq::beginRegistration($sCardCode);
                        $aResult = \Bethowen\Services\LoymaxDsq::confirmPhoneNumber($sPhone, $aInputData['code'], $dcRegistration['data']['authToken'], true);
                        //Ну и закончить регу если введен верный код
                        if ($aResult['result']['state'] == 'Success') {
                            $sCardCode = $aData['CARD_CODE'];
                            if (!!$sCardCode) {
                                //сам запрос на окончание реги
                                $final = \Bethowen\Services\LoymaxDsq::tryFinishRegistration($sCardCode);
                                //в битриксе записываем поле с ДК у юзера
                                $user_id = $USER->GetID();
                                $userUpdate = new CUser;
                                $fieldsUserUpdate = Array(
                                    "UF_LOYMAX_BONUS" => $sCardCode,
                                    'ACTIVE'=>'Y'
                                );
                                $userUpdate->Update($user_id, $fieldsUserUpdate);
                                \Mobium\Api\ApiHelper::deleteRegisterSessionData((int) $aRegisterSessionData['ID']);
                            }
                        } else {
                            exitWithError($this, 'Ошибка в смене пароля Loymax');
                        }
                        break;
                    case 'reset_password':
                        $sPhone = \Mobium\Api\ApiHelper::getProfileFieldValue('phone');
                        $aResult = \Bethowen\Services\LoymaxDsq::setNewPassword($sPhone, $aInputData['code']);
                        if ($aResult['result']['state'] == 'Success') {
                            $sCardCode = $aData['CARD_CODE'];
                            if (!!$sCardCode) {
                                //сам запрос на окончание реги
                                $final = \Bethowen\Services\LoymaxDsq::tryFinishRegistration($sCardCode);
                                //в битриксе записываем поле с ДК у юзера
                                $user_id = $USER->GetID();
                                $userUpdate = new CUser;
                                $fieldsUserUpdate = Array(
                                    "UF_LOYMAX_BONUS" => $sCardCode,
                                    'ACTIVE'=>'Y'
                                );
                                $userUpdate->Update($user_id, $fieldsUserUpdate);
                                \Mobium\Api\ApiHelper::deleteRegisterSessionData((int) $aRegisterSessionData['ID']);
                            }
                        } else {
                            exitWithError($this, 'Ошибка в смене пароля Loymax');
                        }
                        break;
                }
                $oResult = \Mobium\Api\AccessToken\AccessTokenTable::getList([
                    'filter'=>[
                        'USER_ID'=>$USER->GetID(),
                        'TYPE'=>'auth'
                    ]
                ]);
                $aResult = $oResult->fetchAll();
                if (count($aResult) !== 1){
                    exitWithError($this, 'Ошибка при восстановлении ключа доступа.');
                }
                $sToken = $aResult[0]['BODY'];
                $this->arResult = [
                    'status'=>'ok',
                    'data'=>[
                        'accessToken'=>$sToken
                    ]
                ];
            }
        }
    } elseif ($arParams['method'] == 'get_new_code'){
        if (isset($aInputData['appId'])) {
            $aRegisterSessionData = \Mobium\Api\ApiHelper::getRegisterSessionData((int)$aInputData['appId']);
            if (isset($aRegisterSessionData['ID'])) {
                $aData = $aRegisterSessionData['DATA'];
                if (!isset($aData['USER_ID'])) {
                    exitWithError($this, 'Данные не валидны.');
                }
                global $USER;
                $USER->Authorize($aData['USER_ID']);
                $sCardCode = $aData['CARD_CODE'];
                $sPhone = \Mobium\Api\ApiHelper::getProfileFieldValue('phone');
                switch ($aData['ACTION']) {
                    case 'finish_registration':
                        $dcRegistration = \Bethowen\Services\LoymaxDsq::beginRegistration($sCardCode);
                        $sendConfirmPhone = \Bethowen\Services\LoymaxDsq::setPhoneNumber($sPhone, $dcRegistration['data']['authToken']);
                        $this->arResult = [
                            'status' => 'ok'
                        ];
                        break;
                    case 'reset_password':
                        $resetPswd = \Bethowen\Services\LoymaxDsq::resetPassword($sPhone);
                        $this->arResult = [
                            'status' => 'ok'
                        ];
                        break;

                }
            }
        }
    }


    $this->IncludeComponentTemplate();

}


function exitWithError($oObj, $sError)
{
    $oObj->arResult = [
        'status'=>'error',
        'data'=>['errorMessage'=>$sError]
    ];
    $oObj->IncludeComponentTemplate();
    exit();
}