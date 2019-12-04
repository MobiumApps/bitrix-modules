<?php

use Bitrix\Main\Context;
require_once ($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
$sMethod = trim($_GET['method']);
global $APPLICATION;
$request = Context::getCurrent()->getRequest();
switch ($sMethod){
    case 'getRegistrationFields':
        $APPLICATION->IncludeComponent('mobium:profile', '.default', [
            'method'=>$sMethod //'registration_fields'
        ]);
        break;
    case 'userRegistration':
        $APPLICATION->IncludeComponent('mobium:user_registration', '.default', [
            'method'=>'user_register'
        ]);
        break;
    case 'userAuthorization':
        $APPLICATION->IncludeComponent('mobium:user_authorization', '.default', [
            'method'=>'auth_by_login'
        ]);
        break;
    case 'userLogout':
        $APPLICATION->IncludeComponent('mobium:user_authorization', '.default', [
            'method'=>'logout'
        ]);
        break;
    case 'authByCode':
        $APPLICATION->IncludeComponent('mobium:user_authorization', '.default', [
            'method'=>'auth_by_code'
        ]);
        break;
    case 'verifyCode':
        $APPLICATION->IncludeComponent('mobium:user_authorization', '.default', [
            'method'=>'verify_code'
        ]);
        break;
    case 'getRestoreFields':
        $APPLICATION->IncludeComponent('mobium:profile', '.default', [
            'method'=>'restore_fields'
        ]);
        break;
    case 'userRestorePassword':
        $APPLICATION->IncludeComponent('mobium:user_registration', '.default', [
            'method'=>'user_restore_password'
        ]);
        break;
    case 'getUserProfile':
        $APPLICATION->IncludeComponent('mobium:profile', '.default', [
            'method'=>'get_profile'
        ]);
        break;
    case 'userChangeProfile':
        $APPLICATION->IncludeComponent('mobium:profile', '.default', [
            'method'=>'update'
        ]);
        break;
    case 'userOrderHistory':
        $APPLICATION->IncludeComponent('mobium:orders', '.default', [
            'method'=>'get_history'
        ]);
        break;
    case 'commitOrder':
        $APPLICATION->IncludeComponent('mobium:orders', '.default', [
            'method'=>'commit_order'
        ]);
        break;
    case 'startAgent':
        $APPLICATION->IncludeComponent('mobium:export', '.default', [
            'method'=>'commit_order'
        ]);
        break;

    case 'applyCode':
        $APPLICATION->IncludeComponent('mobium:basket', '.default', [
            'method'=>'apply_code'
        ]);
        break;
    case 'generateBarcode':
        $APPLICATION->IncludeComponent('mobium:profile', '.default', [
            'method'=>'generate_code'
        ]);
        break;

    case 'verifyData':
        $APPLICATION->IncludeComponent('mobium:user_registration', '.default', [
            'method'=>'verify_data'
        ]);
        break;
    case 'getNewCode':
        $APPLICATION->IncludeComponent('mobium:user_registration', '.default', [
            'method'=>'get_new_code'
        ]);
        break;
    case 'userPhoto':
        $APPLICATION->IncludeComponent('mobium:profile', '.default', [
            'method'=>'user_photo'
        ]);
        break;
}
