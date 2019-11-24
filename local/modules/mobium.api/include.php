<?php
require_once __DIR__.'/vendor/autoload.php';
$requiredModules = include(__DIR__.'/install/require.php');
foreach ($requiredModules as $module){
    \Bitrix\Main\Loader::includeModule($module);
}
CModule::AddAutoloadClasses('mobium.api', array(
    'Mobium\Api\OptionsHelper' => 'lib/OptionsHelper.php',
    'Mobium\Api\EventHandler' => 'lib/EventHandler.php',
    'Mobium\Api\ApiHelper' => 'lib/ApiHelper.php',
    'Mobium\Api\OfferExporter' => 'lib/OfferExporter.php',
    'Mobium\Api\ExportYML' => 'lib/ExportYML.php',
    'Mobium\Api\ExportBalance' => 'lib/ExportBalance.php',
    'Mobium\Api\ExportPoints' => 'lib/ExportPoints.php',
    'Mobium\Api\ExportDeliveries' => 'lib/ExportDeliveries.php',
    'Mobium\Api\ExportCatalog' => 'lib/ExportCatalog.php',
    'Mobium\Api\Widgets\TooltipOptionsWidget' => 'widgets/TooltipOptionsWidget.php',
));
