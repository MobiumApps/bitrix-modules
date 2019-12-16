<?php


namespace Mobium\Api;

/**
 * Interface iMain
 * Основной интерфейс модуля. Служит для реализации классов работы API Mobium с Интернет магазином
 * @package Mobium\Api
 */
interface iMain
{
	/**
	 * Возвращает json с полями регистрации 
	 * @return string json ответа
	 */
	function getRegistrationFields(): string;

	/**
	 * Возвращает json со списком полей для восстановления пароля
	 * @return string json ответа
	 */
	function getRestoreFields(): string;

	/**
	 * Возвращает json со сведениями о профиле пользователя
	 * @return string json ответа
	 */
	function getUserProfile(): string;

	/**
	 * Редактирование пользователя
	 * @return string json ответа
	 */
	function userChangeProfile(): string;

	/**
	 * Восстановление пароля
	 * @return string json ответа
	 */
	function userRestorePassword(): string;

	/**
	 * Регистрация пользователя
	 * @return string json ответа
	 */
	function userRegistration(): string;

	/**
	 * Логин
	 * @return string json ответа
	 */
	function userAuthorization(): string;

	/**
	 * Выход
	 * @return string json ответа
	 */
	function userLogout(): string;

	/**
	 * Авторизация по коду
	 * @return string json ответа
	 */
	function authByCode(): string;

	/**
	 *
	 * @return string json ответа
	 */
	function verifyCode(): string;

	/**
	 * История заказов
	 * @return string json ответа
	 */
	function userOrderHistory(): string;

	/**
	 * Commit order
	 * @return string json ответа
	 */
	function commitOrder(): string;

	/**
	 *
	 * @return string json ответа
	 */
	function startAgent(): string;

	/**
	 *
	 * @return string json ответа
	 */
	function applyCode(): string;

	/**
	 * Генерирует Bar код
	 * @return string json ответа
	 */
	function generateBarcode(): string;

	/**
	 * Верификация поля
	 * @return string json ответа
	 */
	function verifyData(): string;

	/**
	 * Повторный запрос кода верификации
	 * @return string json ответа
	 */
	function getNewCode(): string;

	/**
	 * Загрузка ихображения пользователя
	 * @return string json ответа
	 */
	function userPhoto(): string;
}