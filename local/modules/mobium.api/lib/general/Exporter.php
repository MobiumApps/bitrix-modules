<?php

namespace Mobium\Api;

/**
 * Interface Exporter - для экспорта данных в файл
 * @package Mobium\Api\
 */
interface Exporter
{
	/**
	 * Запускает Экспорт
	 * @return mixed
	 */
	function start();

	/**
	 * Подготавляет данные для Экспорта и разрешает старт
	 * @return bool true - можно начинать Экспорт,  - нельзя!
	 */
	function beforeStart(): bool;

	/**
	 * Завершает Экспорт
	 * @return mixed
	 */
	function endExport();

	/**
	 * @return string - имя файла, куда экспортировать данные
	 */
	function getFileName(): string ;

	/**
	 * Создает и запускает агента
	 * @return mixed
	 */
	static function run();
}