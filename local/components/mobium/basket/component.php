<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
use Bitrix\Main\Context,
    Bitrix\Currency\CurrencyManager,
    Bitrix\Sale\Order,
    Bitrix\Sale\Basket,
    Bitrix\Sale\Delivery,
    Bitrix\Sale\PaySystem;
use Bitrix\Sale\DiscountCouponsManager;
use Bitrix\Sale\Shipment;

/**
 * @var array $arParams
 */
\Bitrix\Main\Loader::includeModule('mobium.api');

if ($arParams['method'] === 'apply_code'){
    \Bitrix\Main\Loader::includeModule('sale');
    \Bitrix\Main\Loader::includeModule('catalog');
    $sSiteId = Context::getCurrent()->getSite();
    $aInputData = json_decode(file_get_contents('php://input'), true);
    if (!isset($aInputData['basket'], $aInputData['basket']['items'])){
        $this->arResult = [
            'status'=>1,
            'message'=>'Невалидная корзина'
        ];
        $this->IncludeComponentTemplate();
        exit();
    }

    $aOrderInfo = $aInputData['data']['order_info'];
    $aPaymentType = $aOrderInfo['payment_type'];


    /**@var CUser $USER */
    global $USER;
    if (!\Mobium\Api\ApiHelper::authorizeByHeader()){
        $sName = $aOrderInfo['name'] ?? null;
        $sPhone = $aOrderInfo['phone'] ?? null;
        $sEmail = $aOrderInfo['email'] ?? null;
        if (isset($aInputData['access_token']) && !empty($aInputData['access_token'])){
            $sToken = $aInputData['access_token'];
            \Mobium\Api\ApiHelper::authorizeToken($sToken);
        } elseif (null !== $sEmail) {
            $aRegisterResult = $USER->SimpleRegister($sEmail, $sSiteId);
            if ($aRegisterResult['TYPE'] !== 'OK' || !$USER->GetID()){
                $iAnonymousUserID = \CSaleUser::GetAnonymousUserID();
                $USER->Authorize($iAnonymousUserID);
            }
        } else {
            $iAnonymousUserID = \CSaleUser::GetAnonymousUserID();
            $USER->Authorize($iAnonymousUserID);
        }
    }
    if (!$USER->GetID()){
        $this->arResult = [
            'status'=>3,
            'message'=>'Ошибка при создании заказа'
        ];
        $this->IncludeComponentTemplate();
        exit();
    }




    $currencyCode = CurrencyManager::getBaseCurrency();

    $oBasket = createBasket($aInputData, $sSiteId, $currencyCode);
    $oOrder = Order::create($sSiteId, $USER->GetID() );

    /**
     * 1 - физическое лицо
     * 2 - Юр. лицо
     */
    $oOrder->setPersonTypeId(1);
    $oOrder->setField('CURRENCY', $currencyCode);
    if (isset($aOrderInfo['comment']) && !empty($aOrderInfo['comment'])) {
        $oOrder->setField('USER_DESCRIPTION', $aOrderInfo['comment']);
    }
    $oOrder->setBasket($oBasket);

    $oShipmentCollection = $oOrder->getShipmentCollection();
    //$oShipment = $oShipmentCollection->createItem();

    $aShipmentList = [
        'courier'=>1,
        '5b630e167a838'=>2, //Самовывоз
        '5b630e6545310'=>7, // EMS
        '5b630eaf6846a'=>48, // CDEK
        'DPD'=>50
    ];
    $aShipmentList = \Mobium\Api\ApiHelper::getDeliveryTypes();
    if ($aInputData['version'] == '2.0'){
        $sDeliveryTypeId = $aInputData['delivery']['type_id'];

    }

    $oTemp = Delivery\Services\Manager::getObjectById(2);
    $oShipment = $oShipmentCollection->createItem($oTemp);



    $oShipmentItemCollection = $oShipment->getShipmentItemCollection();
    foreach ($oBasket as $oBasketItem){
        $oItem = $oShipmentItemCollection->createItem($oBasketItem);
        $oItem->setQuantity($oBasketItem->getQuantity());
    }


    $aPaymentsSystemsList = [
        'cash'=>1,
        'yandex_money'=>2,
        'bank_cards'=>3,
        'terminals'=>4,
        'web_money'=>5,
        'sberbank_ticket'=>6,
        'bank_transfer'=>7,
        'internal_bill'=>8,
        'robokassa'=>9,
        'cod'=>10,
        'bonuses'=>11
    ];
    $oPaymentCollection = $oOrder->getPaymentCollection();
    $oPayment = $oPaymentCollection->createItem(PaySystem\Manager::getObjectById($aPaymentsSystemsList['cash']));
    $oPayment->setField('SUM', $oOrder->getPrice());
    $oPayment->setField('CURRENCY', $oOrder->getCurrency());
    /*
    $oPaySystemService = PaySystem\Manager::getObjectById($aPaymentsSystemsList['cash']);
    $oPayment->setFields([
        'PAY_SYSTEM_ID'=>$oPaySystemService->getField('PAY_SYSTEM_ID'),
        'PAY_SYSTEM_NAME'=>$oPaySystemService->getField('NAME'),
    ]);
    */



    $oPropertyCollection = $oOrder->getPropertyCollection();
    if (isset($aOrderInfo['phone'])){
        $oProp = $oPropertyCollection->getPhone();
        $oProp->setValue($aOrderInfo['phone']);
    }
    if (isset($aOrderInfo['name'])){
        $oProp = $oPropertyCollection->getPayerName();
        $oProp->setValue($aOrderInfo['name']);
    }
    if (isset($aOrderInfo['email'])){
        $oProp = $oPropertyCollection->getUserEmail();
        $oProp->setValue($aOrderInfo['email']);
    }

    $oOrder->doFinalAction(true);


    if (isset($aInputData['promoCode']) && !empty(trim($aInputData['promoCode']))){
        if (strpos(strtolower($aInputData['promoCode']), 'discount') !== false){
            $this->arResult = [
                'status'=>4,
                'message'=>'Промокод не действителен.'
            ];
            $this->IncludeComponentTemplate();
            exit();
        }
        $aData = \Bitrix\Sale\DiscountCouponsManager::getData($aInputData['promoCode']);
        if (is_array($aData) && (int)$aData['ID'] > 0){
            if ($aData['STATUS'] == DiscountCouponsManager::STATUS_APPLYED || $aData['STATUS'] == DiscountCouponsManager::STATUS_ENTERED ) {
                if (!\Bitrix\Sale\DiscountCouponsManager::add($aInputData['promoCode'])){
                    $this->arResult = [
                        'status'=>4,
                        'message'=>'Промокод не применен'
                    ];
                    $this->IncludeComponentTemplate();
                    exit();
                }
                $oOrder->getDiscount()->calculate();
                /*$discounts = $oOrder->getDiscount();
                $discounts->calculate();*/


            }
        } else{
            $this->arResult = [
                'status'=>4,
                'message'=>'Промокод не существует'
            ];
            $this->IncludeComponentTemplate();
            exit();
        }
    }
    \Bitrix\Sale\DiscountCouponsManager::init();
    $oOrder->doFinalAction(true);
    //$oResult = $oOrder->save();
    /** @var \Bitrix\Sale\BasketItem $item */
    /*foreach ( $oOrder->getBasket()->getBasketItems() as $item) {
        var_dump($item->getPrice());
    }
    var_dump($oOrder->getPrice());*/
    $this->arResult = [
        'status'=>0,
        'data'=>extractOrder($oOrder),
        //'d'=>$discounts
    ];
    /*if ($oResult->isSuccess()){
        $this->arResult = [
            'status'=>0,
            'data'=>['order_id'=>$oOrder->getId()],
        ];
    } else {
        $this->arResult = [
            'status'=>4,
            'message'=>'Ошибка при сохранении заказа',
            'd'=>$oResult->getErrorMessages()
        ];
    }*/

}

