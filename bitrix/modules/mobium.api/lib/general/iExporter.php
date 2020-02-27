<?php

namespace Mobium\Api;

/**
 * Interface Exporter - ��� �������� ������ � ����
 * @package Mobium\Api\
 */
interface iExporter
{
	/**
	 * ��������� �������
	 * @return mixed
	 */
	function start();

	/**
	 * ������������ ������ ��� �������� � ��������� �����
	 * @return bool true - ����� �������� �������,  - ������!
	 */
	function beforeStart(): bool;

	/**
	 * ��������� �������
	 * @return mixed
	 */
	function endExport();

	/**
	 * @return string - ��� �����, ���� �������������� ������
	 */
	function getFileName(): string ;

	/**
	 * ������� � ��������� ������
	 * @return mixed
	 */
	static function run();
}