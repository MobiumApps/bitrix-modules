<?php
namespace Mobium\Api;


class EventHandler
{
    public static function onAfterUserRegister(&$aFields){
        //var_dump($aFields);
        return $aFields;
    }
}