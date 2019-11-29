<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;


if (!Loader::includeModule('digitalwand.admin_helper') || !Loader::includeModule('mobium.api')) return;

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
                'sort' => 200,
                'icon' => 'iblock_menu_icon',
                'page_icon' => 'iblock_menu_icon',
                'text' => Loc::getMessage("MOBIUM_API_PRODUCTS_TITLE"),
                'url' => \Mobium\Api\ProductsExportProps\AdminInterface\ProductsExportPropsListHelper::getUrl(),
                'more_url'=>[
                    \Mobium\Api\ProductsExportProps\AdminInterface\ProductsExportPropsEditHelper::getUrl()
                ]
            ),
            array(
                'parent_menu' => 'global_menu_content',
                'sort' => 200,
                'icon' => 'iblock_menu_icon',
                'page_icon' => 'iblock_menu_icon',
                'text' => Loc::getMessage("MOBIUM_API_OFFERS_TITLE"),
                'url' => \Mobium\Api\OffersExportProps\AdminInterface\OffersExportPropsListHelper::getUrl(),
                'more_url'=>[
                    \Mobium\Api\OffersExportProps\AdminInterface\OffersExportPropsEditHelper::getUrl()
                ]
            ),
        ],
    ]
];
