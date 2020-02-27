<?php

namespace Mobium\Api\OffersExportProps\AdminInterface;

use Bitrix\Main\Localization\Loc,
    DigitalWand\AdminHelper\Helper\AdminInterface,
    DigitalWand\AdminHelper\Widget\NumberWidget,
    DigitalWand\AdminHelper\Widget\StringWidget,
    DigitalWand\AdminHelper\Widget\CheckboxWidget,
    DigitalWand\AdminHelper\Widget\FileWidget,
    DigitalWand\AdminHelper\Widget\VisualEditorWidget,
    DigitalWand\AdminHelper\Widget\ComboBoxWidget,
    DigitalWand\AdminHelper\Widget\TextAreaWidget;
use Mobium\Api\ApiHelper;
use Mobium\Api\Widgets\TooltipOptionsWidget;


Loc::loadMessages(__FILE__);


class OffersExportPropsAdminInterface extends AdminInterface
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
            'EXPORT_PROP_ID'=>[
                'WIDGET'=>new ComboBoxWidget(),
                'VARIANTS'=>ApiHelper::getOfferProps(),
                'REQUIRED'=>true,
            ],
            'EXPORT_PROP'=>[
                'WIDGET'=> new CheckboxWidget(),
                'DEFAULT'=>'Y',
                'FIELD_TYPE'=>CheckboxWidget::TYPE_STRING
            ],
            'EXPORT_NAME'=>[
                'WIDGET'=> new StringWidget(),
                'SIZE'=>80,
                'FILTER'=>'%',
            ],
            'EXPORT_SORT'=>[
                'WIDGET'=> new StringWidget(),
                'SIZE'=>80,
                'FILTER'=>'%',
            ],
            'PROP_IS_VENDOR_CODE'=>[
                'WIDGET'=> new CheckboxWidget(),
                'DEFAULT'=>'N',
                'FIELD_TYPE'=>CheckboxWidget::TYPE_STRING
            ],
            'PROP_IS_TOOLTIP'=>[
                'WIDGET'=> new CheckboxWidget(),
                'DEFAULT'=>'N',
                'FIELD_TYPE'=>CheckboxWidget::TYPE_STRING
            ],
            'TOOLTIP_OPTIONS'=>[
                'WIDGET'=> new TooltipOptionsWidget(),
                'SIZE'=>80,
                'FILTER'=>'%',
            ],
        ];
        return [
            'MAIN'=>[
                'NAME'=>'Основные',
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
            '\Mobium\Api\OffersExportProps\AdminInterface\OffersExportPropsListHelper',
            '\Mobium\Api\OffersExportProps\AdminInterface\OffersExportPropsEditHelper',
        );
    }
}