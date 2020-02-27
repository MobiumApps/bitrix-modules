<?

namespace Mobium\Api\RegistrationField;

use Bitrix\Main,
    Bitrix\Main\Entity,
    Bitrix\Main\Entity\DataManager,
    Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class RegistrationFieldTable extends DataManager
{

//    public static function extract($aModel, $aKeys = []) {
//        $aResult =  [
//            'id'=>$aModel['SLUG'],
//            'type'=>$aModel['TYPE'],
//            'title'=>$aField['NAME'],
//            'required'=>$aField['REQUIRED'] === 'Y',
//            'need_verification'=>$aField['NEED_VERIFICATION'] === 'Y',
//            'editable'=>($aField['EDITABLE'] ?? 'N') === 'Y'
//        ];
//    }

    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'mobium_registration_fields';
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
                'title'=>GetMessage("MOBIUM_API_SLAG"),
                'required'=>true,
                'size'=>100,
            ]),
            new Entity\BooleanField('EDITABLE', [
                'values'=>['N', 'Y'],
                'default'=>'Y',
                'title'=>GetMessage("MOBIUM_API_REDAKTIRUEMOE_POLE"),
            ]),


            new Entity\BooleanField('REGISTER_ACTIVE', [
                'values'=>['N', 'Y'],
                'default'=>'N',
                'title'=>GetMessage("MOBIUM_API_AKTIVNOTQ"),
            ]),
            new Entity\BooleanField('REGISTER_REQUIRED', [
                'values'=>['N', 'Y'],
                'default'=>'N',
                'title'=>GetMessage("MOBIUM_API_OBAZATELQNOE"),
            ]),
            new Entity\IntegerField('REGISTER_SORT', [
                'title'=>GetMessage("MOBIUM_API_SORTIROVKA"),
                'default'=>500,
            ]),
            new Entity\StringField('REGISTER_TYPE', [
                'required'=>false,
                'title'=>GetMessage("MOBIUM_API_TIP_POLA"),
                'size'=>30,
            ]),
            new Entity\StringField('REGISTER_TITLE', [
                'required'=>false,
                'title'=>GetMessage("MOBIUM_API_ZAGOLOVOK"),
                'size'=>255,
            ]),


            new Entity\BooleanField('VERIFICATION_ACTIVE', [
                'values'=>['N', 'Y'],
                'default'=>'N',
                'title'=>GetMessage("MOBIUM_API_AKTIVNOTQ"),
            ]),
            new Entity\IntegerField('VERIFICATION_TIME', [
                'title'=>GetMessage("MOBIUM_API_VREMA_DO_POVTORNOGO"),
            ]),
            new Entity\StringField('VERIFICATION_TEXT', [
                'size'=>255,
                'title'=>GetMessage("MOBIUM_API_TEKST_EKRANA_AKTIVAC"),
            ]),
            new Entity\StringField('VERIFICATION_TYPE', [
                'size'=>30,
                'title'=>GetMessage("MOBIUM_API_TIP_POLA"),
            ]),
            new Entity\StringField('VERIFICATION_DRIVER', [
                'size'=>30,
                'title'=>GetMessage("MOBIUM_API_SPOSOB_VERIFIKACII"),
            ]),


            new Entity\BooleanField('PROFILE_ACTIVE', [
                'values'=>['N','Y'],
                'default'=>'N',
                'title'=>GetMessage("MOBIUM_API_AKTIVNOSTQ"),
            ]),
            new Entity\IntegerField('PROFILE_SORT', [
                'title'=>GetMessage("MOBIUM_API_SORTIROVKA"),
                'default'=>500,
            ]),
            new Entity\StringField('PROFILE_TITLE', [
                'required'=>false,
                'title'=>GetMessage("MOBIUM_API_ZAGOLOVOK"),
                'size'=>30,
            ]),
            new Entity\StringField('PROFILE_TYPE', [
                'required'=>false,
                'title'=>GetMessage("MOBIUM_API_TIP_POLA"),
                'size'=>30,
            ]),
            new Entity\StringField('PROFILE_ACTION', [
                'required'=>false,
                'title'=>GetMessage("MOBIUM_API_EKSEN"),
                'size'=>30,
            ]),
            new Entity\StringField('PROFILE_ACTION_PARAM', [
                'required'=>false,
                'title'=>GetMessage("MOBIUM_API_PARAMETRY_EKSENA"),
                'size'=>30,
            ]),

            new Entity\BooleanField('RESTORE_ACTIVE', [
                'values'=>['N','Y'],
                'default'=>'N',
                'title'=>GetMessage("MOBIUM_API_AKTIVNOSTQ"),
            ]),
            new Entity\IntegerField('RESTORE_SORT', [
                'title'=>GetMessage("MOBIUM_API_SORTIROVKA"),
                'default'=>500,
            ]),
        ];
    }
}
