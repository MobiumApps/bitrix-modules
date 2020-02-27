<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();


class ProfileComponent extends CBitrixComponent{
    public function executeMain(){
        \Bitrix\Main\Loader::includeModule('mobium.api');
        var_dump(1);
        $this->arResult = \Mobium\Api\RegistrationField\RegistrationFieldTable::getList([
            'select'=>'*',
            'order'=>['SORT'=>'ASC']
        ]);
        var_dump($this->arResult);
    }
}