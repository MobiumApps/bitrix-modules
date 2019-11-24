<?php
/**
 * @var array $arParams
 */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
\Bitrix\Main\Loader::includeModule('mobium.api');

$aFilter = [];
$aOrder = [];
switch ($arParams['method']){
    case 'registration_fields':
        $aFilter = [
            'REGISTER_ACTIVE'=>'Y',
        ];
        $aOrder = [
            'REGISTER_SORT'=>'ASC'
        ];
        break;
    case 'get_profile':
        if (!\Mobium\Api\ApiHelper::authorizeByHeader()){
            $this->arResult = [
                'status'=>'error',
                'data'=>[
                    'errorMessage'=>'not_authorized'
                ]
            ];
            $this->IncludeComponentTemplate();
            exit();
        }
        $sUserPhoto = '';
        if (($iUserPhoto = $USER->GetParam('PERSONAL_PHOTO'))){
            $aFile = CFile::GetFileArray($iUserPhoto);
            if (substr($aFile['SRC'], 0, 1) == '/'){
                $sUserPhoto = 'http://'.$_SERVER['HTTP_HOST'].$aFile['SRC'];
            }
        }
        $aFilter = [
            'PROFILE_ACTIVE'=>'Y',
        ];
        $aOrder = [
            'PROFILE_SORT'=>'ASC'
        ];
        break;
    case 'restore_fields':
        $aFilter = [
            'RESTORE_ACTIVE'=>'Y',
        ];
        $aOrder = [
            'RESTORE_SORT'=>'ASC'
        ];
        break;
    case 'update':
        if (!\Mobium\Api\ApiHelper::authorizeByHeader()){
            $this->arResult = [
                'status'=>'error',
                'data'=>[
                    'errorMessage'=>'not_authorized'
                ]
            ];
            $this->IncludeComponentTemplate();
            exit();
        }
        $aFilter = [
            'EDITABLE'=>'Y',
            'PROFILE_ACTIVE'=>'Y'
        ];
        $aOrder = [
            'PROFILE_SORT'=>'ASC'
        ];
        $aInputData = json_decode(file_get_contents('php://input'), true);
        $aData = [];
        foreach ($aInputData['fields'] as $aField) {
            $aData[$aField['field_id']] = $aField;
        }
        break;
    case 'generate_code':
        $oGenerator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        $sUserId = isset($_GET['userId']) ? $_GET['userId'] : null;
        if ($sUserId === null){
            exit();
        }
        $by = 'id'; $order='desc';
        $qUsers = \CUser::GetList($by, $order, ['ID'=>$sUserId], [
            'SELECT'=>['UF_DISCOUNTCARD', 'UF_DISCOUNT'],
            'FIELDS'=>['ID']
        ]);
        $rUser = $qUsers->Fetch();

        if (!isset($rUser['UF_DISCOUNTCARD']) || empty($rUser['UF_DISCOUNTCARD'])){
            exit();
        }

        $sContent = $oGenerator->getBarcode($rUser['UF_DISCOUNTCARD'], $oGenerator::TYPE_EAN_13);
        header('Content-Transfer-Encoding: binary');
        header('Content-Type: image/png');
        header('Content-Length: '.mb_strlen($sContent));
        echo $sContent;
        exit();
        break;
    default:
        $this->arResult = [
            'status'=>'error',
            'data'=>[
                'errorMessage'=>'Unsupported method'
            ]
        ];
        $this->IncludeComponentTemplate();
        exit();
}
$oResult = \Mobium\Api\RegistrationField\RegistrationFieldTable::getList([
    'filter'=>$aFilter,
    'order'=>$aOrder,
]);
$this->arResult = [];
$aFields = [];
$aCardData = \Mobium\Api\ApiHelper::getCurrentUserCardData();

