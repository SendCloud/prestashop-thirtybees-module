<?php
/**
 * SendCloud | Smart Shipping Service
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

// Autoload required, ignoring PSR-1 2.3
require_once dirname(__FILE__) . '/includes/autoload.php';

/**
 * Main SendCloud Shipping module class.
 *
 * It coordinates the module screens, installation, updgrades, activation and
 * deactivation of the Module.
 *
 * @author    SendCloud Global B.V. <contact@sendcloud.eu>
 * @copyright 2016 SendCloud Global B.V.
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @category  Shipping
 * @package   Sendcloud
 * @link      https://sendcloud.eu
 */
class Sendcloud extends CarrierModule
{
    /**
     * Translatable messages used by the module. We now keep all translatable
     * string in the module for simplicity. Since controllers has access to the
     * module instance, it's easier to manage translations that way.
     *
     * We also centralize all warnings reported by validator.prestashop.com regarding
     * long lines into a single file instead of spreading all over the module files.
     *
     * @var array
     */
    private $messages;


    /**
     * @var SendcloudConnector
     */
    public $connector;

    /**
     * Set the initial data
     */
    public function __construct()
    {
        $this->boostrap = true;
        $this->name = 'sendcloud';
        $this->tab = 'shipping_logistics';
        $this->version = '1.1.3';
        $this->author = 'SendCloud Global B.V.';
        $this->author_uri = 'https://sendcloud.eu';
        $this->need_instance = false;
        $this->ps_versions_compliancy = array('min' => '1.5','max'=> '1.7');
        $this->module_key = 'ee5ffe2f68aefd272e994aa5a26e6224';

        parent::__construct();

        $this->displayName = $this->l('SendCloud | Europe\'s Number 1 Shipping Tool', $this->name);

        /**
         * Using line breaks makes translations to _not_ work properly. We centralize most translatable strings here
         * to avoid spreading them in the module and to ease code review and limit usage of the coding standards
         * ignore comment below.
         */
        // @codingStandardsIgnoreStart
        $this->messages = array(
            'admin' => $this->l('Administration', $this->name),
            'already_connected' => $this->l('You already have connected with SendCloud before. You may connect again to update your Integration.', $this->name),
            'api_key' => $this->l('SendCloud API Key', $this->name),
            'cant_connect' => $this->l('You must configure a URL for this Shop before connecting with SendCloud.', $this->name),
            'connection_done' => $this->l('Your connection is almost done. Redirecting to the SendCloud Panel.', $this->name),
            'missing_api_key' => $this->l('Missing API key. Unable to connect with SendCloud.', $this->name),
            'no_service_point' => $this->l('No service point data found.', $this->name),
            'service_point_details' => $this->l('Service Point Details', $this->name),
            'smart_shipping' => $this->l('Smart shipping service for your online store. Save time and shipping costs.', $this->name),
            'unable_to_parse' => $this->l('Unable to parse service point data.', $this->name),
            'warning_carrier_deleted' => $this->l('Service Point Delivery carrier is not active. Activate the Carrier before using this feature.', $this->name),
            'warning_carrier_disabled_for_shop' => $this->l('The Service Point Delivery carrier is not enabled for the current active Shop.', $this->name),
            'warning_carrier_inactive' => $this->l('Service Point Delivery carrier is not active. Activate the Carrier before using this feature.', $this->name),
            'warning_carrier_not_found' => $this->l('Service Points were enabled but are not configured properly. Activate Service Points from the SendCloud Panel before using this feature.', $this->name),
            'warning_carrier_restricted' => $this->l('There are no Payment Methods associated with the Service Point Delivery carrier. Customers will not be able to select it during checkout', $this->name),
            'warning_carrier_zones' => $this->l('You must enable at least one shipping location for the Service Point Delivery carrier before using this feature.', $this->name),
            'warning_no_configuration' => $this->l('Service Points are not enabled. Please enable them on your SendCloud Panel before using this feature.', $this->name),
            'warning_no_connection' => $this->l('You must connect with SendCloud before using this feature.', $this->name),
        );

        $this->description = $this->l('SendCloud helps to grow your online store by optimizing the shipping process. Shipping packages have never been that easy!', $this->name);
        $this->confirmUninstall = $this->l('After uninstalling you will not be able to see your orders in the SendCloud Panel. Are you sure?', $this->name);
        // @codingStandardsIgnoreEnd

        // Set the warnings in the Module listing page in the Back Office.
        $this->connector = new SendcloudConnector($this->name);
    }

