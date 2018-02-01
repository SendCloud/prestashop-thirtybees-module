<?php
/**
 * Unsynchronised carrier exceptions.
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

/**
 * Raised when the plugin detects an unsynchronisation between the current active
 * carrier and the latest carrier reference saved in the configurations.
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
class SendcloudUnsynchronisedCarrierException extends PrestaShopException
{
    public $carrierFound;
    public $currentCarrier;

    public function __construct($carrier_found, $current_carrier)
    {
        $this->carrierFound = $carrier_found;
        $this->currentCarrier = $current_carrier;

        parent::__construct(
            'Current carrier referenced by module does not match active carrier.'
        );
    }
}
