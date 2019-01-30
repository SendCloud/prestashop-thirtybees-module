<?php
/**
 * Holds the main connection logic class.
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
 * Manage connection details and state between the SendCloud panel and the
 * PrestaShop instance.
 *
 *  @author    SendCloud Global B.V. <contact@sendcloud.eu>
 *  @copyright 2016 SendCloud Global B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  @category  Shipping
 *  @package   Sendcloud
 *  @link      https://sendcloud.eu
 */
class SendcloudConnector
{
    const SETTINGS_CONNECT = 'SENDCLOUD_SETTINGS_CONNECT';
    const SETTINGS_CARRIER_ID = 'SENDCLOUD_SPP_CARRIER';
    const SETTINGS_SERVICE_POINT = 'SENDCLOUD_SPP_SCRIPT';

    /**
     * Default price range for Service Point Delivery carrier.
     *
     * @var array tuple with min, max
     */
    private $defaultPriceRange = array('0', '10000');

    /**
     * Default weight range for Service Point Delivery carrier (in kg)
     *
     * @var array tuple with min, max
     */
    private $defaultWeightRange = array('0', '50');

    /**
     * Default properties for Service Point Delivery carrier object. It maps
     * exaclty each field in the `CarrierCore` definition.
     *
     * @var array
     */
    private $carrierConfig = array(
        'name' => 'Service Point Delivery',
        'id_tax_rules_group' => 0,
        'active' => true,
        'deleted' => false,
        'shipping_handling' => false,
        'range_behavior' => 0,
        'is_module' => true,
        'delay' => array(
            'be' => 'Afhaalpuntevering',
            'de' => 'Paketshop Zustellung',
            'en' => 'Service Point Delivery',
            'fr' => 'Livraison en point service',
            'nl' => 'Afhaalpuntevering',
        ),
        'shipping_external' => true,
        'external_module_name' => null,
        'need_range' => true,
        'max_width' => 150,
        'max_height' => 150,
        'max_depth' => 150,
        'max_weight' => 0,  // Will be overriden by max($defaultWeightRange)
        'grade' => 4,
    );

    /**
     * Default API permissions used by SendCloud. It __MUST__ use the following
     * format:
     *
     * Example: ```
     * array(
     *  '<permission_name>': array(  // e.g: addresses, customers
     *      '<method_name>': 'on' // e.g: GET, POST, PUT, DELETE
     *  )
     * );
     * ```
     *
     * @var array
     */
    private $apiResources = array(
        'addresses',
        'carriers',
        'configurations',
        'countries',
        'customers',
        'order_details',
        'order_states',
        'orders',
        'products',
        'states'
    );

    /**
     * SendCloud connection settings.
     *
     * @var array connection data such as API key details.
     */
    private $connectSettings;

    /**
     * Current Shop instance to apply changes to.
     * @var Shop $shop
     */
    private $shop;

    /**
     * Reference for the module name.
     *
     * @var string
     */
    private $moduleName;

    /**
     * @var array
     */
    private $savedConfigurations = array();

    /**
     * Set default values in the Connector class.
     *
     * @param string $module_name
     */
    public function __construct($module_name)
    {
        $this->connectSettings = null;
        $this->moduleName = $module_name;
    }

    /**
     * Retrieve current connection settings for the current store.
     *
     * @param bool $force_reload query the database again if `true`
     * @return array connection settings with api key/id
     */
    public function getSettings($force_reload = false)
    {
        if (!$this->connectSettings || $force_reload) {
            $this->loadSettings();
        }
        return $this->connectSettings;
    }

    /**
     * Remove all SendCloud related settings for the current shop.
     *
     * @return bool true if all settings were removed succesfully.
     */
    public function disconnect()
    {
        $settings = $this->getSettings();
        $delete_key = true;
        if ($settings['id']) {
            $key = new WebserviceKey($settings['id']);
            $delete_key = $key->delete();
        }
        return Configuration::deleteByName(self::SETTINGS_CONNECT) && $delete_key &&
            $this->removeCarrier(Context::getContext()->shop) &&
            Configuration::deleteByName(self::SETTINGS_CARRIER_ID) &&
            Configuration::deleteByName(self::SETTINGS_SERVICE_POINT);
    }

