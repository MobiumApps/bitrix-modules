<?

namespace Mobium\Api\Verify;

use Bitrix\Main,
    Bitrix\Main\Entity,
    Bitrix\Main\Entity\DataManager,
    Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class VerifyTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'mobium_verify';
    }

	public static function getObjectClass()
	{
		return verify::class;
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
            new Entity\StringField('CODE', [
                'size'=>50,
                'title'=>GetMessage("MOBIUM_API_TOKEN"),
                'required'=>true,
            ]),
			new Entity\DatetimeField('CREATED_AT', [
				'title'=>GetMessage("MOBIUM_API_VREMA_SOZDANIA"),
				'required'=>true,
				'default_value' => new \Bitrix\Main\Type\Datetime
			]),
            new Entity\IntegerField('LIFETIME', [
                'title'=>GetMessage("MOBIUM_API_VREMA_JIZNI"),
                'required'=>true
            ]),
            new Entity\IntegerField('APP_ID', [
                'title'=>'ID '.GetMessage("MOBIUM_API_PRILOGENIA"),
                'required'=>true
            ]),
            new Entity\IntegerField('USER_ID', [
                'title'=>'ID '.GetMessage("MOBIUM_API_POLQZOVATELA"),
                'required'=>true
            ])
        ];
    }
}