    public function getMessage($identifier)
    {
        if (!isset($this->messages[$identifier])) {
            // Explicitly forbid someone to retrieve e non-defiend message
            throw new PrestaShopException('Message identifier not found.');
        }
        return $this->messages[$identifier];
    }

    /**
     * Install this module
     *
     * @return boolean
     */
    public function install()
    {
        return parent::install() &&
            $this->installSQL() &&
            $this->installTab() &&
            $this->registerHook('actionCarrierProcess') &&
            $this->registerHook('actionObjectAddAfter') &&
            $this->registerHook('actionObjectDeleteAfter') &&
            $this->registerHook('actionEmailAddAfterContent') &&
            $this->registerHook('displayAdminOrderContentShip') &&
            $this->registerHook('displayAdminOrderTabShip') &&
            $this->registerHook('displayAdminOrder') &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('displayOrderConfirmation') &&
            $this->registerHook('displayOrderDetail') &&
            $this->registerHook('displayPDFDeliverySlip') &&
            // Pre 1.7 Hooks
            $this->registerHook('updateCarrier') &&
            $this->registerHook('displayCarrierList') &&
            // PrestaShop 1.7+ only hooks
            $this->registerHook('actionCarrierUpdate') &&
            $this->registerHook('displayCarrierExtraContent') &&
            true
            ;
    }

    /**
     * Uninstall this module.
     *
     * @return boolean
     */
    public function uninstall()
    {
        return
            $this->connector->disconnect() &&
            $this->uninstallSQL() &&
            $this->uninstallTab() &&
            parent::uninstall();
    }

    /**
     * Hook after a new entity is added.
     *
     * @param  array parameters received by the hook, contain the target ` $object`.
     * @return null
     */
    public function hookActionObjectAddAfter(array $params)
    {
        $object = isset($params['object']) ? $params['object'] : null;
        $shop = $this->context->shop;
        try {
            $this->connector->activateServicePoints($shop, $object);
        } catch (SendcloudServicePointException $e) {
            $webservice = WebserviceRequest::getInstance();
            $webservice->errors[] = array(
                400,
                $this->l('Unable to activate service points. Please try again', $this->name)
            );
        }
    }

    /**
     * Deactivate service points feature entirely.
     *
     * @param  array $params
     * @return void
     */
    public function hookActionObjectDeleteAfter(array $params)
    {
        $object = isset($params['object']) ? $params['object'] : null;
        $shop = $this->context->shop;
        try {
            $this->connector->deactivateServicePoints($shop, $object);
        } catch (SendcloudServicePointException $e) {
            $webservice = WebserviceRequest::getInstance();
            // Line too long but PS translation tool doesn't recognise it when splitted
            // on multiple lines.
            $webservice->errors[] = $this->l('Unable to deactivate service points completely.', $this->name);
        }
    }

    /**
     * Track changes in the installed service point carrier (pre 1.7).
     *
     * @param array $params hook parameters containing the new carrier
     */
    public function hookUpdateCarrier(array $params)
    {
        if (Tools::version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            return;
        }

        $this->connector->updateCarrier($params['new_carrier']);
    }


    /**
     * Track changes in the installed service point carrier (post 1.7)
     *
     * * @param array $params hook parameters containing the new carrier.
     */
    public function hookActionCarrierUpdate(array $params)
    {
        if (Tools::version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
            return;
        }
        $carrier = isset($params['carrier']) ? $params['carrier'] :
            // Look for the old parameter as well.
            isset($params['new_carrier']) ? $params['new_carrier'] : null;
        $this->connector->updateCarrier($carrier);
    }

    /**
     * Display the service point selection button.
     *
     * @param array $params
     */
    public function hookDisplayCarrierList(array $params)
    {
        if (Tools::version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            return '';
        }
        return $this->displayServicePointButton($params);
    }