/**
 * @param array $aInputData
 * @param string $sSiteId
 * @param string $sCurrencyCode
 * @return \Bitrix\Sale\Basket
 */
function  createBasket($aInputData, $sSiteId, $sCurrencyCode){
    /** @var Basket $oBasket */
    $oBasket = Basket::create($sSiteId);
    $aItems = $aInputData['basket']['items'];
    foreach ($aItems as $aItem){
        $oItem = $oBasket->createItem('catalog', $aItem['id']);
        $oItem->setFields([
            'QUANTITY'=>$aItem['count'],
            'CURRENCY'=>$sCurrencyCode,
            'LID'=>$sSiteId,
            'PRODUCT_PROVIDER_CLASS' => '\CCatalogProductProvider',
        ]);
    }
    return $oBasket;
}

/**
 * @param Order $oOrder
 */
function extractOrder($oOrder)
{
    $aResult = [
        'items'=>[],
        'price'=>0,
        'region'=>[],
    ];
    //$aCardData = \Mobium\Api\ApiHelper::getCurrentUserCardData();
    /** @var \Bitrix\Sale\BasketItem $oBasketItem */
    foreach ($oOrder->getBasket()->getBasketItems() as $oBasketItem) {
        $aResult['items'][] = [
            'id'=>$oBasketItem->getProductId(),
            'count'=>$oBasketItem->getQuantity(),
            'price'=>$oBasketItem->getBasePrice(),
        ];
    }

    //$fTotalPrice = (float)$oOrder->getPrice();
    $fTotalPrice = (float)$oOrder->getPaymentCollection()->getSum();
    //    //var_dump($fTotalPrice);
    /*if (false !== $aCardData && !$aCardData['BONUS']){
        $fTotalPrice = $fTotalPrice - ($fTotalPrice*(((float)$aCardData['UF_DISCOUNT'])/100));
    }*/
    $aResult['price'] = $fTotalPrice;
    return $aResult;
}

$this->includeComponentTemplate();