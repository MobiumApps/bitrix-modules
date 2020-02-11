<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
IncludeModuleLangFile(__FILE__);
?><?php
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use \Mobium\Api\ProductsExportProps;
use \Mobium\Api\OffersExportProps;
use \Mobium\Api\RegistrationField;
use \Mobium\Api\DeliveryType;

if (!Loader::includeModule('digitalwand.admin_helper') || !Loader::includeModule('mobium.api')) return;
Loc::loadMessages(__FILE__);
return [
    [
        "parent_menu" => "global_menu_content",
        "sort"        => 220,
        "url"         => "admin_helper_route.php?lang=" . LANGUAGE_ID . "&module=kelnik.banners&view=banners_list&entity=banners",  // ссылка на пункте меню
        "text"        => 'Mobium',
        "title"       => 'Mobium',
        "icon"        => "iblock_menu_icon_types", // малая иконка
        "page_icon"   => "iblock_menu_icon_types", // большая иконка
        "items_id"    => "mobium_banners",  // идентификатор ветви
        "items"       => [
            array(
                'parent_menu' => 'global_menu_content',
                'sort' => 150,
                'icon' => 'iblock_menu_icon',
                'page_icon' => 'iblock_menu_icon',
                'text' => Loc::getMessage("MOBIUM_API_PRODUCTS_TITLE"),
                'url' => ProductsExportProps\AdminInterface\ProductsExportPropsListHelper::getUrl(),
                'more_url'=>[
                    ProductsExportProps\AdminInterface\ProductsExportPropsEditHelper::getUrl()
                ]
            ),
            array(
                'parent_menu' => 'global_menu_content',
                'sort' => 160,
                'icon' => 'iblock_menu_icon',
                'page_icon' => 'iblock_menu_icon',
                'text' => Loc::getMessage("MOBIUM_API_OFFERS_TITLE"),
                'url' => OffersExportProps\AdminInterface\OffersExportPropsListHelper::getUrl(),
                'more_url'=>[
                    OffersExportProps\AdminInterface\OffersExportPropsEditHelper::getUrl()
                ]
            ),
            array(
                'parent_menu' => 'global_menu_content',
                'sort' => 170,
                'icon' => 'iblock_menu_icon',
                'page_icon' => 'iblock_menu_icon',
                'text' => Loc::getMessage("MOBIUM_API_FIELDS_TITLE"),
                'url' => RegistrationField\AdminInterface\registrationfieldlisthelper::getUrl(),
                'more_url'=>[
					RegistrationField\AdminInterface\registrationfieldedithelper::getUrl()
                ]
            ),
            array(
                'parent_menu' => 'global_menu_content',
                'sort' => 180,
                'icon' => 'iblock_menu_icon',
                'page_icon' => 'iblock_menu_icon',
                'text' => Loc::getMessage("MOBIUM_API_DELIVERIES_TITLE"),
                'url' => DeliveryType\AdminInterface\deliverytypelisthelper::getUrl(),
                'more_url'=>[
                    DeliveryType\AdminInterface\deliverytypeedithelper::getUrl()
                ]
            ),
        ],
    ]
];
