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
                    'text'=>'Текст',
                    'email'=>'Email',
                    'password'=>'Пароль',
                    'phone'=>'Телефон',
                    'sex_select'=>'Выбо пола',
                    'date_picker'=>'Дата',
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
                    'text'=>'Текст',
                    'email'=>'Email',
                    'password'=>'Пароль',
                    'phone'=>'Телефон',
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
                    'name_field'=>'Данные об имени',
                    'image_action_field'=>'Изображение и действие',
                    'title_text_field'=>'Заголовок и значение',
                    'text_field'=>'Текст',
                    'action_field'=>'Действие',
                    'bonus_field'=>'Бонусы',
                    'barcode_field'=>'Штрих-код',
                ]
            ],
            'PROFILE_ACTION'=>[
                'WIDGET'=> new ComboBoxWidget(),
                'SIZE'=>80,
                'FILTER'=>'%',
                'VARIANTS'=>[
                    'openCategory' => 'Открыть категорию',
                    'openCatalog' => 'Открыть каталог',
                    'openProduct' => 'Открыть товар',
                    'openSearch' => 'Открыть поиск',
                    'openUrl' => 'Ссылка в приложении',
                    'openUrlExternal' => 'Ссылка в браузере',
                    'doCall' => 'Звонок',
                    'openCart' => 'Открыть корзину',
                    'openMainScreen' => 'Открыть главный экран',
                    'openShops' => 'Открыть карту',
                    'openHistory' => 'Открыть историю заказов',
                    'openArticles' => 'Открыть статью',
                    //'openCatalogInsideMenu' => false,
                    'openFavourites' => 'Открыть избранное',
                    'openForm' => 'Открыть форму обратной связи',
                    'openGallery' => 'Открыть галерею',
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
                'NAME'=>'Основные',
                'FIELDS'=>$aMain
            ],
            'REGISTER'=>[
                'NAME'=>'Форма регистрации',
                'FIELDS'=>$aRegister,
            ],
            'VERIFICATION'=>[
                'NAME'=>'Верификация',
                'FIELDS'=>$aVerification,
            ],
            'PROFILE'=>[
                'NAME'=>'Профиль пользователя',
                'FIELDS'=>$aProfile,
            ],
            'RESTORE'=>[
                'NAME'=>'Восстановление пароля',
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