    /**
     * Display the service point button for PrestaShop 1.7+
     *
     * @since 1.1.0
     * @param array $params
     * @return string
     */
    public function hookDisplayCarrierExtraContent(array $params)
    {
        return $this->displayServicePointButton($params);
    }

    /**
     * Return the markup for the service point selection button.
     *
     * @since 1.1.0
     * @param array $params
     * @return string
     */
    private function displayServicePointButton(array $params)
    {
        $cart = isset($params['cart']) ? $params['cart'] : null;

        if (!$cart || !$cart->id_address_delivery || !$this->servicePointsAvailable()) {
            return '';
        }

        $carrier = $this->connector->getOrSynchroniseCarrier();
        $address = new Address($cart->id_address_delivery);
        $country = new Country($address->id_country);

        $point = SendcloudServicePoint::getFromCart($cart->id);

        $link = $this->context->link;
        $this->smarty->assign(array(
            'prestashop_flavor' => SendcloudTools::getPSFlavor(),
            'carrier' => $carrier,
            'cart' => $cart,
            'to_country' => $country->iso_code,
            'to_postal_code' => $address->postcode,
            'language' => $this->context->language->language_code,
            'service_point_details' => $point->details,
            'save_endpoint' => $link->getModuleLink($this->name, 'ServicePointSelection')
        ));

        return $this->display(
            __FILE__,
            'views/templates/hook/carrier-selection.tpl'
        );
    }

    /**
     * Inject the required front office assets (CSS and JavaScript) to enable
     * make the service point selection work in the checkout page.
     *
     * @param  array $params
     * @return string Additional header HTML to be added in the front office.
     */
    public function hookDisplayHeader($params)
    {
        $cart = isset($params['cart']) ? $params['cart'] : null;
        $controller = isset($this->context->controller) ?
            $this->context->controller : null;

        $allowed_controllers = array(
            'HistoryController',
            'OrderConfirmationController',
            'OrderController',
            'OrderOpcController',
        );

        $is_allowed = !is_null($controller) &&
            in_array(get_class($controller), $allowed_controllers);

        if (!$is_allowed || !$cart) {
            // Load assets just in the order-related controllers.
            return '';
        }

        if (!$this->servicePointsAvailable()) {
            return '';
        }

        $script = $this->connector->getServicePointScript();

        if (Tools::version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $controller->registerStylesheet(
                'module-sendcloud-frontstyles',
                'modules/' . $this->name . '/views/css/front.css',
                array('media' => 'screen')
            );

            $controller->registerJavascript(
                'module-sendcloud-script',
                $script,
                array('server' => 'remote')
            );
        } else {
            $controller->addCSS($this->_path . '/views/css/front.css');
            $controller->addJquery();
            $controller->addJS($script, false);
        }
    }

    public function hookDisplayBackOfficeHeader(array $params)
    {
        $allowed_controllers = array(
            'AdminOrdersController'
        );
        $controller = get_class($this->context->controller);

        if (!in_array($controller, $allowed_controllers)) {
            return;
        }

        $backoffice_css = Tools::toUnderscoreCase($controller);
        $this->context->controller->addCSS($this->_path. "views/css/backoffice/{$backoffice_css}.css");
    }

    /**
     * With a multi-step checkout the process carrier hook is called. It saves
     * the service point details in the database (or skip it if service points
     * were not enabled at all).
     *
     * With OPC style checkout, the only option is to save through
     * `SendcloudShippingServicePointSelectionModuleFrontController`
     *
     * We keep this as a fall back to the service point selection controller
     * for multi-step checkouts as a last resource to save the service point
     * information.
     *
     * @param  array $params
     * @return bool `true` if service point info was saved/skipped correctly.
     * @see    SendcloudShippingServicePointSelectionModuleFrontController
     */
    public function hookActionCarrierProcess(array $params)
    {
        $cart = isset($params['cart']) ? $params['cart'] : null;
        if (!$cart || !$this->servicePointsAvailable()) {
            return false;
        }

        $carrier = $this->connector->getOrSynchroniseCarrier();

        if ($cart->id_carrier != $carrier->id) {
            // A user may not enable service points at all may
            // selected another carrier.
            return true;
        }

        $details = Tools::getValue('sendcloudshipping_service_point');
        if ($details && !$this->saveServicePoint($cart, urldecode($details))) {
            // Line too long but PS translation tool doesn't recognise it when splitted
            // on multiple lines.
            $this->context->controller->errors[] = $this->l('Unable to save service point information.', $this->name);
        }
        return true;
    }

