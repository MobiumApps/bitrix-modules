<?php

namespace Mobium\Api\AccessToken\AdminInterface;

use Bitrix\Main\Localization\Loc,
    Kelnik\AdminHelper\Helper\AdminInterface,
    Kelnik\AdminHelper\Widget\NumberWidget,
    Kelnik\AdminHelper\Widget\StringWidget,
    Kelnik\AdminHelper\Widget\CheckboxWidget,
    Kelnik\AdminHelper\Widget\FileWidget,
    Kelnik\AdminHelper\Widget\VisualEditorWidget,
    Kelnik\AdminHelper\Widget\TextAreaWidget;


Loc::loadMessages(__FILE__);


class AccessTokenAdminInterface extends AdminInterface
{

    /**
     * {@inheritdoc}
     */
    public function fields(){

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function helpers()
    {
        return array(
            '\Mobium\Api\AccessToken\AdminInterface\AccessTokenListHelper',
            '\Mobium\Api\AccessToken\AdminInterface\AccessTokenEditHelper',
        );
    }
}