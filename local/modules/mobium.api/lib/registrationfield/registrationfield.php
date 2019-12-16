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
                'title'=>'����',
                'required'=>true,
                'size'=>100,
            ]),
            new Entity\BooleanField('EDITABLE', [
                'values'=>['N', 'Y'],
                'default'=>'Y',
                'title'=>'������������� ����',
            ]),


            new Entity\BooleanField('REGISTER_ACTIVE', [
                'values'=>['N', 'Y'],
                'default'=>'N',
                'title'=>'���������',
            ]),
            new Entity\BooleanField('REGISTER_REQUIRED', [
                'values'=>['N', 'Y'],
                'default'=>'N',
                'title'=>'������������',
            ]),
            new Entity\IntegerField('REGISTER_SORT', [
                'title'=>'����������',
                'default'=>500,
            ]),
            new Entity\StringField('REGISTER_TYPE', [
                'required'=>false,
                'title'=>'��� ����',
                'size'=>30,
            ]),
            new Entity\StringField('REGISTER_TITLE', [
                'required'=>false,
                'title'=>'���������',
                'size'=>255,
            ]),


            new Entity\BooleanField('VERIFICATION_ACTIVE', [
                'values'=>['N', 'Y'],
                'default'=>'N',
                'title'=>'���������',
            ]),
            new Entity\IntegerField('VERIFICATION_TIME', [
                'title'=>'����� �� ���������� �������',
            ]),
            new Entity\StringField('VERIFICATION_TEXT', [
                'size'=>255,
                'title'=>'����� ������ ���������',
            ]),
            new Entity\StringField('VERIFICATION_TYPE', [
                'size'=>30,
                'title'=>'��� ����',
            ]),
            new Entity\StringField('VERIFICATION_DRIVER', [
                'size'=>30,
                'title'=>'������ �����������',
            ]),


            new Entity\BooleanField('PROFILE_ACTIVE', [
                'values'=>['N','Y'],
                'default'=>'N',
                'title'=>'����������',
            ]),
            new Entity\IntegerField('PROFILE_SORT', [
                'title'=>'����������',
                'default'=>500,
            ]),
            new Entity\StringField('PROFILE_TITLE', [
                'required'=>false,
                'title'=>'���������',
                'size'=>30,
            ]),
            new Entity\StringField('PROFILE_TYPE', [
                'required'=>false,
                'title'=>'��� ����',
                'size'=>30,
            ]),
            new Entity\StringField('PROFILE_ACTION', [
                'required'=>false,
                'title'=>'�����',
                'size'=>30,
            ]),
            new Entity\StringField('PROFILE_ACTION_PARAM', [
                'required'=>false,
                'title'=>'��������� ������',
                'size'=>30,
            ]),

            new Entity\BooleanField('RESTORE_ACTIVE', [
                'values'=>['N','Y'],
                'default'=>'N',
                'title'=>'����������',
            ]),
            new Entity\IntegerField('RESTORE_SORT', [
                'title'=>'����������',
                'default'=>500,
            ]),
        ];
    }
}
