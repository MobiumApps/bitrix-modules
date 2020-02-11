<?

namespace Mobium\Api\OffersExportProps;

use Bitrix\Main,
    Bitrix\Main\Entity,
    Bitrix\Main\Entity\DataManager,
    Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class OffersExportPropsTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'mobium_offers_export_props';
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
            new Entity\IntegerField('EXPORT_PROP_ID', [
                'required'=>true,
                'title'=>GetMessage("MOBIUM_API_SVOYSTVO_TP"),
            ]),
            new Entity\BooleanField('EXPORT_PROP', [
                'values'=>['N', 'Y'],
                'default'=>'Y',
                'title'=>GetMessage("MOBIUM_API_VYVODITQ_V_SPISKE_HA"),
            ]),
            new Entity\StringField('EXPORT_NAME', [
                'title'=>GetMessage("MOBIUM_API_IMA_V_SPISKE_HARAKTE"),
                'size'=>255,
            ]),
            new Entity\IntegerField('EXPORT_SORT', [
                'title'=>GetMessage("MOBIUM_API_SORTIROVKA_V_SPISKE"),
                'default'=>500,
            ]),
            new Entity\BooleanField('PROP_IS_VENDOR_CODE', [
                'values'=>['N', 'Y'],
                'default'=>'Y',
                'title'=>GetMessage("MOBIUM_API_SVOYSTVO_AVLAETSA_AR"),
            ]),
            new Entity\BooleanField('PROP_IS_TOOLTIP', [
                'values'=>['N', 'Y'],
                'default'=>'Y',
                'title'=>GetMessage("MOBIUM_API_SVOYSTVO_AVLAETSA_BA"),
            ]),
            new Entity\TextField('TOOLTIP_OPTIONS', array(
                'serialized' => true,
                'title'=>GetMessage("MOBIUM_API_OPCII_BADJEY")
            ))
        ];
    }
}
