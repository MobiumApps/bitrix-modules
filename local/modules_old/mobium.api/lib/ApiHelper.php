<?php
namespace Mobium\Api;


use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;

class ApiHelper
{


    /**
     * @return bool
     */
    public static function authorizeByHeader()
    {
        if (!empty($sToken = $_SERVER['REMOTE_USER'] ?? '')){
            try {
                return static::authorizeToken($sToken);
            } catch (\Exception $e) {
                return false;
            }
        }
        return false;
    }

    /**
     * @param string $sToken
     * @param bool $bCheckExpire
     * @return bool
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function authorizeToken($sToken, $bCheckExpire=false)
    {
        if (!empty($sToken)) {
            $oResult = \Mobium\Api\AccessToken\AccessTokenTable::getList([
                'filter' => [
                    'BODY' => $sToken,
                    'TYPE' => 'auth'
                ]
            ]);
            $aTokenResult = $oResult->fetchAll();
            if (count($aTokenResult) === 1) {
                $aTokenResult = $aTokenResult [0];
                if ($bCheckExpire && ((int) ($aTokenResult['CREATED_AT'] + (int) $aTokenResult['LIFETIME']) < time())){
                    return false;
                }
                /**@var \CUser $USER */
                global $USER;
                $USER->Authorize($aTokenResult ['USER_ID']);
                return true;
            }
        }
        return false;
    }

    /**
     * @param $sId
     * @return mixed|null
     */
    public static function getProfileFieldValue($sId){
        /**@var \CUser $USER */
        global $USER;
        switch ($sId){
            case 'name':
                return $USER->GetFullName();
            case 'email':
                return $USER->GetEmail();
            case 'phone':
                $oUserResult = \CUser::GetByID($USER->GetID());
                if ($aUser = $oUserResult->Fetch()){
                    return $aUser['PERSONAL_PHONE'];
                }
                return $USER->GetParam('PERSONAL_PHONE');
            case 'login':
                return $USER->GetLogin();
            case 'bonuses':
                return static::getCurrentUserBonuses();
            case 'barcode':
                return '';
            default:
                return null;

        }

    }

    /**
     * @param $sId
     * @param int $mValue
     * @return mixed|null
     */
    public static function setProfileFieldValue($sId, $mValue){
        /**@var \CUser $USER */
        global $USER;
        switch ($sId){
            case 'name':
                return $USER->Update($USER->GetID(), [
                    'NAME'=>$mValue
                ]);
            case 'email':
                return $USER->Update($USER->GetID(), [
                    'EMAIL'=>$mValue
                ]);
            case 'phone':
                $USER->SetParam('PERSONAL_PHONE', $mValue);
                return $USER->Update($USER->GetID(), [
                    'PERSONAL_PHONE' => $mValue
                ]);
            case 'login':
                return false;
            case 'bonuses':
                return static::setCurrentUserBonuses($mValue);
            case 'barcode':
                return '';
            default:
                return null;

        }

    }

    /**
     * @param int $iValue
     * @return bool
     */
    public static function setCurrentUserBonuses($iValue){
        /**@var \CUser $USER */
        global $USER;
        return $USER->Update($USER->GetID(), [
            'UF_LOGICTIM_BONUS' => $iValue
        ]);
    }

    /**
     * @return int
     */
    public static function getCurrentUserBonuses(){
        /**@var \CUser $USER */
        global $USER;
        $by = 'timestamp_x'; $o = 'desc';
        $oResult = \CUser::GetList($by, $o, ['ID'=>$USER->GetID()], ['SELECT'=>['UF_LOGICTIM_BONUS']]);
        $aUserData = $oResult->Fetch();
        return (int) $aUserData['UF_LOGICTIM_BONUS'] ?? 0;
    }
}