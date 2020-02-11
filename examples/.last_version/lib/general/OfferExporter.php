<?php
namespace Mobium\Api;

use Bitrix\Main\Loader;
use Bitrix\Currency;
use Bitrix\Catalog\ExportYML;
use Bitrix\Iblock;
use Bitrix\Main\Config\Option;
use CCatalog;
use CCatalogDiscount;
use CCatalogDiscountSave;
use CCatalogProduct;
use CCatalogSku;
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

abstract class OfferExporter implements iExporter
{
	/**
	 * @var int - ID �� �������
	 */
	protected $productsIblock;

	/**
	 * @var int - ID �� �������� �����������
	 */
	protected $offersIblock;

    /**
     * @var \CUser - ������������ �� ������� ���� �������
     */
    protected $user;

    /**
     * @var bool - ���� �������� ���������� ������������
     */
    protected $tempUserCreated = false;


    /**
     * @var \CUser - ��� �������� ������������
     */
    protected $tmpUser;

    /**
     * @var string - URL �������� �����
     */
    protected $serverName;


    /**
     * @var string - ������� ������
     */
    protected $baseCurrency;

    /**
     * @var string - ��� ������ �����
     */
    protected $currencyRub = 'RUB'; // ����� ���� RUR

    /**
     * @var bool - ���� ������� ������
     */
    protected $isError = false;

    /**
     * @var string - ���� � ����� ��������
     */
    protected $filePath;

    /**
     * @var string - ��� ����� ��������
     */
    protected $fileName;

	/**
	 * @var string - ���� � ����� ����������
	 */
    protected $lockFilePath;

    /**
     * @var resource - ������ �� ���� ��������
     */
    protected $file;

    /**
     * @var string - �������� ����� ��������
     */
    protected $charset = 'utf-8';

    public function __construct()
	{
		Loader::IncludeModule("iblock");
		set_time_limit(0);
		ini_set('memory_limit', '4096M');
		$this->productsIblock = Option::get('mobium.api', 'products_iblock', '0');
		$this->offersIblock = Option::get('mobium.api', 'offers_iblock', '0');
		$this->fileName = $this->getFileName();
		if (true !==$this->beforeStart()){
			exit();
		}
		$this->start();
		$this->endExport();
	}

	/**
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    protected function defineCurrency()
    {
        $this->baseCurrency = Currency\CurrencyManager::getBaseCurrency();
        $currencyIterator = Currency\CurrencyTable::getList(array(
            'select' => array('CURRENCY'),
            'filter' => array('=CURRENCY' => 'RUR')
        ));
        if ($currency = $currencyIterator->fetch()){
            $this->currencyRub = 'RUR';
        }
    }

	/**
	 * ������������� ������������ ��� ��������, ������� ����������, ��� �������������
	 */
    function setUser()
    {
        global $USER;
        if (!\CCatalog::IsUserExists()) {
            $this->tempUserCreated = true;
            if (isset($USER)) {
                $this->tmpUser = $USER;
            }
            $this->user = new \CUser();
        } else {
            $this->user = $USER;
        }
    }

	/**
	 * ������� ���������� ������������, ���� �� ��� ������ ��� ������������� �������
	 */
    protected function deleteUser()
    {
        global $USER;
        if ($this->tempUserCreated) {
            if (isset($this->tmpUser) && $this->tmpUser instanceof \CUser) {
                $USER = $this->tmpUser;
                unset($this->tmpUser);
            }
        }
    }

	/**
	 * ��������� ������� �� �����, �����, ����, ������
	 */
	function start()
	{
		fwrite($this->file, $this->renderHeader());
		$this->processCatalog();
		fwrite($this->file, $this->renderFooter());
		fclose($this->file);
	}

	/**
	 *  ������ �������� �������� ���� ��������, ������ ���� ���������� � ��������
	 */
	protected abstract function renderHeader(): string ;
	protected abstract function processCatalog();
	protected abstract function renderFooter(): string ;

	/**
	 * ��������� �������� ������������, ������� ���������� ��� �������������,
	 * �������� ����� � ������, ������ ���� � ����� ��������, ��������� ������ � ����.
	 * @return bool - true, ���� ��� ���������� | false, ���� ���-�� ����� �� ���.
	 */
    function beforeStart(): bool
    {
    	Loader::includeModule("catalog");
        $this->setUser();
        \CCatalogDiscountSave::Disable();
        \CCatalogDiscountCoupon::ClearCoupon();
        if ($this->user->IsAuthorized()) {
            \CCatalogDiscountCoupon::ClearCouponsByManage($this->user->GetID());
        }
        $this->protocol = (\CMain::IsHTTPS() ? 'https://' : 'http://');
        $this->defineCurrency();
        $strExportPath = Option::get("catalog", "export_default_path", CATALOG_DEFAULT_EXPORT_PATH);
        $this->filePath = Rel2Abs('/',str_replace('//','/',$strExportPath."/{$this->fileName}"));
        if (!empty($this->filePath)){
			$this->lockFilePath = $this->filePath.'.lock';
			if (file_exists($this->lockFilePath)){
				return false;
			}
			file_put_contents($this->lockFilePath, 'locked');
            CheckDirPath($_SERVER["DOCUMENT_ROOT"].$strExportPath);
            if ($this->file = @fopen($_SERVER["DOCUMENT_ROOT"].$this->filePath, 'wb')) {
                return true;
            }
        }
        return false;
    }

    function endExport()
    {
        if ($this->isError) {
            CEventLog::Log('WARNING','CAT_YAND_AGENT','mobium.api','exportYML',$this->filePath);
        }
		@unlink($this->lockFilePath);
        CCatalogDiscountSave::Enable();
        $this->deleteUser();
    }

    /**
     * @param string $text
     * @param bool $bHSC ������������ htmlspecialchars
     * @param bool $bDblQuote ������������ &quot; � "
     * @return string
     */
    protected function text2xml($text, $bHSC = false, $bDblQuote = false)
    {
        global $APPLICATION;

        $bHSC = (true == $bHSC ? true : false);
        $bDblQuote = (true == $bDblQuote ? true: false);

        if ($bHSC) {
            $text = htmlspecialcharsbx($text);
            if ($bDblQuote){
                $text = str_replace('&quot;', '"', $text);
            }

        }
        $text = preg_replace('/[\x01-\x08\x0B-\x0C\x0E-\x1F]/', "", $text);
        $text = str_replace("'", "&apos;", $text);
        $text = $APPLICATION->ConvertCharset($text, LANG_CHARSET, $this->charset);

        return $text;
    }

    protected function getServerName(){
        if (!$this->serverName){
            $this->serverName = (\CMain::IsHTTPS() ? 'https://' : 'http://') . Option::get("main", "server_name", $_SERVER['SERVER_NAME']);
        }
        return $this->serverName;
    }

    /**
     * @return string
     */
    abstract function getFileName(): string ;

}