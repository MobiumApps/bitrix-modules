<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
use Bitrix\Main\Context,
    Bitrix\Currency\CurrencyManager,
    Bitrix\Sale\Order,
    Bitrix\Sale\Basket,
    Bitrix\Sale\Delivery,
    Bitrix\Sale\PaySystem;
use Bitrix\Sale\DiscountCouponsManager;
use Bitrix\Sale\Internals\CollectableEntity;
use Bitrix\Sale\Shipment;

/**
 * @var array $arParams
 */
\Bitrix\Main\Loader::includeModule('mobium.api');

$aPaymentSystemAssociation = [
    'Наличные курьеру'=>1,
    'Банковской картой онлайн'=>3,
    'Банковской картой курьеру'=>9,
    'Наличными или банковской картой'=>10
];

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
        ],
        'order'=>['ID'=>'DESC']
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
                'price'=>$oBasketItem->getPrice(),

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
    $aTiming = [];
    $fStartTotalTime = microtime(true);
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
                $iProfileId = \Mobium\Api\ApiHelper::createUserAppProfile($USER);
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
    $fStartCreating = microtime(true);
    $oBasket = createBasket($aInputData, $sSiteId, $currencyCode);

    \Bitrix\Sale\DiscountCouponsManager::init();
    $oOrder = Order::create($sSiteId, $USER->GetID() );
    $oCollection = $oOrder->getPropertyCollection();
    if (false !== ($aCardData = \Mobium\Api\ApiHelper::getCurrentUserCardData())){
        $sCode = $aCardData['UF_CODE'];
        if (null !== $oDiscountCard = getPropertyByCode($oCollection, 'DISCOUNT_CARD')){
            $oDiscountCard->setValue($sCode);
    }
    }
    $aTiming['creating'] = microtime(true) - $fStartCreating;

    /**
     * 1 - физическое лицо
     * 2 - Юр. лицо
     */
    $oOrder->setPersonTypeId(1);
    $oOrder->setField('CURRENCY', $currencyCode);
    if (isset($aOrderInfo['comments']) && !empty($aOrderInfo['comments'])) {
        $oOrder->setField('USER_DESCRIPTION', $aOrderInfo['comment']);
    }
    $oOrder->setBasket($oBasket);
    $oShipmentCollection = $oOrder->getShipmentCollection();
    //$oShipment = $oShipmentCollection->createItem();

    //$aShipmentList = \Mobium\Api\ApiHelper::getDeliveryTypes();
    if ($aInputData['version'] == '2.0'){
        $sDeliveryTypeId = $aInputData['data']['delivery']['type_id'];
    } else {
        $sDeliveryTypeId = $aInputData['data']['deliveryTypeId'];
    }

    /*\Bitrix\Sale\DiscountCouponsManager::init(
        \Bitrix\Sale\DiscountCouponsManager::MODE_ORDER, [
            "userId" => $oOrder->getUserId(),
            "orderId" => $oOrder->getId()
        ]
    );*/
    //\Bitrix\Sale\DiscountCouponsManager::add($aOrderInfo['marketing']['promoCode']);

    $aShipmentList = \Mobium\Api\ApiHelper::getDeliveryAssociations();
    if (isset($aShipmentList[$sDeliveryTypeId])){
        $oTemp = Delivery\Services\Manager::getObjectById((int) $aShipmentList[$sDeliveryTypeId]);
        //\Mobium\Api\ApiHelper::getLocationsForDelivery((int) $aShipmentList[$sDeliveryTypeId]);
    } else {
        $oTemp = Delivery\Services\Manager::getObjectById(1);
    }

    $oShipment = $oShipmentCollection->createItem($oTemp);
    $oShipment->setField('CURRENCY', $currencyCode);

    //$oShipment->setStoreId();
    $aDeliveryInfo = [
        'phone'=>$aInputData['data']['order_info']['phone'],
        'name'=>$aInputData['data']['order_info']['name']
    ];
    $bNeedToLoadLocation = true;
    if (isset($aInputData['data']['delivery']['data'])){
        $aDeliveryData = $aInputData['data']['delivery']['data'];
        foreach ($aDeliveryData as $aField){
            if ($aField['type'] == 'outpost_field'){
                $fStartOutpostTime  = microtime(true);
                $oRes = CCatalogStore::GetList([], ['UF_CODE_1C'=>$aField['value']], false, false, ['ID']);
                while ($aStore = $oRes->Fetch()){
                    $oShipment->setStoreId($aStore['ID']);
                    $bNeedToLoadLocation = false;
                    break 2;
                }
                $aTiming['outpost'] = microtime(true) - $fStartOutpostTime;
                //$oShipment->setStoreId(10);
            }
            if ($aField['type'] == 'street_place_id' && !isset($aDeliveryInfo['street'])){
                $aDeliveryInfo['street']=$aField['value'];
            }
            if ($aField['id'] == 'building'){
                $aDeliveryInfo['building'] = $aField['value'];
            }
            if ($aField['id'] == 'apartments'){
                $aDeliveryInfo['apartments']  = $aField['value'];
            }
        }
    }

    if (isset($aInputData['region_data']['title'])){
        $aDeliveryInfo['city'] = $aInputData['region_data']['title'];
    }
    if ($bNeedToLoadLocation && isset($aInputData['region_data']['location'])){
        if (isset($aInputData['region_data']['location']['place_id'])){
            $sResult = file_get_contents(
                'https://maps.googleapis.com/maps/api/geocode/json?language=ru&key=AIzaSyAQguMPPedN6co-VuT7YX4PkDPlpz3WQto&place_id='.$aInputData['region_data']['location']['place_id']
            );
//            $oOrder->setField('MOBIUM_ID_CITY', $aInputData['region_data']['location']['place_id']);

            if (null !== ($aGeoData = json_decode($sResult, true))){
                if ($aGeoData['status'] === 'OK'){

                    if (isset($aGeoData['results']) && is_array($aGeoData['results']) && count($aGeoData['results']) > 0){
                        $aGeoResult = $aGeoData['results'][0];
                        foreach ($aGeoResult['address_components'] as $aAddressComponent){
//                            if (in_array('locality', $aAddressComponent['types'])){
//                                $sRegionName = $aAddressComponent['long_name'];
//                                $fStartPlaceTime = microtime(true);
//                                list($aPlaceData, $aTimingData) = \Mobium\Api\ApiHelper::getLocationsForDelivery((int) $aShipmentList[$sDeliveryTypeId], $sRegionName);
//                                if (!isset($aTiming['location_inner'])){
//                                    $aTiming['location_inner'] = [];
//                                }
//                                $aTiming['location_inner'][] = $aTimingData;
//                                $aTiming['location'] = microtime(true) - $fStartPlaceTime;
//                                if (!empty($aPlaceData)){
//                                    $aPlaceData = $aPlaceData[0];
//                                    $aDeliveryInfo['location'] = $aPlaceData['ID'];
//                                    $aDeliveryInfo['full_address'] = $aGeoResult['formatted_address'];
//
//                                }
//
//                            }
                            if (in_array('locality', $aAddressComponent['types'])){
                                $aDeliveryInfo['city'] = $aAddressComponent['long_name'];
                            }
                            if (in_array('street_number', $aAddressComponent['types'])){
                                $aDeliveryInfo['building'] = $aAddressComponent['long_name'];
                            }
                            if (in_array('route', $aAddressComponent['types'])){
                                $aDeliveryInfo['street'] = $aAddressComponent['long_name'];
                            }
                        }
                    }
                } else {
                    $this->arResult = [
                        'status'=>7,
                        'message'=>'Ошибка в адресе'
                    ];
                    $this->IncludeComponentTemplate();
                    exit();
                }
            }
        }

    }
    if (isset($aOrderInfo['email']) && filter_var($aOrderInfo['email'], FILTER_VALIDATE_EMAIL)){
        $aDeliveryInfo['email'] = $aOrderInfo['email'];
    }
    if (isset($aOrderInfo['bonuses_used']) && (float) $aOrderInfo['bonuses_used'] > 0){
        $this->arResult = [
            'status'=>8,
            'message'=>'Сейчас списание бонусов в приложении в разработке, но Вы можете использовать их при оформлении заказа через сайт.'
        ];
        $this->IncludeComponentTemplate();
        exit();
    }
    if (
        isset($aOrderInfo['bonuses_used']) && (float) $aOrderInfo['bonuses_used'] > 0 &&
        false !== $aCardData
    ){
        if ((float) $aOrderInfo['bonuses_used'] > 15){
            $aOrderInfo['bonuses_used'] = 15;
        }
        /** @var \Bitrix\Sale\BasketItem[] $aBasketItems */
        $aBasketItems = $oOrder->getBasket()->getBasketItems();
        $i = 1;
        $aBasketItemsForRequest = [];
        $fTotalSum = $fTotalDiscountSum = 0;
        foreach ($aBasketItems as $oBasketItem) {
            $amount = \Bethowen\Helpers\Price::getDiscountPrice($oBasketItem->getProductId());
            $price = $oBasketItem->getPrice();
            $discount = $oBasketItem->getField('BASE_PRICE') - $price;
            $percent = $price / $oBasketItem->getField('BASE_PRICE') * 100;

            $aBasketItemsForRequest[] = [
                'position'=>$i,
                'amount'=>$amount,
                'goodsId'=>$oBasketItem->getProductId(),
                'barcode'=>'barcode',
                'quantity'=>$oBasketItem->getQuantity(),
                'cashback'=>0,
                'discount'=>$discount,
                'name'=>$oBasketItem->getField('NAME'),
                'price'=>$price,
                'correction'=>0
            ];
            ++$i;
            $fTotalSum += $amount*$oBasketItem->getQuantity();
            if ($discount == 0){
                $fTotalDiscountSum += $amount*$oBasketItem->getQuantity();
            }
        }
        if (empty($sCardCode)){
            $sCardCode = $aCardData['UF_CODE'];
        }
        \Mobium\Api\ApiHelper::authorizeUserCard($sCardCode);
        $request = Bethowen\Services\Loymax::prepareBasket($aBasketItemsForRequest);
        $response = Bethowen\Services\Loymax::calculateLoymax($request, true);

        if (count($response) > 1){
            $aLoymaxData = json_decode($response[0], true);
            if ((float) $aLoymaxData['data'][0]['availableAmount'] < (float) $aOrderInfo['bonuses_used']){
                $this->arResult = [
                    'status'=>7,
                    'message'=>'Вы не можете потратить столько бонусов.'
                ];
                $this->IncludeComponentTemplate();
                exit();
            }
            $pid = $response[1];
            $fChargeBonuses = (float) $aOrderInfo['bonuses_used'];
            $cheque = $request["cheque"];

            $request = Bethowen\Services\Loymax::prepareBonusPayment($cheque, $fChargeBonuses, 1234);
            $response = Bethowen\Services\Loymax::bonusPaymentLoymax($pid, $request);

            $iPercents = round($fTotalDiscountSum / ($fTotalSum/100));
            if ($iPercents > 90){
                $iPercents = 90;
            }
            $sCoupon = 'DISCOUNT'.$iPercents;

            \Bitrix\Sale\DiscountCouponsManager::add($sCoupon);
        }
    }

    \Mobium\Api\ApiHelper::updateProfileForOrder($USER, $aDeliveryInfo);
    $fStartDATime = microtime(true);
    $oShipmentCollection->calculateDelivery();

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
    $iPaymentSystemId = $aPaymentSystemAssociation[$aPaymentType['title']] ?? 1;

    $oPaymentCollection = $oOrder->getPaymentCollection();
    $oPayment = $oPaymentCollection->createItem(PaySystem\Manager::getObjectById($iPaymentSystemId));
    $oPayment->setField('PAY_SYSTEM_ID', $iPaymentSystemId);
    $oPayment->setField('SUM', $oOrder->getPrice());
    $oPayment->setField('CURRENCY', $oOrder->getCurrency());
    $aTiming['delivery'] = microtime(true) - $fStartDATime;
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


    //$oOrder = \Bitrix\Sale\Order::load($oResult->getId());
    if (isset($aOrderInfo['marketing'], $aOrderInfo['marketing']['promoCode']) && !empty(trim($aOrderInfo['marketing']['promoCode']))){
        $aData = \Bitrix\Sale\DiscountCouponsManager::getData($aOrderInfo['marketing']['promoCode']);
        if (is_array($aData) && (int)$aData['ID'] > 0){
            if ($aData['STATUS'] == DiscountCouponsManager::STATUS_APPLYED || $aData['STATUS'] == DiscountCouponsManager::STATUS_ENTERED ) {
                /*
                    \Bitrix\Sale\DiscountCouponsManager::MODE_ORDER, [
                        "userId" => $oOrder->getUserId(),
                        "orderId" => $oOrder->getId()
                    ]
                );*/
                \Bitrix\Sale\DiscountCouponsManager::add($aOrderInfo['marketing']['promoCode']);
                //$discounts = $oOrder->getDiscount();
                //$discounts->calculate();
                //$oOrder->doFinalAction(true);
                //$oResult=$oOrder->save();
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
    $fStartSaving = microtime(true);
    $oOrder->doFinalAction(true);
    $oResult = $oOrder->save();

    \Bethowen\Helpers\Other::addOrderProperty('MOBIUM_ID_CITY', $aInputData['region_data']['location']['place_id'], $oOrder->getId());

    $aTiming['saving_order'] = microtime(true) - $fStartSaving;

    $fStartUpdate = microtime(true);
    if (isset($aDeliveryInfo['location'])){
        \Bethowen\Helpers\Other::addOrderProperty('MESTO', $aDeliveryInfo['location'], $oOrder->getId());
    }
    if (isset($aDeliveryInfo['city'])){
        \Bethowen\Helpers\Other::addOrderProperty('CITY', $aDeliveryInfo['city'], $oOrder->getId());
    }
    if (isset($aDeliveryInfo['street'])){
        \Bethowen\Helpers\Other::addOrderProperty('STREET', $aDeliveryInfo['street'], $oOrder->getId());
    }
    if (isset($aDeliveryInfo['building'])){
        \Bethowen\Helpers\Other::addOrderProperty('HOUSE', $aDeliveryInfo['building'], $oOrder->getId());
    }
    if (isset($aDeliveryInfo['apartments'])){
        \Bethowen\Helpers\Other::addOrderProperty('KVARTIRA', $aDeliveryInfo['apartments'], $oOrder->getId());
    }
    $aTiming['update_properties'] = microtime(true) - $fStartUpdate;


    $aTiming['total'] = microtime(true) - $fStartTotalTime;
    if ($oResult->isSuccess()){
        $fCreatingPayment = microtime(true);
        $aReturnData = ['order_id'=>$oOrder->getId(), 'order_info'=>extractOrder($oOrder)];
        if ($aPaymentType['type'] === 'gateway'){
            $aReturnData['link'] = \Bethowen\Helpers\Order::getSberbankLinkToPay($oOrder->getId(), 'isApp=1');
        }
        $aTiming['creating_payment'] = microtime(true) - $fCreatingPayment;
        $this->arResult = [
            'status'=>0,
            'data'=>$aReturnData,
            'timing'=>$aTiming
        ];
    } else {
        $this->arResult = [
            'status'=>4,
            'message'=>'Ошибка при сохранении заказа',
            'd'=>$oResult->getErrorMessages(),
            'timing'=>$aTiming
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
        $oItem = $oBasket->createItem('catalog', $aItem['offer']['id']);
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
 * @return array
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

/**
 * @param \Bitrix\Sale\PropertyValueCollection $propertyCollection
 * @param string $code
 * @return CollectableEntity|null
 */
function getPropertyByCode($propertyCollection, $code)  {
    foreach ($propertyCollection as $property)
    {
        if($property->getField('CODE') == $code)
            return $property;
    }
    return null;
}

$this->includeComponentTemplate();