<?php
/**
 * Database install queries.
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

$sql = array();

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'sendcloud_service_points` (
    `id_service_point` INT(10) NOT NULL AUTO_INCREMENT,
    `id_cart` INT(10) UNSIGNED NOT NULL,
    `id_address_delivery` INT(11) UNSIGNED DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NULL,
    PRIMARY KEY (`id_service_point`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8';

return $sql;
