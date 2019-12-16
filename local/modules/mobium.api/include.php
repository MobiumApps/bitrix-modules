<?php
use Bitrix\Main\Loader;
use Mobium\Api\Widgets\TooltipOptionsWidget;
foreach (require(__DIR__.'/modules.php') as $module) {
	Loader::includeModule($module);
}
Bitrix\Main\Loader::registerAutoloadClasses(
	"mobium.api",
	array(
		'\Mobium\Api\ExportYML' => '/lib/general/ExportYML.php',
		'\Mobium\Api\ExportBalance' => '/lib/general/ExportBalance.php',
		'\Mobium\Api\OfferExporter' => '/lib/general/OfferExporter.php',
		'\Mobium\Api\iExporter' => '/lib/general/iExporter.php',
		'\Mobium\Api\iMain' => '/lib/general/iMain.php',
		'\Mobium\Api\Main' => '/lib/general/Main.php',
		'\Mobium\Api\ApiHelper' => '/lib/general/ApiHelper.php',
		'\Mobium\Api\Helper' => '/lib/general/Helper.php',
		'\Mobium\Api\Widgets\TooltipOptionsWidget' => '/widgets/TooltipOptionsWidget.php',
		'\Picqer\Barcode\BarcodeGeneratorPNG' => '/lib/picqer/php-barcode-generator/src/BarcodeGeneratorPNG.php',
	)
);