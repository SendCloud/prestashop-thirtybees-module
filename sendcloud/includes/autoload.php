<?php
/**
 * Autoloading logic to match `Sendcloud` prefixed classes.
 *
 * PHP version 5
 *
 *  @author    SendCloud Global B.V. <contact@sendcloud.eu>
 *  @copyright 2016 SendCloud Global B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  @category  Shipping
 *  @package   Sendcloud
 *  @link      https://sendcloud.eu
 */

spl_autoload_register(function ($className) {
    if (preg_match('/^Sendcloud/', $className)) {
        $fileName = str_replace('Sendcloud', '', $className) . '.php';

        $paths = array(
            dirname(dirname(__FILE__)) . '/classes/' . $fileName,
            dirname(dirname(__FILE__)) . '/classes/exceptions/' . $fileName
        );

        foreach ($paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                return true;
            }
        }
    }
    return false;
});