    /**
     * Check all the requirements to make service points available in the Frontoffice.
     *
     * - Shop *must* have a connection with SendCloud
     * - There's a Service Point script configuration
     * - Service Point carrier exists and it's correclty configured
     * - The Shop has a relation with the carrier (when using Multistore the admin may
     *   disable the carrier for certain shops.)
     *
     * @return bool `true` if every requirement is met.
     */
    public function servicePointsAvailable()
    {
        if (!$this->connector->isConnected()) {
            return false;
        }


        $config = $this->connector->getServicePointScript();

        if (!$config) {
            return false;
        }

        $carrier = $this->connector->getOrSynchroniseCarrier();
        if (!$carrier || !$carrier->active || $carrier->deleted) {
            return false;
        }
        $carrier_shops = $carrier->getAssociatedShops();
        $shop = Context::getContext()->shop;

        if (!in_array($shop->id, $carrier_shops)) {
            return false;
        }

        $shipping_zones = $carrier->getZones();
        if (empty($shipping_zones)) {
            return false;
        }

        if ($this->connector->isRestricted($carrier, $shop)) {
            return false;
        }

        return true;
    }

    /**
     * Add the service point details to the order confirmation e-mail
     * sent to the customer.
     *
     * @param array $params
     */
    public function hookActionEmailAddAfterContent(array $params)
    {
        $template = $params['template'];
        if ('order_conf' != $template) {
            return;
        }

        $cart = $this->context->cart;
        $point = SendcloudServicePoint::getFromCart($cart->id);
        if (!$point->id || !$point->details) {
            return;
        }

        $this->smarty->assign(
            array(
            'point_details' => $point->getDetails()
            )
        );

        $details_html = $this->display(
            __FILE__,
            'views/templates/hook/mail-order-confirmation.html'
        );

        $details_txt = $this->display(
            __FILE__,
            'views/templates/hook/mail-order-confirmation.txt'
        );

        $template_html = str_replace(
            '{delivery_block_html}',
            '{delivery_block_html}' . $details_html,
            $params['template_html']
        );
        $params['template_html'] = $template_html;

        $template_txt = str_replace(
            '{delivery_block_txt}',
            '{delivery_block_txt}' . $details_txt,
            $params['template_txt']
        );
        $params['template_txt'] = $template_txt;
    }

    /**
     * After the payment being successfuly accepted the order is created and
     * a confirmation screen is shown. We use it to send the service
     * point and order details back to SendCloud.
     *
     * @param  array $params
     * @return string
     */
    public function hookDisplayOrderConfirmation(array $params)
    {
        $order = isset($params['objOrder']) ? $params['objOrder'] : null;
        // 1.7+ uses `order` parameter.
        $order = isset($params['order']) ? $params['order'] : null;

        if (!$order || !$this->servicePointsAvailable()) {
            return '';
        }

        $carrier = $this->connector->getOrSynchroniseCarrier();
        $cart = new Cart($order->id_cart);
        if (!$cart || $cart->id_carrier != $carrier->id) {
            return '';
        }

        $shop = $this->context->shop;
        $point = SendcloudServicePoint::getFromCart($order->id_cart);
        $delivery_address = new Address($order->id_address_delivery);
        $this->smarty->assign(array(
            'order' => $order,
            'shop_url' => $shop->getBaseURL(),
            'prestashop_flavor' => SendcloudTools::getPSFlavor(),
            'delivery_address' => $delivery_address,
            'point_details' => $point->getDetails(),
            'txt_service_point_details' => $this->getMessage('service_point_details'),
        ));
        return $this->display(
            __FILE__,
            'views/templates/hook/order-confirmation.tpl'
        );
    }

