<?php
/**
 * Holds the main administration screen controller of the module.
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
 * Controls the main administration screen of the Module.
 *
 *  @author    SendCloud Global B.V. <contact@sendcloud.eu>
 *  @copyright 2016 SendCloud Global B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  @category  Shipping
 *  @package   Sendcloud
 *  @link      https://sendcloud.eu
 */
class AdminSendcloudController extends ModuleAdminController
{
    /**
     * Connector class instance.
     *
     * @var Connector
     */
    private $connector;

    /**
     * SendCloud module instance. It's assigned automatically by PrestaShop
     * infrastructure.
     *
     * @var SendcloudShipping
     */
    public $module;


    /**
     * Configure the administration controller and define some sane defaults.
     */
    public function __construct()
    {
        $this->bootstrap = true;
        $this->display =  'view';
        parent::__construct();

        // Line too long but PS translation tool doesn't recognise it when splitted
        // on multiple lines.
        $this->meta_title = $this->module->getMessage('smart_shipping');
        if (!$this->module->active) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminHome'));
        }

        $this->connector = $this->module->connector;
    }

    /**
     * Injects assets in the administration page. PrestaShop < 1.6 will have
     * additional Bootstrap styles, to make it consistent with more modern
     * module architecture.
     *
     * @return string
     */
    public function setMedia($isNewTheme = false)
    {
        $assets_base = _MODULE_DIR_ . $this->module->name;

        $this->addJquery();
        $this->addJS($assets_base . '/views/js/admin/configure.js');
        $this->addCSS(
            array(
            $assets_base . '/views/css/backoffice.css',
            )
        );

        return parent::setMedia($isNewTheme);
    }

    /**
     * Change the toolbar title to not include anything other than what's explicitly
     * set here.
     *
     * @return null
     */
    public function initToolbarTitle()
    {
        $this->toolbar_title[] = $this->module->getMessage('admin');
        $this->toolbar_title[] = 'SendCloud';
    }

    /**
     * Render the main administration screen of the module.
     *
     * @see    views/templates/admin/sendcloud/helpers/view/view.tpl
     * @return string
     */
    public function renderView()
    {
        $can_connect = $this->connector->canConnect();
        if (!$can_connect) {
            $this->errors[] = $this->module->getMessage('cant_connect');
        }

        $selectedCarriers = $this->connector->getSelectedCarriers();
        $carriers = array();
        foreach ($selectedCarriers as $code => $name) {
            $carrier = $this->connector->getOrSynchroniseCarrier($code);
            $editLink = '';
            if ($carrier && !$carrier->deleted) {
                $carriersLink = $this->context->link->getAdminLink('AdminCarrierWizard');
                $editLink = $carriersLink . '&id_carrier=' . $carrier->id;
            } else {
                continue;
            }
            $thumbnailName = "carrier_mini_{$carrier->id}_{$this->context->shop->id}.jpg";
            $imagePath = _PS_SHIP_IMG_DIR_.'/'.$carrier->id.'.jpg';

            $thumbnail = ImageManager::thumbnail($imagePath, $thumbnailName, 45);
            // PS enforces escaping variables in templates, and the automatic validation will fail - even for trivial
            // and controlled values, like this one produced by a PrestaShop API itself. Get the URL of the
            // thumbnail and render the image ourselves in the template.
            $matches = array();
            preg_match('/^<img.+src="([^"]+)".*>$/i', $thumbnail, $matches);
            $thumbnailURL = count($matches) >= 2 ? $matches[1] : '';
            $carriers[] = array(
                'code' => $code,
                // NOTE: this is the name used @ Sendcloud (i.e Chronopost, DPD, UPS), not the PrestaShop carrier name
                // (which the merchant is free to change)
                'name' => $name,
                // end NOTE
                'instance' => $carrier,
                'edit_link' => $editLink,
                'thumbnail' => $thumbnailURL
            );
        }

        $goto_panel_url = SendcloudTools::getPanelURL(
            '',
            null,
            SendcloudTools::isTrackingEnabled($this->module)
        );

        $connect_url = $this->processConfiguration();

        $this->base_tpl_view = 'view.tpl';
        $this->tpl_view_vars = array(
            'can_connect' => $can_connect,
            'multishop_warning' => $this->module->getMultishopWarningImage(),
            'prestashop_flavor' => SendcloudTools::getPSFlavor(),
            'prestashop_webservice_docs' => SendcloudTools::getWSDocs(),
            'sendcloud_panel_url' => $goto_panel_url,
            'api_resources' => $this->connector->getAPIResources(),
            'is_connected' => $this->connector->isConnected(),
            'connect_settings' => $this->connector->getSettings(),
            'service_point_script' => $this->connector->getServicePointScript(),
            'service_point_warning' => $this->getWarning($carriers),
            'service_point_carriers' => $carriers,
            'connect_url' => $connect_url
        );

        return parent::renderView();
    }

    /**
     * Process data from Configuration page after form submission. It will
     * redirect to the SendCloud panel (if possible) or return the connection
     * URL to use in a fallback redirect.
     *
     * @return null|string the connect URL to redirect the user to SendCloud.
     */
    protected function processConfiguration()
    {
        if (Tools::isSubmit('new_key')) {
            $connect_url = $this->processConnect();
            if (!headers_sent()) {
                // Directly redirect to SendCloud Panel, if possible
                Tools::redirect($connect_url);
                exit;
            } else {
                $this->informations[] = $this->module->getMessage('connection_done');
            }
            // Get the URL to use a fallback redirect.
            return $connect_url;
        }

        if ($this->connector->isConnected()) {
            // Line too long but PS translation tool doesn't recognise it when splitted
            // on multiple lines.
            $this->informations[] = $this->module->getMessage('already_connected');
        }
    }

    /**
     * Retrieve a service point related warning message based in its status.
     *
     * By default the lack of SendCloud connection and the lack of the service point
     * script are not going to be reported as a warning.
     *
     * @param array $carriers List of carriers to check against status and determine the warnings
     * @return string The translated warning message.
     */
    private function getWarning(array $carriers)
    {
        $shop_id = Shop::getContextShopID(false);
        if ($shop_id === null) {
            return '';
        }

        if (!$this->connector->isConnected()) {
            return $this->module->getMessage('warning_no_connection');
        }

        $config = $this->connector->getServicePointScript();

        if (!$config) {
            return $this->module->getMessage('warning_no_configuration');
        }

        /*
        Warn about the carriers if:
            - No selected carrier configuration is found
            - No (PS) carriers were created (i.e.: no )
            - Any (PS) carrier is deactivated or deleted
            - Any (PS) carrier does not have zone (prices) configuration
            - Any (PS) carrier is not associated with the current shop.
        */
        if (empty($carriers)) {
            return $this->module->getMessage('warning_carrier_not_found');
        }

        // TODO: Get better warnings and pinpoint which carriers are problematic.
        foreach ($carriers as $item) {
            $carrier = $item['instance'];

            if (!$carrier->active && !$carrier->deleted) {
                return $this->module->getMessage('warning_carrier_inactive');
            }

            if ($carrier->deleted) {
                return $this->module->getMessage('warning_carrier_deleted');
            }

            $available_zones = $carrier->getZones();
            if (empty($available_zones)) {
                return $this->module->getMessage('warning_carrier_zones');
            }

            $carrier_shops = $carrier->getAssociatedShops();
            if (!in_array($shop_id, $carrier_shops)) {
                return $this->module->getMessage('warning_carrier_disabled_for_shop');
            }

            if ($this->connector->isRestricted($carrier, $this->context->shop)) {
                return $this->module->getMessage('warning_carrier_restricted');
            }
        }
    }

    /**
     * Execute all the heavy work related to the creation of a SendCloud connection.
     *
     * @return bool wheater the connection was successfull or not
     */
    private function processConnect()
    {
        try {
            $this->connector->connect(
                $this->module->getMessage('api_key'),
                $this->getOrGenerateAPIKey()
            );
        } catch (SendcloudMissingAPIKeyException $e) {
            $this->errors[] = $this->module->getMessage('missing_api_key');
            return false;
        }

        $path = getenv('THIRTYBEES_VERSION') ? '/shops/thirtybees/connect/' : '/shops/prestashop/connect/';
        $data = $this->connector->getSettings();
        $shop = $this->context->shop;

        $query_params = array(
            'url_webshop' => $shop->getBaseURL(),
            'api_key' => $data['key'],
            'shop_name' => $shop->name,
            'shop_id' => $shop->id,
        );

        $connect_url = SendcloudTools::getPanelURL(
            $path,
            $query_params,
            SendcloudTools::isTrackingEnabled($this->module)
        );

        return $connect_url;
    }

    /**
     * Get the submitted key so we rely in the built-in key generation functions
     * *or* generate a new string to use as an API Key.
     *
     * We experienced some online store backoffices with problems to
     * inject jQuery in the global scope at the time the JS code generation function
     * is executed.
     * We do our best to inject *and* detect jQuery and use it when available,
     * otherwise rely on the server-side implementation with no penalties
     * to the user.
     *
     * Note: O/0 is left out to avoid confusion (just like the built-in functions)
     *
     * @return string
     */
    private function getOrGenerateAPIKey()
    {
        $key = preg_replace('/\s\s*/im', '', Tools::getValue('new_key'));
        if (!empty($key)) {
            return $key;
        }

        $key = Tools::strtoupper(md5(rand() . time() . $this->module->name));
        $key = preg_replace('/[O0]/i', 'A', $key);

        return Tools::substr($key, 0, 32);
    }
}
