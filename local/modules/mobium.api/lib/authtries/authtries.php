<?

namespace Mobium\Api\AuthTries;

use Bitrix\Main,
    Bitrix\Main\Entity,
    Bitrix\Main\Entity\DataManager,
    Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class AuthTriesTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'mobium_auth_tries';
    }

    /**
     * @return array
     */
    public static function getMap()
    {
        return [
            new Entity\IntegerField('APP_ID', [
                'primary'=>true
            ]),
			new Entity\TextField('DATA', [
				'serialized' => true,
				'title'=>'Data'
			]),
        ];
    }
}