    /**
     * Tabs are not supported on PrestaShop 1.5, so we hook into the
     * `displayAdminOrder` to show service point details, when available.
     *
     * @see SendcloudShipping::hookDisplayAdminOrderTabShip
     * @see SendcloudShipping::hookDisplayAdminOrderContentShip
     */
    public function hookDisplayAdminOrder($params)
    {
        if (SendcloudTools::getPSFlavor() != 'ps15') {
            return '';
        }
        $id_order = isset($params['id_order']) ? $params['id_order']: null;
        $order = new Order($id_order);

        return $this->displayAdminOrderServicePoint($order);
    }

    /**
     * Display the tab element for service point details in the Admin
     * order editing view.
     *
     * @param  array $params
     * @return string
     */
    public function hookDisplayAdminOrderTabShip(array $params)
    {
        $order = isset($params['order']) ? $params['order'] : null;
        $point = $this->getOrderServicePoint($order);
        if (!$point) {
            return '';
        }
        $this->smarty->assign(
            array(
            'prestashop_flavor' => SendcloudTools::getPSFlavor(),
            'point_details' => $point->getDetails(),
            'txt_service_point_details' => $this->getMessage('service_point_details'),
            )
        );
        return $this->display(__FILE__, 'views/templates/hook/admin-order-tab-shipping.tpl');
    }

    /**
     * Display the *contents* of the tab containinig the service point details
     * in the Admin order editing view.
     *
     * @param  array $params
     * @return string
     */
    public function hookDisplayAdminOrderContentShip(array $params)
    {
        $order = isset($params['order']) ? $params['order'] : null;
        return $this->displayAdminOrderServicePoint($order);
    }

    /**
     * Display the service point details in the order details (history)
     * to the customer.
     *
     * @param  array $params
     * @return string
     */
    public function hookDisplayOrderDetail(array $params)
    {
        $order = isset($params['order']) ? $params['order'] : null;
        $point = $this->getOrderServicePoint($order);
        if (!$point) {
            return '';
        }

        $this->smarty->assign(
            array(
            'point_details' => $point->getDetails(),
            'prestashop_flavor' => SendcloudTools::getPSFlavor(),
            'txt_service_point_details' => $this->getMessage('service_point_details'),
            )
        );

        return $this->display(__FILE__, 'views/templates/hook/order-details.tpl');
    }

    /**
     * Add the service point details to the delivery slip PDF. Usually a
     * delivery slip is generated when changing the order status to
     * 'Processing in progress'
     *
     * @param array $params
     */
    public function hookDisplayPDFDeliverySlip($params)
    {
        $invoice = isset($params['object']) ? $params['object'] : null;
        if (!$invoice) {
            return '';
        }
        $order = new Order($invoice->id_order);
        $point = SendcloudServicePoint::getFromCart($order->id_cart);
        if (!$point->id || !$point->details) {
            return '';
        }

        $this->smarty->assign(
            array(
            'point_details' => $point->getDetails(),
            'txt_service_point_details' => $this->getMessage('service_point_details'),
            )
        );
        return $this->display(
            __FILE__,
            'views/templates/hook/pdf-delivery-slip.tpl'
        );
    }

