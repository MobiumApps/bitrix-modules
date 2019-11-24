<?

namespace Mobium\Api\AccessToken;

use Bitrix\Main,
    Bitrix\Main\Entity,
    Bitrix\Main\Entity\DataManager,
    Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class AccessTokenTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'mobium_user_token';
    }

    /**
     * @return array
     */
    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary'=>true,
                'autocomplete'=>true
            ]),
            new Entity\StringField('BODY', [
                'size'=>50,
                'title'=>'Токен',
                'required'=>true,
            ]),
            new Entity\IntegerField('CREATED_AT', [
                'title'=>'Timestamp создания',
                'required'=>true,
            ]),
            new Entity\IntegerField('LIFETIME', [
                'title'=>'Время жизни',
                'required'=>true
            ]),
            new Entity\StringField('TYPE', [
                'title'=>'Тип',
                'required'=>true
            ]),
            new Entity\IntegerField('USER_ID', [
                'title'=>'ID пользователя',
                'required'=>true
            ])
        ];
    }
}
