<?

namespace Mobium\Api\DeliveryType;

use Bitrix\Main,
    Bitrix\Main\Entity,
    Bitrix\Main\Entity\DataManager,
    Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class DeliveryTypeTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'mobium_delivery_type_assoc';
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
            new Entity\BooleanField('ACTIVE', [
                'values'=>['N', 'Y'],
                'default'=>'Y',
                'title'=>GetMessage("MOBIUM_API_AKTIVNOSTQ"),
            ]),
            new Entity\StringField('DELIVERY_SERVICE_ID_BITRIX', [
                'title'=>GetMessage("MOBIUM_API_SLUJBA_DOSTAVKI"),
                'required'=>true,
                'size'=>100,
            ]),
            new Entity\StringField('DELIVERY_SERVICE_ID_MOBIUM', [
                'title'=>GetMessage("MOBIUM_API_SLUJBA_DOSTAVKI1"),
                'required'=>true,
                'size'=>100
            ]),
            new Entity\StringField('DELIVERY_SERVICE_AREA_ID', [
                'title'=>GetMessage("MOBIUM_API_ZONA_DOSTAVKI"),
                'required'=>false,
                'size'=>100
            ])
        ];
    }
}
