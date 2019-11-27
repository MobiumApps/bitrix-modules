<?php
namespace Mobium\Api;


use Bitrix\{Currency, Iblock, Main};
use {
	Main\Loader,
	CCatalog,
	CCatalogDiscount,
	CCatalogDiscountSave,
	CCatalogProduct,
	CCatalogSku,
	CCatalogStore,
	CCatalogStoreProduct,
	CCurrencyRates,
	CDBResult,
	CEventLog,
	CFile,
	CHTTP,
	CIBlock,
	CIBlockElement,
	CIBlockFormatProperties,
	CIBlockRights,
	CIBlockSection,
	COption,
	CSite
};

class ExportBalance extends OfferExporter
{

    protected $selectStockField = [
        'ID', 'TITLE', 'PHONE', 'ADDRESS', 'SCHEDULE', 'DESCRIPTION', 'EMAIL', 'UF_CODE_1C'
    ];

    /**
     * @var \SplFixedArray
     */
    protected $productIDs = [];

    protected function processCatalog(): string
    {
        $this->getProductsIDs();
        $oStocksResult = CCatalogStore::GetList(array(), array('ACTIVE'=>'Y'), false, false, $this->selectStockField);
        $aStocks = [];
        while ($aStock = $oStocksResult->Fetch()){
            $aStocks[$aStock['ID']] = $aStock;
            fwrite($this->file, '<outlet id="'.$aStock['ID'].'" point="'.$aStock['UF_CODE_1C'].'">'.PHP_EOL);
            fwrite($this->file, '<name>'.$this->text2xml($aStock['TITLE']).'</name>>'.PHP_EOL);
            $aChunks = array_chunk($this->productIDs, 300);
            foreach ($aChunks as $aChunk){
                $oStockProductsResult = CCatalogStoreProduct::GetList(array(), array('=STORE_ID'=>$aStock['ID'], '@PRODUCT_ID'=>$aChunk), false, false, array('*'));
                while ($aProductData = $oStockProductsResult->Fetch()){
                    fwrite($this->file, '<offer id="'.$aProductData['PRODUCT_ID'].'" instock="'.$aProductData['AMOUNT'].'"/>'.PHP_EOL);
                }
            }
        }
    }

    public static function run(): string
	{
        new static();
        return 'Mobium\Api\ExportBalance::run();';
    }

    /**
     * @return string
     */
    protected function renderHeader(): string
    {
        $sResult = '<?xml version="1.0" encoding="'.$this->charset.'"?>';
        $sResult.= "<outlets date=\"".date("Y-m-d H:i")."\">".PHP_EOL;
        return $sResult;
    }

    protected function renderFooter(): string
	{
		return "</outlet>".PHP_EOL;
	}

    protected function getProductsIDs()
    {
        $oCatalogListResult = CCatalog::GetList(array(), array('IBLOCK_ID'=>static::PRODUCTS_IBLOCK_ID, "PRODUCT_IBLOCK_ID" => 0), false, false, array('IBLOCK_ID'));
        while ($aCatalog = $oCatalogListResult->Fetch()){
            $aCatalog['IBLOCK_ID'] = (int) $aCatalog['IBLOCK_ID'];
            $aIBlockInfo = CIBlock::GetArrayByID($aCatalog['IBLOCK_ID']);
            if (empty($aIBlockInfo) || !is_array($aIBlockInfo)){
                continue;
            }
            if ('Y' != $aIBlockInfo['ACTIVE']){
                continue;
            }
            // Проверка прав доступа, можно и выкинуть.
            $bRights = false;
            if ('E' != $aIBlockInfo['RIGHTS_MODE']){
                $aRights = CIBlock::GetGroupPermissions($aCatalog['IBLOCK_ID']);
                if (!empty($aRights) && isset($aRights[2]) && 'R' <= $aRights[2]){
                    $bRights = true;
                }
            } else {
                $oRights = new CIBlockRights($aCatalog['IBLOCK_ID']);
                $aRights = $oRights->GetGroups(array('section_read', 'element_read'));
                if (!empty($aRights) && in_array('G2',$aRights)){
                    $bRights = true;
                }

            }
            if (!$bRights){
                continue;
            }

            // Загрузка товаров
            $aFilters = ['IBLOCK_ID'=>$aCatalog['IBLOCK_ID'], 'ACTIVE'=>'Y', 'ACTIVE_DATE'=>'Y'];
            $oProductsResult = CIBlockElement::GetList(array(), $aFilters, false, false, ['ID']);

            $iTotalCount = 0;
            while ($aProduct = $oProductsResult->GetNext()) {
                /*if ($iTotalCount > 1000){
                    break;
                }*/
                $fStart = microtime(true);
                $iTotalCount++;
                $aProductOffers = CCatalogSKU::getExistOffers(array($aProduct['ID']), $aCatalog["IBLOCK_ID"]);
                if (count($aProductOffers) == 1 &&
                    isset($aProductOffers[$aProduct['ID']]) && $aProductOffers[$aProduct['ID']] !== false && count($aProductOffers[$aProduct['ID']]) > 0) {
                    $res = CCatalogSKU::getOffersList(array($aProduct['ID']), $aCatalog["IBLOCK_ID"]);
                    $aOffersIds = array_keys($res[$aProduct['ID']]);
                    foreach ($aOffersIds as $iId){
                        $aFilters['ID'] = $iId;
                        $aFilters['IBLOCK_ID']=static::OFFERS_IBLOCK_ID;
                        $aTempProduct = CIBlockElement::GetList(array(), $aFilters, false, false, ['ID'])->GetNext();
                        $this->productIDs[] = $aTempProduct['ID'];
                    }

                } else {
                    $this->productIDs[] =$aProduct['ID'];
                }
            }
        }
    }

    /**
     * @return string
     */
    function getFileName(): string
    {
        return 'mobium_instock.xml';
    }
}