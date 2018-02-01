<?php
/**
 * Update requested permissions from users.
 *
 * Additional 'states' and 'products' permissions asked
 * to retrieve products weight and states when required.
 *
 * @author    SendCloud Global B.V. <contact@sendcloud.eu>
 * @copyright 2016 SendCloud Global B.V.
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @category  Shipping
 * @package   Sendcloud
 * @link      https://sendcloud.eu
 */

function upgrade_module_1_0_3($module)
{

    $connector = new SendcloudConnector($module->name);
    $settings = $connector->getSettings();
    return WebserviceKey::setPermissionForAccount($settings['id'], $connector->getAPIPermissions());
}