    /**
     * Activate the WebService feature of PrestaShop, creates or updates the required
     * API credentials related to the SendCloud connection _for the current shop_
     * and redirect to SendCloud panel to connect with the newly created settings.
     *
     * If an existing API account is already created, it will be updated with a new
     * API key and the connection in the SendCloud Panel updated accordingly.
     *
     * @return array New or updated connection data, including latest key/id.
     * @throws MissingSendCloudAPIException
     */
    public function connect($new_title, $new_key)
    {
        $settings = $this->getSettings(true);

        if (!$settings['key'] && !$new_key) {
            throw new SendcloudMissingAPIKeyException();
        }

        if (preg_match('/cgi/i', Tools::strtolower(php_sapi_name()))) {
            Configuration::updateValue('PS_WEBSERVICE_CGI_HOST', 1);
        }
        Configuration::updateValue('PS_WEBSERVICE', 1);

        $connected_shops = $settings['shops'];
        $connected_shops[] = Shop::getContextShopID(false);

        $localized_date = Tools::displayDate(date('Y-m-d H:i:s'), null, true);
        $title_with_date = sprintf('%s (%s)', $new_title, $localized_date);
        $ws_key = new WebserviceKey($settings['id']);
        $ws_key->description = $title_with_date;
        $ws_key->active = true;
        // Use the existing key *OR* grab the new one.
        $ws_key->key = $settings['key'] ? $settings['key'] : $new_key;
        if ($ws_key->id) {
            $ws_key->update();
        } else {
            $ws_key->add();
        }

        WebserviceKey::setPermissionForAccount($ws_key->id, $this->getAPIPermissions());

        $settings = array(
            'id' => $ws_key->id,
            'key' => $ws_key->key,
            'shops' => array_unique($connected_shops)
        );

        $this->updateSettings($settings);
        Tools::generateHtaccess();
        return $ws_key;
    }

    /**
     * Check requirements that the current shop needs to have before connecting
     * with SendCloud.
     *
     * @return bool
     */
    public function canConnect()
    {
        $shop = Context::getContext()->shop;
        $url = $shop->getBaseURL();

        return !empty($url);
    }

    /**
     * Checks if the Webservice feature of PrestaShop is enabled and we have
     * proper API credentials set for the current shop.
     *
     * @return bool true if we have valid settings from a previous connection.
     */
    public function isConnected()
    {
        $settings = $this->getSettings();
        $current_shop_id = (int)Shop::getContextShopID(false);

        $connection_made =
            !is_null($settings['id']) &&
            !is_null($settings['key']) &&
            in_array($current_shop_id, $settings['shops']) &&
            Configuration::get('PS_WEBSERVICE');

        return $connection_made;
    }

    /**
     * Retrieve the service point delivery carrier related to this shop and
     * referenced by the activation configuration.
     *
     * @param bool $lookup Query the carrier table to get the current active carrier.
     * @return Carrier or `null` if no carrier could be found.
     */
    public function getCarrier($lookup = true)
    {
        $carrier_id = Configuration::get(self::SETTINGS_CARRIER_ID, null, null, null);

        if ($lookup) {
            /*
            Sometimes PrestaShop 1.5 behaves badly when updating a carrier:

            If an invalid value is passed to the `Tracking URL` field for example,
            and a user hits the `Finish` button instead of `Next`, a new carrier
            is created but the validation complains about it and a 'rollback' is
            executed; the newly created carrier is marked as `deleted` and the
            original carrier is set as `active`. The validation error prevents
            the `updateCarrier` hook to be executed properly, making the current
            active carrier to be out of sync with our most recent saved carrier ID

            We synchronise the carrier ID here in order to avoid any mismatch
            and prevent the module to stop working.
            */
            $carrier_sql = "SELECT id_carrier FROM `%s`
                WHERE external_module_name='%s'
                AND active=1 and deleted=0 and is_module=1";

            $carrier_found = Db::getInstance()->getValue(sprintf(
                $carrier_sql,
                pSQL(_DB_PREFIX_.'carrier'),
                pSQL($this->moduleName)
            ));

            if ($carrier_found && $carrier_found != $carrier_id) {
                throw new SendcloudUnsynchronisedCarrierException($carrier_found, $carrier_id);
            }
        }

        if (!$carrier_id) {
            return;
        }

        return new Carrier($carrier_id);
    }

