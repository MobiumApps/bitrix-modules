<?php
namespace Mobium\Api\DeliveryType\AdminInterface;

use DigitalWand\AdminHelper\Helper\AdminListHelper;
use Bitrix\Main\Localization\Loc;

Loc::loadCustomMessages(__FILE__);
class DeliveryTypeListHelper extends AdminListHelper
{
    protected static $model = '\Mobium\Api\DeliveryType\DeliveryTypeTable';
}

