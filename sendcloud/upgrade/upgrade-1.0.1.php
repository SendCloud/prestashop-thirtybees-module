<?php
/**
 * Removes old serialized sendcloud connections.
 *
 * Switch from `serialize`/`unserialize` to json encode/decode. Due to the
 * nature of `serialize`/`unserialize` if a change in the database value
 * is made and includes malicious code, an attacker could inject
 * it in the execution context.
 *
 * This is a breaking change and will require users to click `Connect with SendCloud`
 * again to make service points available again. We are explicitly not migrating any
 * value to JSON because we can't do any assumptions if the existing data was changed
 * or not, deleting all connections here is the safest option.
 *
 * No `WebserviceKey` is deleted in the process due to the fact this will make the API
 * to stop working, making the SendCloud panel not able to process the Shop
 * orders.
 *
 * @author    SendCloud Global B.V. <contact@sendcloud.eu>
 * @copyright 2016 SendCloud Global B.V.
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @category  Shipping
 * @package   Sendcloud
 * @link      https://sendcloud.eu
 */

function upgrade_module_1_0_1()
{
    $remove_sql = sprintf(
        "DELETE FROM `%s` WHERE name = '%s'",
        pSQL(_DB_PREFIX_ . 'configuration'),
        pSQL(SendcloudConnector::SETTINGS_CONNECT)
    );
    return Db::getInstance()->execute($remove_sql);
}
