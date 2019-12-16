<?php

// override these settings in your own config.php
// log directory is relative to this file's directory.
$logdir = dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . "log" . DIRECTORY_SEPARATOR;
return array(
	'server'         => array(
		'log' => $logdir . "server.log",
		'log_theme'=>"Soap Server error",
		'log_verbose'=>true,
		'server_url'=>'',
		'wsdl_namespace'=>'',
		'wsdl_target_url'=>''
	),
	'client'         => array(
		'log'         => $logdir . "client.log",
		'log_theme'   => "Soap Client Error",
		'log_verbose' => true,
		'wsdl'        => '',
		'trace'       => true,
		'login'       => '',
		'password'    => '',
		'timeout'     => 60
	),
	'queue'          => array(
		'log'        => $logdir . "queue.log",
		'log_theme'  => 'Queue error',
		'list_limit' => 140
	),
	'log_recipients' => array( )
);