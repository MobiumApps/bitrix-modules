<?php


namespace Mobium\Api;

/**
 * Interface iMain
 * �������� ��������� ������. ������ ��� ���������� ������� ������ API Mobium � �������� ���������
 * @package Mobium\Api
 */
interface iMain
{
    /**
     * ���������� json � ������ �����������
     * @return string json ������
     */
    function getRegistrationFields(): string;

    /**
     * ���������� json �� ������� ����� ��� �������������� ������
     * @return string json ������
     */
    function getRestoreFields(): string;

    /**
     * ���������� json �� ���������� � ������� ������������
     * @return string json ������
     */
    function getUserProfile(): string;

    /**
     * �������������� ������������
     * @return string json ������
     */
    function userChangeProfile(): string;

    /**
     * �������������� ������
     * @return string json ������
     */
    function userRestorePassword(): string;

    /**
     * ����������� ������������
     * @return string json ������
     */
    function userRegistration(): string;

    /**
     * �����
     * @return string json ������
     */
    function userAuthorization(): string;

    /**
     * �����
     * @return string json ������
     */
    function userLogout(): string;

    /**
     * ����������� �� ����
     * @return string json ������
     */
    function authByCode(): string;

    /**
     *
     * @return string json ������
     */
    function verifyCode(): string;

    /**
     * ������� �������
     * @return string json ������
     */
    function userOrderHistory(): string;

    /**
     * Commit order
     * @return string json ������
     */
    function commitOrder(): string;

    /**
     *
     * @return string json ������
     */
    function startAgent(): string;

    /**
     *
     * @return string json ������
     */
    function applyCode(): string;

    /**
     * ���������� Bar ���
     * @return string json ������
     */
    function generateBarcode($code): string;

    /**
     * ����������� ����
     * @return string json ������
     */
    function verifyData(): string;

    /**
     * ��������� ������ ���� �����������
     * @return string json ������
     */
    function getNewCode(): string;

    /**
     * �������� ����������� ������������
     * @return string json ������
     */
    function userPhoto(): string;
}