    /**
     * Get or sync the carrier module configuration.
     *
     * @return Carrier
     */
    public function getOrSynchroniseCarrier()
    {
        try {
            $carrier = $this->getCarrier();
        } catch (SendcloudUnsynchronisedCarrierException $e) {
            $this->synchroniseCarrier($e->carrierFound);
            $carrier = $this->saveCarrier($e->carrierFound);
        }
        return $carrier;
    }

    /**
     * Gets API resources
     *
     * @return array
     */
    public function getAPIResources()
    {
        return $this->apiResources;
    }

    /**
     * Get the permissions required by the SendCloud module based in the list
     * of required resources.
     *
     * @return array
     */
    public function getAPIPermissions()
    {
        $methods = array(
            'GET' => 'on',
            'POST' => 'on',
            'PUT' => 'on',
            'DELETE' => 'on',
            'HEAD' => 'on',
        );

        $permissions = array();
        foreach ($this->apiResources as $res) {
            $permissions[$res] = $methods;
        }
        return $permissions;
    }

    /**
     * Keep track of the current carrier ID. PrestaShop does not change the
     * same record, instead a new entry in the carrier table is created.
     *
     * @see SendcloudConnector::addCarrierLogo()
     * @see SendcloudConnector::getCarrier()
     * @see SendcloudConnector::saveCarrier()
     * @param Carrier $new_carrier
     */
    public function updateCarrier($new_carrier)
    {
        if ($new_carrier->external_module_name === $this->moduleName) {
            $this->saveCarrier($new_carrier->id);
        }
    }

    /**
     * Inspect the object and if it's the service point configuration we create the
     * required carrier and update the module settings accordingly.
     *
     * @param ObjectModel $object
     * @throws SendcloudServicePointException
     * @return bool `true` if service points were activated successfuly.
     */
    public function activateServicePoints(Shop $shop, $object)
    {
        if (!$this->isServicePointsConfig($object)) {
            return;
        }

        // Avoid polluting the database with multiple (perhaps incorrect)
        // data related to service points.
        // If an attempt to enable the feature successfuly creates the configuration
        // but SendCloud refuses to save it we may start to create several service
        // point configurations. PrestaShop design allows us to have multiple
        // configurations with the same name, shop, and shop group, hence the deletion
        // of previously added scripts.
        $configuration = pSQL(_DB_PREFIX_.'configuration');
        $config_name = pSQL(self::SETTINGS_SERVICE_POINT);
        $config_id = (int)$object->id;
        $shop_id = (int)$shop->id;

        $remove_orphans = "DELETE FROM `{$configuration}`
        WHERE name='{$config_name}' AND id_configuration != ${config_id}
        AND id_shop='{$shop_id}'";

        if (!Db::getInstance()->execute($remove_orphans)) {
            throw new SendcloudServicePointException(
                'Unable to remove orphan configurations.'
            );
        }
        return $this->createCarrier($shop);
    }

    /**
     * Remove the service point delivery carrier.
     *
     * @param ObjectModel $object
     * @return bool `true` if the carrier was successfuly removed.
     */
    public function deactivateServicePoints(Shop $shop, $object)
    {
        if (!$this->isServicePointsConfig($object)) {
            return;
        }

        return $this->removeCarrier($shop);
    }