while ($aField = $oResult->fetch()){
    switch ($arParams['method']){
        case 'registration_fields':
            $aTempField = [
                'id'=>$aField['SLUG'],
                'type'=>$aField['REGISTER_TYPE'],
                'title'=>$aField['REGISTER_TITLE'],
                'required'=>$aField['REGISTER_REQUIRED'] == 'Y',
                'need_verification'=>$aField['VERIFICATION_ACTIVE'] == 'Y'
            ];
            if ($aTempField['need_verification']) {
                $aTempField['time'] = $aField['VERIFICATION_TIME'];
                $aTempField['text'] = $aField['VERIFICATION_TEXT'];
                $aTempField['editable'] = $aField['EDITABLE'] == 'Y';
                $aTempField['code_input_type'] = $aField['VERIFICATION_TYPE'];
            }
            $aFields[] = $aTempField;
            break;
        case 'restore_fields':
            $aTempField = [
                'id'=>$aField['SLUG'],
                'type'=>$aField['REGISTER_TYPE'],
                'title'=>$aField['REGISTER_TITLE']
            ];
            $aFields[] = $aTempField;
            break;
        case 'get_profile':
            $oUserResult = CUser::GetList($by, $desc, ['ID'=>$USER->GetID()], ['SELECT'=>['UF_DISCOUNTCARD']]);

            $aUserData = $oUserResult->Fetch();

            $aTempField = [
                'cabinet_field_type' => $aField['PROFILE_TYPE']
            ];
            $bAddToResult = true;
            switch ($aTempField['cabinet_field_type']){
                case 'name_field':
                    $aTempField['editable'] = $aField['EDITABLE'] == 'Y';
                    $aTempField['id'] = $aField['SLUG'];
                    $aTempField['value'] = \Mobium\Api\ApiHelper::getProfileFieldValue($aField['SLUG']);
                    $aTempField['image'] = $sUserPhoto;
                    break;
                case 'image_action_field':
                    $aTempField['action'] = [
                        'type'=>$aTempField['PROFILE_ACTION'],
                        'param'=>$aTempField['PROFILE_ACTION_PARAM']
                    ];
                    $aTempField['image'] = '';
                    break;
                case 'title_text_field':
                    $aTempField['editable'] = $aField['EDITABLE'] == 'Y';
                    $aTempField['id'] = $aField['SLUG'];
                    $aTempField['title'] = $aField['PROFILE_TITLE'];
                    $aTempField['value'] = \Mobium\Api\ApiHelper::getProfileFieldValue($aField['SLUG']);
                    break;
                case 'text_field':
                    $aTempField['value'] = \Mobium\Api\ApiHelper::getProfileFieldValue($aField['SLUG']);
                    $aTempField['editable'] = $aField['EDITABLE'] == 'Y';
                    break;
                case 'action_field':
                    $aTempField['action'] = [
                        'type'=>$aTempField['PROFILE_ACTION'],
                        'param'=>$aTempField['PROFILE_ACTION_PARAM']
                    ];
                    $aTempField['value'] = \Mobium\Api\ApiHelper::getProfileFieldValue($aField['SLUG']);
                    break;
                case 'bonus_field':
                    if (false !== $aCardData && $aCardData['BONUS']){
                        $aTempField['title'] = $aField['PROFILE_TITLE'];
                        $aTempField['value'] = $aCardData['BONUS_POINTS'];
                        /*if ($aCardData['BONUS']){

                        } else {
                            $aTempField['value'] = (float) $aCardData['UF_DISCOUNT'] / 100;
                        }*/
                    } else {
                        $bAddToResult = false;
                    }
                    break;
                case 'barcode_field':
                    $aTempField['image'] = \Mobium\Api\ApiHelper::getProfileFieldValue($aField['SLUG']);
                    $oUri = new \Bitrix\Main\Web\Uri('https://'.$_SERVER['HTTP_HOST'].'/api/mobium.php?method=generateBarcode&userId='.$USER->GetId());
                    $aTempField['image'] = $oUri->getUri();
                    break;
            }
            if ($aField['VERIFICATION_ACTIVE'] == 'Y') {
                $aTempField['need_verification'] = $aField['VERIFICATION_ACTIVE'] == 'Y';
                $aTempField['time'] = $aField['VERIFICATION_TIME'];
                $aTempField['text'] = $aField['VERIFICATION_TEXT'];
                $aTempField['editable'] = $aField['EDITABLE'] == 'Y';
                $aTempField['code_input_type'] = $aField['VERIFICATION_TYPE'];
            }
            if ($bAddToResult){
                $aFields[] = $aTempField;
            }

            break;
        case 'update':
            $aErrors = [];
            if (isset($aData[$aField['SLUG']])) {
                if (!($bRes = \Mobium\Api\ApiHelper::setProfileFieldValue($aField['SLUG'], $aData[$aField['SLUG']]['value']))){
                    $aErrors[] = 'Ошибка при изменении поля '.$aField['PROFILE_TITLE'];
                }
            }


    }
}

$this->arResult = [
    'status'=>'ok',
    'data'=>[
        'fields'=>$aFields
    ]
];
if ($arParams['method'] == 'get_profile'){
    if (isset($_GET['platform']) && $_GET['platform'] === 'ios') {
        $aRes = [];
        foreach ($aFields as $aField) {
            if (in_array($aField['id'], ['name_field', 'title_text_field', 'text_field', ])){
                $aRes[$aField['id']] = $aField['value'];
            }
            if ($aField['id'] === 'barcode_field'){
                $oUri = new \Bitrix\Main\Web\Uri('/api/mobium.php?method=generateBarcode&userId='.$USER->GetId());
                $aRes[$aField['id']] = $oUri->getUri();
            }
        }
        /*if ($iBonuses > 0){
            $aRes['bonus'] = [
                'amount'=>$iBonuses,
                'available_proportion'=>((int)\Bitrix\Main\Config\Option::get('logictim.balls', 'MAX_PAYMENT_SUM', 100) / 100),
            ];
            //$this->arResult['data']['bonuses'] =
        }*/
        $this->arResult = [
            'status'=>'ok',
            'data'=>$aRes
        ];
    } else {
        if (false !== $aCardData){
            if ($aCardData['BONUS']){
                $this->arResult['data']['bonuses'] = [
                    'amount'=>(float)$aCardData['BONUS_POINTS'],
                    'available_proportion'=>((int)\Bitrix\Main\Config\Option::get('logictim.balls', 'MAX_PAYMENT_SUM', 100) / 100),
                ];
            } else {
                $this->arResult['data']['discount']= [
                    'value'=>(float) $aCardData['UF_DISCOUNT'] /100.0
                ];
            }
        }
        /*$this->arResult['data']['discount']= [
            'value'=>\Mobium\Api\ApiHelper::getCurrentUserPersonalDiscount()/100.0
        ];*/
    }


}
if ($arParams['method'] == 'update'){
    $this->arResult = [
        'status'=>empty($aErrors) ? 'ok':'error',
    ];
    if ($this->arResult['status'] == 'error'){
        $this->arResult['data'] = [
            'errorMessage'=>implode("\n", $aErrors)
        ];
    }
}
$this->IncludeComponentTemplate();
