<?php
/**
 * Utility class for SendCloud module.
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
 * Utility methods used by several entities in the SendCloud module.
 *
 *  @author    SendCloud Global B.V. <contact@sendcloud.eu>
 *  @copyright 2016 SendCloud Global B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  @category  Shipping
 *  @package   Sendcloud
 *  @link      https://sendcloud.eu
 */
class SendcloudTools
{
    /**
     * Get a more general representation of the current PrestaShop version
     *
     * @return string `ps15` or `ps16`, `ps17`
     * @throws PrestaShopException when a non-supported version is detected.
     */
    public static function getPSFlavor()
    {
        if (Tools::version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            return 'ps17';
        }

        if (Tools::version_compare(_PS_VERSION_, '1.6.0.0', '>=')) {
            return 'ps16';
        }

        if (Tools::version_compare(_PS_VERSION_, '1.5.0.0', '>=')) {
            return 'ps15';
        }

        throw new PrestaShopException('Unsupported PrestaShop version.');
    }

    /**
     * Get the URL to the Webservice documentation related to the current PrestaShop
     * version.
     *
     * @var string
     */
    public static function getWSDocs()
    {
        $ps_docs = array(
            'ps15' =>
                'http://doc.prestashop.com/display/PS15/' .
                'Using+the+PrestaShop+Web+Service',
            'ps16' =>
                'http://doc.prestashop.com/display/PS16/' .
                'Using+the+PrestaShop+Web+Service',
            'ps17' => 'http://doc.prestashop.com/display/PS17'
        );

        return $ps_docs[self::getPSFlavor()];
    }

    /**
     * Retrieve the SendCloud Panel URL. Testing and pointing to other environments
     * could be done by setting an env variable `SENDCLOUD_PANEL_URL` with
     * any URL that matches `sendclod.sc` (e.g: `sendcloud.sc.local`)
     *
     * @param string $path path to append to the base URL.
     * @param array|null $params Query params to include in the URL
     * @param bool $
     * @return string The URL to SendCloud Panel.
     */
    public static function getPanelURL($path = '', $params = null, $utm_tracking = false)
    {
        if (!is_array($params)) {
            $params = array();
        }

        $panel_url = getenv('SENDCLOUD_PANEL_URL');
        if (strpos($panel_url, 'sendcloud.sc') === false) {
            $panel_url = 'https://panel.sendcloud.sc';
        }

        $utm_params = array();
        if ($utm_tracking === true) {
            $utm_params = self::getUTMParams();
        }
        $query_params = array_merge($params, $utm_params);
        $query_string = '';
        if (count($query_params)) {
            $query_string = '?' . http_build_query($query_params);
        }

        return $panel_url . $path . $query_string;
    }

    /**
     * Check if UTM tracking is enabled for the module.
     *
     * @param Module Sendcloud module instance.
     */
    public static function isTrackingEnabled(Module $module)
    {
        // `trusted` here means this module is officially available through
        // PrestaShop addons marketplace.
        // We may explicitly disable UTM tracking on dev/test environments, even
        // with a `trusted` module in place.
        $disable_utm = getenv('SENDCLOUD_DISABLE_UTM') ?
            (bool)getenv('SENDCLOUD_DISABLE_UTM') : false;

        if (self::getPSFlavor() === 'ps15') {
            $module->trusted = true;
        }

        return $module->trusted && !$disable_utm;
    }

    /**
     * Retrieve the UTM parameters to send with external links pointing to
     * the SendCloud platform. It respects the value of the `SENDCLOUD_DISABLE_UTM`
     * environment variable, used to explicitly disable tracking (e.g: test
     * environments) and do not send UTM parameters.
     *
     * @param bool $trusted
     * @return array
     */
    public static function getUTMParams()
    {
        return array(
            'utm_source' => urlencode('PrestaShop Module'),
            'utm_medium' => urlencode('Plugins & Modules'),
            'utm_campaign' => urlencode('PrestaShop Partnership')
        );
    }

    public static function httpResponseCode($code = null)
    {
        $statuses = array(
            '100' => 'Continue',
            '101' => 'Switching Protocols',
            '200' => 'OK',
            '201' => 'Created',
            '202' => 'Accepted',
            '203' => 'Non-Authoritative Information',
            '204' => 'No Content',
            '205' => 'Reset Content',
            '206' => 'Partial Content',
            '300' => 'Multiple Choices',
            '301' => 'Moved Permanently',
            '302' => 'Moved Temporarily',
            '303' => 'See Other',
            '304' => 'Not Modified',
            '305' => 'Use Proxy',
            '400' => 'Bad Request',
            '401' => 'Unauthorized',
            '402' => 'Payment Required',
            '403' => 'Forbidden',
            '404' => 'Not Found',
            '405' => 'Method Not Allowed',
            '406' => 'Not Acceptable',
            '407' => 'Proxy Authentication Required',
            '408' => 'Request Time-out',
            '409' => 'Conflict',
            '410' => 'Gone',
            '411' => 'Length Required',
            '412' => 'Precondition Failed',
            '413' => 'Request Entity Too Large',
            '414' => 'Request-URI Too Large',
            '415' => 'Unsupported Media Type',
            '500' => 'Internal Server Error',
            '501' => 'Not Implemented',
            '502' => 'Bad Gateway',
            '503' => 'Service Unavailable',
            '504' => 'Gateway Time-out',
            '505' => 'HTTP Version not supported',
        );

        $code = $code === null ? '200' : $code;
        $text = isset($statuses[$code]) ? $statuses[$code] : $statuses['200'];

        if (function_exists('http_response_code')) {
            return http_response_code((int)$code);
        } else {
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            header($protocol . ' ' . $code . ' ' . $text);
            return $code;
        }
    }
}