    /**
     * Save the given carrier ID to the module global settings.
     *
     * @param int $carrier_id
     */
    public function saveCarrier($carrier_id)
    {
        Configuration::updateValue(
            self::SETTINGS_CARRIER_ID,
            (int)$carrier_id,
            false, // $html
            0, // $id_shop_group,
            0 // $id_shop
        );
        return new Carrier($carrier_id);
    }

    /**
     * Apply all the synchronisation steps (if required) for the specified `$carrier`.
     *
     * @see SendcloudConnector::getCarrier()
     * @param int $carrier_id
     */
    public function synchroniseCarrier($carrier_id)
    {
        $carrier = new Carrier($carrier_id);

        $this->addCarrierGroups($carrier);
        $this->addCarrierLogo($carrier->id);
        $this->addCarrierRanges($carrier);
        $this->addCarrierRestrictions($carrier);
    }

    /**
     * Retrieve the Service Point script configuration for the current active Shop.
     *
     * @return string Service Point script located @ SendCloud
     */
    public function getServicePointScript()
    {
        $shop = Context::getContext()->shop;
        return $this->getShopConfiguration($shop, self::SETTINGS_SERVICE_POINT);
    }

    /**
     * Check for carrier restriction with Payment Methods.
     *
     * @since 1.1.0
     * @param Carrier $carrier The carrier to check for.
     * @param Shop $shop The Shop to check for a payment method relation.
     * @return bool `true` in case of no payment method associated with the carrier.
     */
    public function isRestricted(Carrier $carrier, Shop $shop)
    {
        if (Tools::version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
            // Feature introduced as part of 1.7.x
            return false;
        }

        $check = sprintf(
            'SELECT COUNT(1) FROM `%s` WHERE id_shop = %d AND (id_reference = %d OR id_reference = %d)',
            pSQL(_DB_PREFIX_ . 'module_carrier'),
            (int)$shop->id,
            // Lookup using both carrier ID and Reference:
            // http://forge.prestashop.com/browse/BOOM-3071
            (int)$carrier->id,
            (int)$carrier->id_reference
        );

        $exists = (bool)Db::getInstance()->getValue($check);

        // If there are no relation to payments we consider it as restricted.
        return !$exists;
    }

    /**
     * We got some variations between configuration creation/retrieval between PS 1.5
     * and PS 1.6+ using the Webservice feature.
     *
     * While PS 1.5 doesn't allow us to create configuration using the Webservice
     * _without specifying_ a Shop ID, on PS 1.6 this goes fine. Since the service
     * point configuration *MUST* be shop-specific, we always crete the configuration
     * _with_ a Shop ID defined.
     *
     * Unfortunately, with multishop turned off, an attempt to retrieve a
     * shop-specific configuration fails, because the `$shop_id` is reset on
     * `ConfigurationCore::get()` and the proper value cannot be retrieved with
     * PrestaShop 1.6+
     *
     * @param Shop $shop Shop to inspect for the desired configuration.
     * @param string $config_name Configuration name to look for.
     * @return mixed Configuration value.
     */
    private function getShopConfiguration(Shop $shop, $config_name)
    {
        $value = null;
        $savedConfigurations = isset($this->savedConfigurations[$shop->id]) ?
            $this->savedConfigurations[$shop->id] : array();
        $savedValue = isset($savedConfigurations[$config_name]) ?
            $savedConfigurations[$config_name] : null;


        if (!$savedValue) {
            $retrieve_sql = sprintf(
                "SELECT `value` FROM `%s` WHERE name='%s' and id_shop='%d' ORDER BY `id_configuration` DESC",
                pSQL(_DB_PREFIX_ . 'configuration'),
                pSQL($config_name),
                (int)$shop->id
            );
            $value = Db::getInstance()->getValue($retrieve_sql);
            if ($value !== false) {
                // Set the value temporarily to avoid multiple database lookups
                // for this configuration.
                $savedConfigurations[$config_name] = $value;
            }

            $this->savedConfigurations[$shop->id] = $savedConfigurations;
        } else {
            $value = $savedValue;
        }
        return $value;
    }

