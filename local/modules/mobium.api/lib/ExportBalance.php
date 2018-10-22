<?php
namespace Mobium\Api;


use Bitrix\Currency;
use Bitrix\Iblock;
use CCatalog;
use CCatalogDiscount;
use CCatalogDiscountSave;
use CCatalogProduct;
use CCatalogSku;
use CCatalogStore;
use CCatalogStoreProduct;
use CCurrencyRates;
use CDBResult;
use CEventLog;
use CFile;
use CHTTP;
use CIBlock;
use CIBlockElement;
use CIBlockFormatProperties;
use CIBlockRights;
use CIBlockSection;
use COption;
use CSite;

class ExportBalance extends OfferExporter
{
    /**
     * @var array
     */
    protected $stocks = [];

    /**
     * @var array
     */
    protected $offers = [];

    protected $selectStockField = [
        'ID', 'TITLE', 'PHONE', 'ADDRESS', 'SCHEDULE', 'DESCRIPTION', 'EMAIL'
    ];

    public function __construct()
    {
        \Bitrix\Main\Loader::IncludeModule("highloadblock");
        set_time_limit(0);
        ini_set('memory_limit', '4096M');
        $this->fileName = $this->getFileName();
        if (true !==$this->beforeStart()){
            exit();
        }
        $this->start();
        $this->endExport();

    }

    public function start()
    {
        fwrite($this->file, $this->renderHeader());
        $this->processCatalog();
        fwrite($this->file, $this->renderFooter());
        fclose($this->file);
    }

    protected function processCatalog()
    {
        $oStocksResult = CCatalogStore::GetList(array(), array('ACTIVE'=>'Y'), false, false, $this->selectStockField);
        $aStocks = [];
        while ($aStock = $oStocksResult->Fetch()){
            $aStocks[$aStock['ID']] = $aStock;
            fwrite($this->file, '<outlet id="'.$aStock['ID'].'">'.PHP_EOL);
            fwrite($this->file, '<name>'.$this->text2xml($aStock['TITLE']).'</name>>'.PHP_EOL);
            $oStockProductsResult = CCatalogStoreProduct::GetList(array(), array('=STORE_ID'=>$aStock['ID']), false, false, array('*'));
            while ($aProductData = $oStockProductsResult->Fetch()){
                fwrite($this->file, '<offer id="'.$aProductData['PRODUCT_ID'].'" instock="'.$aProductData['AMOUNT'].'"/>'.PHP_EOL);
            }
            fwrite($this->file, '</outlet>'.PHP_EOL);
        }
    }

    public static function run(){
        $oObject = new static();
        return 'Modim\Api\ExportInStock::run();';
    }

    /**
     * @return string
     */
    protected function renderHeader()
    {
        $sResult = '<?xml version="1.0" encoding="'.$this->charset.'"?>';
        $sResult.= "<outlets date=\"".date("Y-m-d H:i")."\">".PHP_EOL;
        return $sResult;
    }

    /**
     * @return string
     */
    protected function renderFooter()
    {
        $sResult = '</outlets>';
        return $sResult;
    }

    /**
     * @return string
     */
    protected function getFileName()
    {
        return 'mobium_instock.xml';
    }
}