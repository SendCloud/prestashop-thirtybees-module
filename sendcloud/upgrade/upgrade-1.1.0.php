<?php
/**
 * Add support to PrestaShop 1.7.x
 *
 * @author    SendCloud Global B.V. <contact@sendcloud.eu>
 * @copyright 2016 SendCloud Global B.V.
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @category  Shipping
 * @package   Sendcloud
 * @link      https://sendcloud.eu
 */

function upgrade_module_1_1_0($module)
{
    return
        $module->registerHook('actionCarrierUpdate') &&
        $module->registerHook('displayCarrierExtraContent');
}