    /**
     * Determine if `$object` is a service point configuration.
     *
     * @param ObjectModel $object
     * @return bool
     */
    private function isServicePointsConfig($object)
    {
        return !is_null($object) &&
            $object instanceof Configuration &&
            self::SETTINGS_SERVICE_POINT === $object->name;
    }

    /**
     * Get the tracking URL based in the current SendCloud panel URL.
     *
     * @return string
     */
    private function getTrackingURL()
    {
        return SendcloudTools::getPanelURL('/shipping/track/@', null, false);
    }


    /**
     * Update carrier relation to the shop, if applicable.
     *
     * @param Carrier $carrier
     * @param Shop $shop
     * @return bool `false` in case of failure to insert the new data.
     */
    private function updateAssoCarrier(Carrier $carrier, Shop $shop)
    {
        $shop_exists = (int)Db::getInstance(false)->getValue(sprintf(
            'SELECT COUNT(1) FROM `'._DB_PREFIX_.'carrier_shop`'.
            'WHERE id_carrier=%d and id_shop=%d',
            (int)$carrier->id,
            (int)$shop->id
        ));
        $relation_sql =  sprintf(
            'INSERT INTO `'._DB_PREFIX_.'carrier_shop` (id_carrier, id_shop) VALUES (%d, %d)',
            (int)$carrier->id,
            (int)$shop->id
        );

        if (!$shop_exists) {
            return Db::getInstance()->execute($relation_sql);
        }

        return true;
    }

    /**
     * Create the Service Point Delivery carrier with some defaults.
     * If carrier is already created it's just updated to be activated and
     * `deleted` set to `false`.
     *
     * @return bool `true` if carrier is created/updated
     */
    private function createCarrier(Shop $shop)
    {
        $config = $this->carrierConfig;
        // Retrieve the latest known carrier ID, otherwise create a new entry
        $carrier = $this->getOrSynchroniseCarrier();

        if (!$carrier || $carrier->deleted || !$carrier->active) {
            $carrier = new Carrier();
        } elseif ($carrier->active) {
            // Update carrier to shop definition. No extra action is required.
            return $this->updateAssoCarrier($carrier, $shop);
        }

        foreach ($config as $prop => $value) {
            $carrier->$prop = $value;
        }

        $carrier->external_module_name = $this->moduleName;
        $carrier->url = $this->getTrackingURL();

        $max_weight = $this->defaultWeightRange[1];
        $carrier->max_weight = $max_weight;

        $languages = Language::getLanguages(true);
        foreach ($languages as $language) {
            $iso_code = $language['iso_code'];
            $delay = $config['delay']['en'];
            if (isset($config['delay'][$iso_code])) {
                $delay = $config['delay'][$iso_code];
            }
            $carrier->delay[$language['id_lang']] = $delay;
        }

        if (!$carrier->add()) {
            return false;
        }

        $this->saveCarrier($carrier->id);
        $this->synchroniseCarrier($carrier->id);

        return true;
    }

