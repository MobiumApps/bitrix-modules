<?php

namespace Mobium\Api\ProfileField\AdminInterface;

use Bitrix\Main\Localization\Loc,
    DigitalWand\AdminHelper\Helper\AdminInterface,
    DigitalWand\AdminHelper\Widget\NumberWidget,
    DigitalWand\AdminHelper\Widget\StringWidget,
    DigitalWand\AdminHelper\Widget\CheckboxWidget,
    DigitalWand\AdminHelper\Widget\FileWidget,
    DigitalWand\AdminHelper\Widget\VisualEditorWidget,
    DigitalWand\AdminHelper\Widget\TextAreaWidget;
use DigitalWand\AdminHelper\Widget\ComboBoxWidget;


Loc::loadMessages(__FILE__);


class ProfileFieldAdminInterface extends AdminInterface
{

    /**
     * {@inheritdoc}
     */
    public function fields(){
    }

    /**
     * {@inheritdoc}
     */
    public function helpers()
    {
        return array(
            '\Mobium\Api\ProfileField\AdminInterface\ProfileFieldListHelper',
            '\Mobium\Api\ProfileField\AdminInterface\ProfileFieldEditHelper',
        );
    }
}