<?

namespace Mobium\Api\ProductsExportProps;

use Bitrix\Main,
    Bitrix\Main\Entity,
    Bitrix\Main\Entity\DataManager,
    Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class ProductsExportPropsTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'mobium_products_export_props';
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
                'title'=>'Свойство товара',
            ]),
            new Entity\BooleanField('EXPORT_PROP', [
                'values'=>['N', 'Y'],
                'default'=>'Y',
                'title'=>'Выводить в списке характеристик',
            ]),
            new Entity\StringField('EXPORT_NAME', [
                'title'=>'Имя в списке характеристик',
                'size'=>255,
            ]),
            new Entity\IntegerField('EXPORT_SORT', [
                'title'=>'Сортировка в списке характеристик',
                'default'=>500,
            ]),
            new Entity\BooleanField('PROP_IS_VENDOR_CODE', [
                'values'=>['N', 'Y'],
                'default'=>'Y',
                'title'=>'Свойство является артикулом',
            ]),
            new Entity\BooleanField('PROP_IS_TOOLTIP', [
                'values'=>['N', 'Y'],
                'default'=>'Y',
                'title'=>'Свойство является баджем',
            ]),
            new Entity\TextField('TOOLTIP_OPTIONS', array(
                'serialized' => true,
                'title'=>'Опции баджей'
            ))
        ];
    }
}