    /**
     * Add relation between `Group` and carrier.
     *
     * @param Carrier $carrier
     */
    private function addCarrierGroups(Carrier $carrier)
    {
        $db = Db::getInstance();
        $groups = $carrier->getGroups();
        if (!$groups || (!is_array($groups) || !count($groups))) {
            // Fallback to all available groups.
            $groups = Group::getGroups(true);
        }

        $tbl_carrier_group = _DB_PREFIX_ . 'carrier_group';
        $insert_sql = 'INSERT INTO `%1$s` VALUES (%2$d, %3$d)';
        $exists_sql = 'SELECT count(1) FROM `%1$s` WHERE id_group = %2$d and id_carrier = %3$d';
        foreach ($groups as $group) {
            $exists_query = sprintf(
                $exists_sql,
                pSQL($tbl_carrier_group),
                (int)$group['id_group'],
                (int)$carrier->id
            );
            if ((int)$db->getValue($exists_query) > 0) {
                // Skip if group already exists for carrier.
                continue;
            }
            $db->execute(sprintf(
                $insert_sql,
                pSQL($tbl_carrier_group),
                (int)$carrier->id,
                (int)$group['id_group']
            ));
        }
    }

    /**
     * Create default weight and price ranges and associate them with the
     * `$carrier`.
     *
     * @param Carrier $carrier
     */
    private function addCarrierRanges(Carrier $carrier)
    {
        list($min_price, $max_price) = $this->defaultPriceRange;
        list($min_weight, $max_weight) = $this->defaultWeightRange;

        if (!RangePrice::rangeExist($carrier->id, $min_price, $max_price) &&
            !RangePrice::isOverlapping($carrier->id, $min_price, $max_price)
        ) {
            $range_price = new RangePrice();
            $range_price->id_carrier = $carrier->id;
            $range_price->delimiter1 = $min_price;
            $range_price->delimiter2 = $max_price;
            $range_price->add();
        }

        if (!RangeWeight::rangeExist($carrier->id, $min_weight, $max_weight) &&
            !RangeWeight::isOverlapping($carrier->id, $min_weight, $max_weight)) {
            $range_weight = new RangeWeight();
            $range_weight->id_carrier = $carrier->id;
            $range_weight->delimiter1 = $min_weight;
            $range_weight->delimiter2 = $max_weight;
            $range_weight->add();
        }
    }

    /**
     * Copy our logo to standard PS carrier logo directory.
     *
     * @param Carrier $carrier
     * @return bool `true` if copying was successful
     */
    private function addCarrierLogo($carrier_id)
    {
        $logo_src = realpath(dirname(__FILE__).'/../views/img/carrier_logo.jpg');
        $logo_dst = _PS_SHIP_IMG_DIR_.'/'.$carrier_id.'.jpg';
        if (!file_exists(_PS_SHIP_IMG_DIR_.'/'.$carrier_id.'.jpg')) {
            copy($logo_src, $logo_dst);
        }
    }

    /**
     * As of PS 1.7 it's possible to change which payment modules are available
     * per carrier.
     *
     * http://forge.prestashop.com/browse/BOOM-3070
     * When adding a carrier in the webservice context, the payment
     * relations are not added, so we enable them for all installed payment
     * modules.
     *
     * @since 1.1.0
     * @param Carrier $carrier the carrier to relate payment modules to.
     */
    private function addCarrierRestrictions(Carrier $carrier)
    {
        $shop = Context::getContext()->shop;
        if (// Only 1.7+
            Tools::version_compare(_PS_VERSION_, '1.7.0.0', '>=') &&
            // We have a shop context.
            $shop !== null
        ) {
            if ($this->isRestricted($carrier, $shop)) {
                $modules = PaymentModule::getInstalledPaymentModules();
                $values = array_map(function ($module) use ($shop, $carrier) {
                    // a `Carrier` object may not contain the reference immediatelly after
                    // calling `Carrier::add()`:
                    // http://forge.prestashop.com/browse/BOOM-3071
                    $reference = is_null($carrier->id_reference) ? $carrier->id : $carrier->id_reference;
                    return sprintf(
                        '(%d, %d, %d)',
                        (int)$module['id_module'],
                        (int)$shop->id,
                        (int)$reference
                    );
                }, $modules);

                $insert = sprintf(
                    'INSERT INTO `%s` (id_module, id_shop, id_reference) VALUES %s',
                    pSQL(_DB_PREFIX_ . 'module_carrier'),
                    join(', ', $values)
                );

                Db::getInstance()->execute($insert);
            }
        }
    }

