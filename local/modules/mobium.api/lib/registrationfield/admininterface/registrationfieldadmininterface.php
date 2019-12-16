<?php

namespace Mobium\Api\RegistrationField\AdminInterface;

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


class RegistrationFieldAdminInterface extends AdminInterface
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
            'SLUG'=>[
                'WIDGET'=> new StringWidget(),
                'SIZE'=>80,
                'FILTER'=>'%',
                'REQUIRED'=>true,
            ],
            'EDITABLE'=>[
                'WIDGET'=> new CheckboxWidget(),
                'DEFAULT'=>'N',
                'FIELD_TYPE'=>CheckboxWidget::TYPE_STRING
            ],
        ];

        $aRegister = [
            'REGISTER_ACTIVE'=>[
                'WIDGET'=> new CheckboxWidget(),
                'DEFAULT'=>'N',
                'FIELD_TYPE'=>CheckboxWidget::TYPE_STRING
            ],
            'REGISTER_REQUIRED'=>[
                'WIDGET'=> new CheckboxWidget(),
                'DEFAULT'=>'N',
                'FIELD_TYPE'=>CheckboxWidget::TYPE_STRING
            ],
            'REGISTER_SORT'=>[
                'WIDGET'=> new StringWidget(),
                'SIZE'=>80,
                'FILTER'=>'%',
            ],
            'REGISTER_TITLE'=>[
                'WIDGET'=> new StringWidget(),
                'SIZE'=>80,
                'FILTER'=>'%',
            ],
            'REGISTER_TYPE'=>[
                'WIDGET'=> new ComboBoxWidget(),
                'SIZE'=>80,
                'FILTER'=>'%',
                'VARIANTS'=>[
                    'text'=>'�����',
                    'email'=>'Email',
                    'password'=>'������',
                    'phone'=>'�������',
                    'sex_select'=>'���� ����',
                    'date_picker'=>'����',
                ]
            ],
        ];

        $aVerification = [
            'VERIFICATION_ACTIVE'=>[
                'WIDGET'=> new CheckboxWidget(),
                'DEFAULT'=>'N',
                'FIELD_TYPE'=>CheckboxWidget::TYPE_STRING
            ],
            'VERIFICATION_TIME'=>[
                'WIDGET'=> new StringWidget(),
                'SIZE'=>80,
                'FILTER'=>'%',
            ],
            'VERIFICATION_TEXT'=>[
                'WIDGET'=> new StringWidget(),
                'SIZE'=>80,
                'FILTER'=>'%',
            ],
            'VERIFICATION_TYPE'=>[
                'WIDGET'=> new ComboBoxWidget(),
                'SIZE'=>80,
                'FILTER'=>'%',
                'VARIANTS'=>[
                    'text'=>'�����',
                    'email'=>'Email',
                    'password'=>'������',
                    'phone'=>'�������',
                ]
            ],
            'VERIFICATION_DRIVER'=>[
                'WIDGET'=> new ComboBoxWidget(),
                'SIZE'=>80,
                'FILTER'=>'%',
                'VARIANTS'=>[
                    'sms'=>'SMS',
                    'email'=>'Email',
                ]
            ],
        ];

        $aProfile = [
            'PROFILE_ACTIVE'=>[
                'WIDGET'=> new CheckboxWidget(),
                'DEFAULT'=>'N',
                'FIELD_TYPE'=>CheckboxWidget::TYPE_STRING
            ],
            'PROFILE_SORT'=>[
                'WIDGET'=> new StringWidget(),
                'SIZE'=>80,
                'FILTER'=>'%',
            ],
            'PROFILE_TITLE'=>[
                'WIDGET'=> new StringWidget(),
                'SIZE'=>80,
                'FILTER'=>'%',
            ],
            'PROFILE_TYPE'=>[
                'WIDGET'=> new ComboBoxWidget(),
                'SIZE'=>80,
                'FILTER'=>'%',
                'VARIANTS'=>[
                    'name_field'=>'������ �� �����',
                    'image_action_field'=>'����������� � ��������',
                    'title_text_field'=>'��������� � ��������',
                    'text_field'=>'�����',
                    'action_field'=>'��������',
                    'bonus_field'=>'������',
                    'barcode_field'=>'�����-���',
                ]
            ],
            'PROFILE_ACTION'=>[
                'WIDGET'=> new ComboBoxWidget(),
                'SIZE'=>80,
                'FILTER'=>'%',
                'VARIANTS'=>[
                    'openCategory' => '������� ���������',
                    'openCatalog' => '������� �������',
                    'openProduct' => '������� �����',
                    'openSearch' => '������� �����',
                    'openUrl' => '������ � ����������',
                    'openUrlExternal' => '������ � ��������',
                    'doCall' => '������',
                    'openCart' => '������� �������',
                    'openMainScreen' => '������� ������� �����',
                    'openShops' => '������� �����',
                    'openHistory' => '������� ������� �������',
                    'openArticles' => '������� ������',
                    //'openCatalogInsideMenu' => false,
                    'openFavourites' => '������� ���������',
                    'openForm' => '������� ����� �������� �����',
                    'openGallery' => '������� �������',
                    //'openProfile' => false,
                ]
            ],
            'PROFILE_ACTION_PARAM'=>[
                'WIDGET'=> new TextAreaWidget(),
                'SIZE'=>80,
                'FILTER'=>'%',
            ],

        ];

        $aRestore = [
            'RESTORE_ACTIVE'=>[
                'WIDGET'=> new CheckboxWidget(),
                'DEFAULT'=>'N',
                'FIELD_TYPE'=>CheckboxWidget::TYPE_STRING
            ],
            'RESTORE_SORT'=>[
                'WIDGET'=> new StringWidget(),
                'SIZE'=>80,
                'FILTER'=>'%',
            ],
        ];
        return [
            'MAIN'=>[
                'NAME'=>'��������',
                'FIELDS'=>$aMain
            ],
            'REGISTER'=>[
                'NAME'=>'����� �����������',
                'FIELDS'=>$aRegister,
            ],
            'VERIFICATION'=>[
                'NAME'=>'�����������',
                'FIELDS'=>$aVerification,
            ],
            'PROFILE'=>[
                'NAME'=>'������� ������������',
                'FIELDS'=>$aProfile,
            ],
            'RESTORE'=>[
                'NAME'=>'�������������� ������',
                'FIELDS'=>$aRestore
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function helpers()
    {
        return array(
            '\Mobium\Api\RegistrationField\AdminInterface\RegistrationFieldListHelper',
            '\Mobium\Api\RegistrationField\AdminInterface\RegistrationFieldEditHelper',
        );
    }
}