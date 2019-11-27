<?php
Mobium\Api\ExportYML::run();
Mobium\Api\ExportBalance::run();

$this->arResult = CAgent::AddAgent(
	'Mobium\Api\ExportYML::run();',
	'mobium.api',
	'N',
	86400
);

$this->arResult = CAgent::AddAgent(
	'Mobium\Api\ExportBalance::run();',
	'mobium.api',
	'N',
	86400
);