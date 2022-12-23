<?php
/**
 * Add support to PrestaShop 1.7.x
 *
 * @author    Sendcloud Global B.V. <contact@sendcloud.eu>
 * @copyright 2016 Sendcloud Global B.V.
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *
 * @category  Shipping
 *
 * @see      https://sendcloud.eu
 */
function upgrade_module_1_3_0($module)
{
    $module->registerHook('displayBeforeCarrier');
    Tools::clearSmartyCache();

    $connector = new SendcloudConnector($module->name);
    $allShops = Shop::getCompleteListOfShopsID();
    foreach ($allShops as $id) {
        $connector->updateCarrierSelection(new Shop($id));
    }

    return true;
}
