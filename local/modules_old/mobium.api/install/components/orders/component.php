<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
use Bitrix\Main\Context,
    Bitrix\Currency\CurrencyManager,
    Bitrix\Sale\Order,
    Bitrix\Sale\Basket,
    Bitrix\Sale\Delivery,
    Bitrix\Sale\PaySystem;
use Bitrix\Sale\Shipment;

/**
 * @var array $arParams
 */
\Bitrix\Main\Loader::includeModule('mobium.api');

if ($arParams['method'] === 'get_history'){
    if (!\Mobium\Api\ApiHelper::authorizeByHeader()){
        $this->arResult = [
            'status'=>'error',
            'data'=>[
                'errorMessage'=>'not_authorized'
            ]
        ];
        $this->IncludeComponentTemplate();
        exit();
    }
    /**@var CUser $USER */
    global $USER;
    $oOrderResult = Order::getList([
        'select'=>['ID'],
        'filter'=>[
            'USER_ID'=> $USER->GetID()
        ]
    ]);
    $aResult = [];
    while ($aOrder = $oOrderResult->fetch()) {
        /** @var Order $oOrder */
        $oOrder = Order::load($aOrder['ID']);
        /** @var \Bitrix\Sale\BasketItem[] $aBasketItems */
        $aBasketItems = $oOrder->getBasket()->getBasketItems();
        $oShipmentCollection = $oOrder->getShipmentCollection();
        $iShipmentCollectionCount = $oShipmentCollection->getIterator()->count();

        /** @var Shipment $oShipment */
        $oShipment = $oShipmentCollection->getItemByIndex(0);
        //$oShipment = $oShipmentCollection->getItemByIndex($iShipmentCollectionCount - 1);
        $sDeliveryName = $oShipment->getDeliveryName();
        //$sDeliveryName = $oShipment->getDeliveryName();
        //$sDeliveryName = $oOrder->getShipmentCollection()->getSystemShipment()->getDelivery()->getName();
        /** @var Bitrix\Sale\Payment $oPayment */
        foreach ($oOrder->getPaymentCollection() as $oPayment){
            $sPayment = $oPayment->getPaymentSystemName();
            $sOrderStatus = $oPayment->isPaid() ? 'Оплачен' :'Не оплачен';
        }
        $aItems = [];
        /** @var Bitrix\Sale\BasketItem $oBasketItem */
        foreach ($oOrder->getBasket()->getBasketItems() as $oBasketItem){
            $aItems[] = [
                'id'=>$oBasketItem->getProductId(),
                'count'=>$oBasketItem->getQuantity(),
                'price'=>$oBasketItem->getPrice()
            ];
        }
        $aTempRes = [
            'id'=>$oOrder->getId(),
            'status'=>$sOrderStatus,
            'total'=>$oOrder->getPrice(),
            'deliveryType'=>$sDeliveryName,
            'items'=>$aItems,
        ];
        $aResult[] = $aTempRes;
    }
    $this->arResult = [
        'status'=>'ok',
        'data'=>['orders'=>$aResult]
    ];
}

if ($arParams['method'] === 'commit_order'){
    \Bitrix\Main\Loader::includeModule('sale');
    \Bitrix\Main\Loader::includeModule('catalog');
    $sSiteId = Context::getCurrent()->getSite();
    $aInputData = json_decode(file_get_contents('php://input'), true);
    if (!isset($aInputData['data'], $aInputData['data']['order_info'])){
        $this->arResult = [
            'status'=>1,
            'message'=>'Невалидный заказ'
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
                /*$this->arResult = [
                    'status'=>2,
                    'message'=>'Ошибка при создании заказа'
                ];
                $this->IncludeComponentTemplate();
                exit();*/
            }
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
    if (isset($aInputData['data']['deliveryTypeId']) && isset($aShipmentList[$aInputData['data']['deliveryTypeId']])){
        $oTemp = Delivery\Services\Manager::getObjectById($aShipmentList[$aInputData['data']['deliveryTypeId']]);

    } else {
        $oTemp = Delivery\Services\Manager::getObjectById(1);
    }
    $oShipment = $oShipmentCollection->createItem($oTemp);

    /*$oShipment->setFields([
        'DELIVERY_ID'=>$aDeliveryService['ID'],
        'DELIVERY_NAME'=>$aDeliveryService['NAME'],
    ]);*/

    $oShipmentItemCollection = $oShipment->getShipmentItemCollection();
    foreach ($oBasket as $oBasketItem){
        $oItem = $oShipmentItemCollection->createItem($oBasketItem);
        $oItem->setQuantity($oBasketItem->getQuantity());
    }
    /*
    $oBasket = Basket::create($sSiteId);
    $aItems = $aInputData['data']['items'];
    foreach ($aItems as $aItem){
        $oItem = $oBasket->createItem('catalog', $aItem['id']);
        $oItem->setFields([
            'QUANTITY'=>$aItem['count'],
            'CURRENCY'=>$currencyCode,
            'LID'=>$sSiteId,
            //'PRODUCT_PROVIDER_CLASS' => '\CCatalogProductProvider',
        ]);
        $oShipmentItem = $oShipmentItemCollection->createItem($oItem);
        $oShipmentItem->setQuantity($aItem['count']);
    }
    */


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
    $oResult = $oOrder->save();
    if ($oResult->isSuccess()){
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
    }

}

/**
 * @param array $aInputData
 * @param string $sSiteId
 * @param string $sCurrencyCode
 * @return \Bitrix\Sale\BasketBase
 */
function createBasket($aInputData, $sSiteId, $sCurrencyCode){
    $oBasket = Basket::create($sSiteId);
    $aItems = $aInputData['data']['items'];
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

$this->includeComponentTemplate();