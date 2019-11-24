<?php
namespace Mobium\Api;

use Bitrix\Main\Config\Option;
class OptionsHelper extends Option
{

    public function changeOptions($module, $config, $value){
        parent::$options['-'][$module][$config] = $value;
    }
}