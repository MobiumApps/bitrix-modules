if($updater->CanUpdateDatabase())
{
	if(!$updater->TableExists("mobium_verify"))
	{
			$updater->Query(array(
				"MySQL" => "CREATE TABLE IF NOT EXISTS `mobium_verify`(
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `CODE` varchar(50) NOT NULL,
  `CREATED_AT` DATETIME NOT NULL,
  `LIFETIME` int(11) DEFAULT 180,
  `APP_ID` int(11) NOT NULL,
  `USER_ID` int(11) NOT NULL,
  PRIMARY KEY (`ID`)
);",
			));
}	
}