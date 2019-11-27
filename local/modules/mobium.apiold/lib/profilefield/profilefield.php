<?

namespace Mobium\Api\ProfileField;

use Bitrix\Main,
    Bitrix\Main\Entity,
    Bitrix\Main\Entity\DataManager,
    Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class ProfileFieldTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'mobium_profile_fields';
    }

    /**
     * @return array
     */
    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary'=>true,
                'autocomplete'=>true,
            ]),
            new Entity\StringField('SLUG', [
                'title'=>'Слаг',
                'required'=>true,
                'size'=>100,
            ]),
            new Entity\BooleanField('ACTIVE', [
                'values'=>['N', 'Y'],
                'default'=>'Y',
                'title'=>'Активность',
            ]),
            new Entity\StringField('NAME', [
                'required'=>true,
                'title'=>'Название',
                'size'=>255,
            ]),
            new Entity\StringField('TYPE', [
                'required'=>true,
                'title'=>'Тип',
                'size'=>100
            ]),
            new Entity\BooleanField('REQUIRED', [
                'title'=>'Обязательное поле',
                'value'=>['N', 'Y']
            ]),
            new Entity\BooleanField('NEED_VERIFICATION', [
                'title'=>'Нужна верификация',
                'value'=>['N', 'Y'],
            ]),
            new Entity\BooleanField('EDITABLE', [
                'title'=>'Редактируемое',
                'value'=>['N', 'Y']
            ]),
            new Entity\StringField('CODE_INPUT_TYPE', [
                'title'=>'Тип поля ввода',
                'size'=>255,
            ]),
            new Entity\StringField('TEXT', [
                'title'=>'Текст для экрана верификации',
                'size'=>255,
            ])
        ];
    }
}