    /**
     * Remove the service point delivery carrier. Additional cleanup on the
     * service point configuration is also executed.
     *
     * @param Shop $shop
     * @return bool `true` if the carrier is successfuly removed.
     */
    private function removeCarrier(Shop $shop)
    {
        $carrier = $this->getOrSynchroniseCarrier();

        if (!$carrier || !$carrier->id) {
            // The end user may not activate service points at all, leading
            // to no carrier information to be removed.
            Configuration::deleteByName(self::SETTINGS_CARRIER_ID);
            return true;
        }

        if (!$this->updateDefaultCarrier($carrier)) {
            // Avoid setting it as deleted if no other carrier could be set as
            // default. By doing that we avoid the user having no active carriers
            // in the shop.
            return false;
        }

        $remaining_service_points = Db::getInstance()->getValue(sprintf(
            "SELECT COUNT(1) FROM `%s` WHERE name='%s' AND id_shop != %d AND id_shop != 0",
            pSQL(_DB_PREFIX_ . 'configuration'),
            pSQL(self::SETTINGS_SERVICE_POINT),
            (int)$shop->id
        ));
        // Keep the carrier, since it's being used by other shops.
        if (!$remaining_service_points) {
            // only delete if there're no remaining service point settings.
            $removed = $carrier->delete();
            if ($removed) {
                Configuration::deleteByName(self::SETTINGS_CARRIER_ID);
                Configuration::deleteByName(self::SETTINGS_SERVICE_POINT);
            }
            return $removed;
        }
        return true;
    }

    /**
     * Change the default web shop carrier to something else other than service
     * point delivery, when applicable.
     *
     * @param Carrier $carrier The service point delivery carrier.
     * @return bool FALSE if no other carrier could be set as default.
     */
    private function updateDefaultCarrier(Carrier $carrier)
    {
        if (Configuration::get('PS_CARRIER_DEFAULT') == (int) $carrier->id) {
            $carriers = Carrier::getCarriers($this->context->language->id);
            foreach ($carriers as $other) {
                if ($other['active'] && !$other['deleted'] &&
                    $other['id_carrier'] != $carrier->id
                ) {
                    Configuration::updateValue('PS_CARRIER_DEFAULT', $other['id_carrier']);
                    return true;
                }
            }
            return false;
        }
        return true;
    }

    /**
     * Retrieve the current settings related to SendCloud connection for the
     * current shop from the database.
     *
     * @return null
     */
    private function loadSettings()
    {
        $empty = array('id' => null, 'key' => null, 'shops' => array());
        $settings = Configuration::getGlobalValue(self::SETTINGS_CONNECT);

        if (!$settings) {
            return $empty;
        }

        $settings = Tools::jsonDecode($settings, true);
        if (empty($settings) ||
            !isset($settings['id']) || is_null($settings['id']) ||
            !isset($settings['key']) || is_null($settings['key'])
        ) {
            return $empty;
        }

        if (!WebserviceKey::keyExists($settings['key'])) {
            Configuration::deleteByName(self::SETTINGS_CONNECT);
            return $empty;
        }

        if (!isset($settings['shops'])) {
            $settings['shops'] = array();
        }

        $this->connectSettings = $settings;
    }

    /**
     * Update SendCloud connection settings for the current shop.
     *
     * @param array $settings New data to be saved, including id/key
     * @return bool true if the settings were succesfully saved.
     */
    private function updateSettings(array $settings)
    {
        $saved = Configuration::updateGlobalValue(self::SETTINGS_CONNECT, Tools::jsonEncode($settings));

        if (!$saved) {
            throw new PrestaShopException(
                $this->l(
                    'Unable to update the connection settings.',
                    $this->module->name
                )
            );
        }

        $this->connectSettings = $settings;
        return true;
    }
}
