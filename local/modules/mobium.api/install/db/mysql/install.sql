CREATE TABLE IF NOT EXISTS `mobium_registration_fields` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `SLUG` varchar(255) COLLATE utf8_general_ci NOT NULL,
  `EDITABLE` char(1) COLLATE utf8_general_ci NOT NULL DEFAULT 'N',

  `REGISTER_ACTIVE` char(1) COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  `REGISTER_REQUIRED` char(1) COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  `REGISTER_TYPE` varchar(30) COLLATE utf8_general_ci NULL,
  `REGISTER_TITLE` varchar(255) COLLATE utf8_general_ci NOT NULL,
  `REGISTER_SORT` int(11) NOT NULL DEFAULT 500,

  `VERIFICATION_ACTIVE` char(1) COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  `VERIFICATION_TIME` int(11) NULL,
  `VERIFICATION_TEXT` varchar(255) COLLATE utf8_general_ci NULL,
  `VERIFICATION_TYPE` varchar(30) COLLATE utf8_general_ci NULL,
  `VERIFICATION_DRIVER` varchar(30) COLLATE utf8_general_ci NULL,

  `PROFILE_ACTIVE` char(1) COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  `PROFILE_SORT` int(11) NOT NULL DEFAULT 500,
  `PROFILE_TYPE` varchar(30) COLLATE utf8_general_ci NULL,
  `PROFILE_ACTION` varchar(50) COLLATE utf8_general_ci NULL,
  `PROFILE_ACTION_PARAM` TEXT COLLATE utf8_general_ci NULL,
  `PROFILE_TITLE`  varchar(255) COLLATE utf8_general_ci NULL,

  `RESTORE_ACTIVE` char(1) COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  `RESTORE_SORT` int(11) NOT NULL DEFAULT 500,
  PRIMARY KEY (`ID`),
  KEY `REGISTER_ACTIVE` (`REGISTER_ACTIVE`),
  KEY `PROFILE_ACTIVE` (`PROFILE_ACTIVE`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

LOCK TABLES `mobium_registration_fields` WRITE;
/*!40000 ALTER TABLE `mobium_registration_fields` DISABLE KEYS */;
INSERT INTO `mobium_registration_fields` VALUES
(1,'login','N','N','N','text','Логин',1,'N',0,'','','','N',1,'name_field','','','Профиль','N',1),
(2,'password','N','Y','Y','password','Пароль *',5,'N',0,'','','','N',0,'','','','','N',0),
(3,'password_confirm','N','Y','Y','password','Подтверждение пароля *',6,'N',0,'','','','N',0,'','','','','N',0),
(4,'email','N','Y','Y','email','Электронная почта *',3,'N',0,'','','','Y',3,'title_text_field','','','Электронная почта','Y',0),
(5,'phone','N','Y','Y','phone','Телефон *',4,'Y',120,'Введите код из SMS','text','sms','Y',4,'title_text_field','','','Телефон','N',0),
(6,'name','Y','Y','Y','text','Имя *',1,'N',0,'','','','Y',1,'name_field','','','Имя','N',0),
(7,'bonuses','N','N','N','','',0,'N',0,'','','','N',10,'bonus_field','','','Программа лояльности','N',0),
(8,'card_code','Y','Y','N','text','Номер бонусной карты',9,'N',0,'','','','Y',7,'text_field','','','Бонусная карта','N',0),
(9,'barcode','N','N','N','','',0,'N',0,'','','','Y',22,'barcode_field','','','Дисконтная карта','N',0),
(10,'sex','N','Y','Y','sex_select','Пол *',8,'N',0,'','','','Y',6,'title_text_field','','','Пол','N',0),
(11,'birthday','N','Y','Y','date_picker','Дата рождения *',7,'N',0,'','','','Y',5,'title_text_field','','','Дата рождения','N',0),
(12,'last_name','Y','Y','Y','text','Фамилия *',2,'N',0,'','','','Y',2,'title_text_field','','','Фамилия','N',0);
UNLOCK TABLES;

  CREATE TABLE IF NOT EXISTS `mobium_user_token`(
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `BODY` varchar(50) COLLATE utf8_general_ci NOT NULL,
  `CREATED_AT` int(11) NOT NULL,
  `LIFETIME` int(11) DEFAULT 0,
  `TYPE` varchar(50) COLLATE utf8_general_ci NOT NULL,
  `USER_ID` int(11) NOT NULL,
  PRIMARY KEY (`ID`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `mobium_delivery_type_assoc`(
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ACTIVE` char(1) COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  `DELIVERY_SERVICE_ID_BITRIX` varchar(100) COLLATE utf8_general_ci NOT NULL,
  `DELIVERY_SERVICE_ID_MOBIUM` varchar (100) COLLATE utf8_general_ci NOT NULL,
  `DELIVERY_SERVICE_AREA_ID` varchar(100) collate utf8_general_ci NOT NULL,
  PRIMARY KEY (`ID`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `mobium_offers_export_props`(
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `EXPORT_PROP_ID` VARCHAR(255) COLLATE utf8_general_ci NOT NULL,
  `EXPORT_PROP` char(1) COLLATE utf8_general_ci NOT NULL DEFAULT 'Y',
  `EXPORT_NAME` varchar(255) COLLATE utf8_general_ci NULL,
  `EXPORT_SORT` int(11) NOT NULL DEFAULT 500,
  `PROP_IS_VENDOR_CODE` char(1) COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  `PROP_IS_TOOLTIP` char(1) COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  `TOOLTIP_OPTIONS` TEXT NULL,

  PRIMARY KEY (`ID`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `mobium_products_export_props`(
   `ID` int(11) NOT NULL AUTO_INCREMENT,
   `EXPORT_PROP_ID` VARCHAR(255) COLLATE utf8_general_ci NOT NULL,
   `EXPORT_PROP` char(1) COLLATE utf8_general_ci NOT NULL DEFAULT 'Y',
   `EXPORT_NAME` varchar(255) COLLATE utf8_general_ci NULL,
   `EXPORT_SORT` int(11) NOT NULL DEFAULT 500,
   `PROP_IS_VENDOR_CODE` char(1) COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
   `PROP_IS_TOOLTIP` char(1) COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
   `TOOLTIP_OPTIONS` TEXT NULL,

   PRIMARY KEY (`ID`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `mobium_register_session`(
   `ID` int(11) NOT NULL AUTO_INCREMENT,
   `APP_ID` INT(11) NOT NULL,
   `CREATED_AT` INT(11) NOT NULL,
   `DATA` TEXT NULL,
   PRIMARY KEY (`ID`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `mobium_auth_tries`(
    `APP_ID` int(11) NOT NULL,
    `DATA` TEXT NULL,
    PRIMARY KEY (`APP_ID`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;