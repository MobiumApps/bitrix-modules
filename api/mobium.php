<?php
require_once ($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
$sMethod = trim($_GET['method']);
global $APPLICATION;

switch ($sMethod){
    case 'getRegistrationFields':
        $APPLICATION->IncludeComponent('mobium:profile', '.default', [
            'method'=>'registration_fields'
        ]);
        break;
    case 'userRegistration':
        $APPLICATION->IncludeComponent('mobium:user_registration', '.default', [
            'method'=>'user_register'
        ]);
        break;
    case 'userAuthorization':
    case 'userLogout':
        $APPLICATION->IncludeComponent('mobium:user_authorization', '.default');
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
}
