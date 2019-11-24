<?php
/**
 * test config
 * server[] = test 1c server
 * log_recipients = dev + info + manager + it departament
 */
return array(
//	'server'         => array(
//		 'server_url'      => 'http://new.bethowen.ru/soapservice/serverWsdl.php?WSDL',
//		 'wsdl_namespace'  => 'http://new.bethowen.ru/soapservice/serverWsdl.php?WSDL',
//		 'wsdl_target_url' => 'http://new.bethowen.ru/soapservice/index.php',
//		'log_theme' => "TEST Soap Server Error"
//	),
    'server'         => array(
        'server_url'      => 'http://devm.bethowen.ru/soapservice/serverWsdl.php?WSDL',
        'wsdl_namespace'  => 'http://devm.bethowen.ru/soapservice/serverWsdl.php?WSDL',
        'wsdl_target_url' => 'http://devm.bethowen.ru/soapservice/index.php'
    ),
	'client'         => array(
		'wsdl'      => 'http://olga.petretail.ru/test_im/website.1cws?wsdl',
		// override soap location in wsdl with custom address:port
		'login'     => 'bethowen',
		'password'  => 'tryWn3iEq',
		'log_theme' => "TEST Soap Client Error",
		'firm_inn'=>'7726650932'

	),
//    'client'         => array(
//        // 'wsdl'     => 'http://87.236.82.163/UTRC/WebSite.1cws?wsdl',
//        'wsdl'     => 'http://realtime.petretail.ru/UTRC/WebSite.1cws?wsdl', // TMP -addr from 19.08 13:20
//        'login'    => 'bethowen',
//        'password' => 'tryWn3iEq',
//        'firm_inn'=>'7726650932'
//
//    ),
	'log_recipients' => array()
	// 'log_recipients' => array(
	// 	'dmz9@yandex.ru',
	// 	'info@ipolh.com',
	// 	'dergunov.va@petretail.ru',
	// 	'list_department_it@petretail.ru'
	// ),
);