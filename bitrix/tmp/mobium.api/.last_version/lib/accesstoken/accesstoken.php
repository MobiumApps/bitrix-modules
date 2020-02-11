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
                'title'=>GetMessage("MOBIUM_API_TOKEN"),
                'required'=>true,
            ]),
            new Entity\IntegerField('CREATED_AT', [
                'title'=>'Timestamp '.GetMessage("MOBIUM_API_SOZDANIA"),
                'required'=>true,
            ]),
            new Entity\IntegerField('LIFETIME', [
                'title'=>GetMessage("MOBIUM_API_VREMA_JIZNI"),
                'required'=>true
            ]),
            new Entity\StringField('TYPE', [
                'title'=>GetMessage("MOBIUM_API_TIP"),
                'required'=>true
            ]),
            new Entity\IntegerField('USER_ID', [
                'title'=>'ID '.GetMessage("MOBIUM_API_POLQZOVATELA"),
                'required'=>true
            ])
        ];
    }
}
