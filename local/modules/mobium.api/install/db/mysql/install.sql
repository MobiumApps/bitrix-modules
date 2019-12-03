CREATE TABLE `mobium_registration_fields` (
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

CREATE TABLE `mobium_user_token`(
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `BODY` varchar(50) COLLATE utf8_general_ci NOT NULL,
  `CREATED_AT` int(11) NOT NULL,
  `LIFETIME` int(11) DEFAULT 0,
  `TYPE` varchar(50) COLLATE utf8_general_ci NOT NULL,
  `USER_ID` int(11) NOT NULL,
  PRIMARY KEY (`ID`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE `mobium_delivery_type_assoc`(
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ACTIVE` char(1) COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  `DELIVERY_SERVICE_ID_BITRIX` varchar(100) COLLATE utf8_general_ci NOT NULL,
  `DELIVERY_SERVICE_ID_MOBIUM` varchar (100) COLLATE utf8_general_ci NOT NULL,
  `DELIVERY_SERVICE_AREA_ID` varchar(100) collate utf8_general_ci NOT NULL,
  PRIMARY KEY (`ID`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE `mobium_offers_export_props`(
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

CREATE TABLE `mobium_products_export_props`(
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

CREATE TABLE `mobium_register_session`(
   `ID` int(11) NOT NULL AUTO_INCREMENT,
   `APP_ID` INT(11) NOT NULL,
   `CREATED_AT` INT(11) NOT NULL,
   `DATA` TEXT NULL,
   PRIMARY KEY (`ID`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE `mobium_auth_tries`(
    `APP_ID` int(11) NOT NULL,
    `DATA` TEXT NULL,
    PRIMARY KEY (`APP_ID`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;