<?php

namespace Mobium\Api\DeliveryType\AdminInterface;

use Bitrix\Main\Localization\Loc,
    DigitalWand\AdminHelper\Helper\AdminInterface,
    DigitalWand\AdminHelper\Widget\NumberWidget,
    DigitalWand\AdminHelper\Widget\StringWidget,
    DigitalWand\AdminHelper\Widget\CheckboxWidget,
    DigitalWand\AdminHelper\Widget\FileWidget,
    DigitalWand\AdminHelper\Widget\VisualEditorWidget,
    DigitalWand\AdminHelper\Widget\TextAreaWidget;
use DigitalWand\AdminHelper\Widget\ComboBoxWidget;
use Mobium\Api\ApiHelper;


Loc::loadMessages(__FILE__);


class DeliveryTypeAdminInterface extends AdminInterface
{

    /**
     * {@inheritdoc}
     */
    public function fields(){
        $aMain = [
            'ID'=>[
                'WIDGET'=> new NumberWidget(),
                'READONLY'=>true,
                'FILTER'=>true,
                'HIDE_WHEN_CREATE'=>true
            ],
            'ACTIVE'=>[
                'WIDGET'=> new CheckboxWidget(),
                'DEFAULT'=>'N',
                'FIELD_TYPE'=>CheckboxWidget::TYPE_STRING
            ],
            'DELIVERY_SERVICE_ID_BITRIX'=>[
                'WIDGET'=>new ComboBoxWidget(),
                'VARIANTS'=>ApiHelper::getBitrixDeliveries()
            ],
            'DELIVERY_SERVICE_ID_MOBIUM'=>[
                'WIDGET'=> new ComboBoxWidget(),
                'VARIANTS'=>ApiHelper::getMobiumDeliveries()
            ],
            'DELIVERY_SERVICE_AREA_ID'=>[
                'WIDGET'=> new ComboBoxWidget(),
                'VARIANTS'=>ApiHelper::getMobiumDeliveryAreas()
            ],

        ];

        return [
            'MAIN'=>[
                'NAME'=>GetMessage("MOBIUM_API_OSNOVNYE"),
                'FIELDS'=>$aMain
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function helpers()
    {
        return array(
            '\Mobium\Api\DeliveryType\AdminInterface\DeliveryTypeListHelper',
            '\Mobium\Api\DeliveryType\AdminInterface\DeliveryTypeEditHelper',
        );
    }
}