    /**
     * Standard settings page. It redirects to the administration screen
     * using `AdminSendcloudController`
     *
     * @return null
     */
    public function getContent()
    {
        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminSendcloud')
        );
    }

    /**
     * Do not apply any special rules to the shipping cost calculations but
     * ensure that service point configuration was done before to make this a
     * valid choice for the end user.
     *
     * @param  Cart  $cart
     * @param  float $shipping_cost
     * @return float The shipping costs. `false` if service points were not enabled.
     */
    public function getOrderShippingCost($cart, $shipping_cost)
    {
        if (!$this->active || !$this->servicePointsAvailable() || !$cart->id_address_delivery) {
            return false;
        }

        return (float)$shipping_cost;
    }

    /**
     * Apply the same rules found in `SendcloudShipping::getOrderShippingCost()`
     *
     * @param  object $params order params
     * @return float
     */
    public function getOrderShippingCostExternal($params)
    {
        return $this->getOrderShippingCost($params, null);
    }

    /**
     * SendCloud connection is shop-specific. If the end user opens the module configuration page
     * with any context other than an explicit shop (e.g: All shops, Shop Group), then we display a
     * message with instructions to switch to a Shop-specific view.
     *
     * @return string the image URL (according to the employee language definition).
     */
    public function getMultishopWarningImage()
    {
        if (Shop::getContextShopID(false) !== null) {
            return '';
        }

        $lang = new Language($this->context->employee->id_lang);
        $image = $this->_path . 'views/img/demo-select-shop.png';
        $file = sprintf('views/img/demo-select-shop-%s.png', $lang->language_code);

        $path = dirname(__FILE__) . '/' . $file;
        if (file_exists($path)) {
            $image = $this->_path . $file;
        }
        return $image;
    }

    /**
     * Helper method to display the details of the service point in the
     * back office. Keep it DRY, since PS 1.6 and PS 1.5 uses different hooks
     * to display service points.
     *
     * @param  Order $order
     * @return string
     */
    private function displayAdminOrderServicePoint(Order $order)
    {
        if (!$order->id) {
            return '';
        }

        $point = $this->getOrderServicePoint($order);
        if (!$point) {
            return '';
        }

        $this->smarty->assign(
            array(
            'prestashop_flavor' => SendcloudTools::getPSFlavor(),
            'point_details' => $point->getDetails(),
            'txt_service_point_details' => $this->getMessage('service_point_details'),
            )
        );
        return $this->display(__FILE__, 'views/templates/hook/admin-order-content-shipping.tpl');
    }

    /**
     * Creates the adminstration tab for the module. It can be found at
     * Administration > SendCloud Shipping after installation.
     *
     * @return bool true if the tab was sucessfully created.
     */
    private function installTab()
    {
        $tab = new Tab();
        $tab->module = $this->name;
        $tab->active = true;
        $tab->class_name = 'AdminSendcloud';
        $tab->name = array();

        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'SendCloud';
        }

        $parent = Tools::version_compare(_PS_VERSION_, '1.7.0.0', '>=') ?
            Tab::getIdFromClassName('AdminParentShipping') : Tab::getIdFromClassName('AdminShipping');
        $tab->id_parent = (int)$parent;
        return $tab->add();
    }

    /**
     * Removes the adminstration tab created by SendcloudShipping::installTab()
     * @return bool
     */
    private function uninstallTab()
    {
        $id_tab = (int)Tab::getIdFromClassName('AdminSendcloud');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }
        // A tab may not be created at all, so there's no reason to fail
        // uninstallation because of that.
        return true;
    }

    /**
     * Create necessary entities in the database in order to make the module
     * to work.
     *
     * @return bool `true` if every entity gets created correctly.
     */
    private function installSQL()
    {
        $queries = include dirname(__FILE__) . '/sql/install.php';
        return $this->performInstallQueries($queries);
    }

    /**
     * Remove every module specific entities from the database.
     *
     * @return bool `true` if every entity is removed correctly.
     */
    private function uninstallSQL()
    {
        $queries = include dirname(__FILE__) . '/sql/uninstall.php';
        return $this->performInstallQueries($queries);
    }

    /**
     * Execute a collection of SQL queries of the install/uninstall procedures.
     *
     * @param  array $queries List of raw SQL queries to execute.
     * @return bool `true` if all queries were executed successfuly.
     */
    private function performInstallQueries(array $queries)
    {
        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Saves the service point information based on `$raw_data`.
     *
     * @param Cart   $cart
     * @param string $raw_data URL-encoded JSON data about the service point.
     */
    private function saveServicePoint(Cart $cart, $raw_data)
    {
        $details = urldecode($raw_data);
        if (!Tools::jsonDecode($details)) {
            return false;
        }

        $point = SendcloudServicePoint::getFromCart($cart->id);
        $point->details = $details;
        if (!$point->save()) {
            return false;
        }
        return $point;
    }

    /**
     * Retrieve the Service Point details related to the specified `$order`.
     *
     * @param Order $order
     */
    private function getOrderServicePoint($order)
    {
        if (!$order || $order->isVirtual()) {
            return;
        }
        $point = SendcloudServicePoint::getFromCart($order->id_cart);
        if (!$point->id || !$point->details) {
            return;
        }

        return $point;
    }
}
