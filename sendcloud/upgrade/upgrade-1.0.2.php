<?php
/**
 * Enable CGI mode if running with CGI interfaces.
 *
 * Or do nothing if running PrestaShop under Apache module.
 *
 * @author    SendCloud Global B.V. <contact@sendcloud.eu>
 * @copyright 2016 SendCloud Global B.V.
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @category  Shipping
 * @package   Sendcloud
 * @link      https://sendcloud.eu
 */

function upgrade_module_1_0_2()
{
    $interface = Tools::strtolower(php_sapi_name());
    if (preg_match('/cgi/i', $interface)) {
        return Configuration::updateValue('PS_WEBSERVICE_CGI_HOST', 1);
    }

    // Do nothing for apache module users.
    return true;
}
