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
                'title'=>'Активность',
            ]),
            new Entity\StringField('DELIVERY_SERVICE_ID_BITRIX', [
                'title'=>'Служба доставки Bitrix',
                'required'=>true,
                'size'=>100,
            ]),
            new Entity\StringField('DELIVERY_SERVICE_ID_MOBIUM', [
                'title'=>'Служба доставки Mobium',
                'required'=>true,
                'size'=>100
            ]),
            new Entity\StringField('DELIVERY_SERVICE_AREA_ID', [
                'title'=>'Зона доставки Mobium',
                'required'=>false,
                'size'=>100
            ])
        ];
    }
}
