<?php
/**
 * Holds the main connection logic class.
 *
 * PHP version 5
 *
 *  @author    Sendcloud Global B.V. <contact@sendcloud.com>
 *  @copyright 2016 Sendcloud Global B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  @category  Shipping
 *  @package
 *  @link      https://sendcloud.com
 */

/**
 * Manage connection details and state between the panel and the
 * PrestaShop instance.
 *
 *  @author    Sendcloud Global B.V. <contact@sendcloud.com>
 *  @copyright 2016 Sendcloud Sendcloud Global B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  @category  Shipping
 *  @package   Sendcloud
 *  @link      https://sendcloud.com
 */
class SendcloudConnector
{
    const SETTINGS_CONNECT = 'SENDCLOUD_SETTINGS_CONNECT';
    const SETTINGS_CARRIER_PREFIX = 'SENDCLOUD_SPP_CARRIER_';
    const SETTINGS_SERVICE_POINT = 'SENDCLOUD_SPP_SCRIPT';
    const LEGACY_CARRIER_CONFIGURATION = 'SENDCLOUD_SPP_CARRIER';

    /**
     * Keep track of all carriers selected at Sendcloud when activating service points.
     * @since 1.3.0
     */
    const SETTINGS_SELECTED_CARRIERS = 'SENDCLOUD_SPP_SELECTED_CARRIERS';

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
            'es' => 'Recogida en punto de servicio',
            'fr' => 'Livraison en point service',
            'nl' => 'Afhaalpuntevering'
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
     * Default API permissions used by Sendcloud. It __MUST__ use the following
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
        'currencies',
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
     * Sendcloud connection settings.
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
     * Remove all Sendcloud related settings for the current shop.
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

        $removeConfig = Configuration::deleteByName(self::SETTINGS_CONNECT) &&
            $delete_key &&
            Configuration::deleteByName(self::SETTINGS_SERVICE_POINT);

        if ($removeConfig !== true) {
            return false;
        }

        $this->removeAllCarriers();
        return true;
    }

    /**
     * Activate the WebService feature of PrestaShop, creates or updates the required
     * API credentials related to the Sendcloud connection _for the current shop_
     * and redirect to Sendcloud panel to connect with the newly created settings.
     *
     * If an existing API account is already created, it will be updated with a new
     * API key and the connection in the Sendcloud Panel updated accordingly.
     *
     * @return array New or updated connection data, including latest key/id.
     * @throws MissingSendcloudAPIException
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
     * with Sendcloud.
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
        $current_shopID = (int)Shop::getContextShopID(false);

        $connection_made =
            !is_null($settings['id']) &&
            !is_null($settings['key']) &&
            in_array($current_shopID, $settings['shops']) &&
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
    public function getCarrier($code)
    {
        $carrierID = self::getSyncedCarrierID($code);

        /*
        Sometimes PrestaShop (1.5, mostly) behaves badly when updating a carrier:

        If an invalid value is passed to the `Tracking URL` field for example,
        and a user hits the `Finish` button instead of `Next`, a new carrier
        is created but the validation complains about it and a 'rollback' is
        executed, crating yet another carrier; the faulty carrier  is marked as `deleted` and the
        carrier created by the rollback is set as `active`.

        The validation error prevents the `updateCarrier` hook to be executed properly, making the
        current active carrier to be out of sync with our most recent saved carrier ID

        We synchronise the carrier ID here in order to avoid any mismatch
        and prevent the module to stop working.
        */
        $referenceID = Configuration::getGlobalValue(self::getCarrierConfigName($code, true));

        $carrierSQL = "SELECT id_carrier FROM `%s`
            WHERE external_module_name='%s'
            AND active=1 and deleted=0 and is_module=1 AND id_reference=%s";

        $carrierFound = Db::getInstance()->getValue(sprintf(
            $carrierSQL,
            pSQL(_DB_PREFIX_.'carrier'),
            pSQL($this->moduleName),
            (int)$referenceID
        ));

        if ($carrierFound && $carrierFound != $carrierID) {
            throw new SendcloudUnsynchronisedCarrierException($carrierFound, $carrierID);
        }

        if (!$carrierID || !$carrierFound) {
            return;
        }

        return new Carrier($carrierID);
    }

    /**
     * Get or sync the carrier module configuration.
     *
     * @return Carrier
     */
    public function getOrSynchroniseCarrier($code)
    {
        try {
            $carrier = $this->getCarrier($code);
        } catch (SendcloudUnsynchronisedCarrierException $e) {
            $this->synchroniseCarrier($e->carrierFound);
            $carrier = $this->saveCarrier($code, $e->carrierFound);
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
     * Get the permissions required by the Sendcloud module based in the list
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
     * Keep track of the current carrier ID. PrestaShop does soft updates and just the most recent
     * version of the carrier is visible to end consumers/merchants.
     *
     * @see SendcloudConnector::addCarrierLogo()
     * @see SendcloudConnector::getCarrier()
     * @see SendcloudConnector::saveCarrier()
     *
     * @param int $current_id the previous carrier version ID
     * @param Carrier $new_carrier
     */
    public function updateCarrier($current_id, $new_carrier)
    {
        if ($new_carrier->external_module_name === $this->moduleName) {
            $shop = Context::getContext()->shop;
            $selectedCarriers = array_keys($this->getSelectedCarriers($shop));
            $matchingCode = null;

            // There is no way to attach metadata to the `Carrier` itself, so we have to iterate
            // over all known selected carriers to find a matching carrier code.
            foreach ($selectedCarriers as $code) {
                $syncedID = self::getSyncedCarrierID($code, false);
                $syncedReference = self::getSyncedCarrierID($code, true);

                if ((int)$syncedID === (int)$current_id ||
                    (int)$syncedReference === (int)$new_carrier->id_reference
                ) {
                    $matchingCode = $code;
                    break;
                }
            }
            if (is_null($matchingCode)) {
                throw new PrestashopException('Unable to update service point carrier reference.');
            }

            $this->saveCarrier($matchingCode, $new_carrier->id);
        }
    }

    /**
     * Inspect the object and if it's the service point configuration we create the
     * required carriers and update the module settings accordingly. Activating service points happens
     * in two phases:
     *
     * 1. A request is made to tell which carriers the user selected at Sendcloud
     * 2. A request is made t activate service points and inject the service point script
     *
     * After that, further requests _may_ be executed to _update selected carriers_. If a carriers
     * was removed from Sendcloud service point configuration, the corresponding carrier on PrestaShop
     * is removed as well.
     *
     * @param ObjectModel $object
     * @throws SendcloudServicePointException
     * @return bool `true` if service points were activated successfuly.
     */
    public function activateServicePoints(Shop $shop, $object)
    {
        if (!$this->isServicePointConfiguration($object)) {
            return;
        }

        if (self::SETTINGS_SELECTED_CARRIERS === $object->name) {
            // Normalize value: It must be a valid JSON
            try {
                $newCarriers = Tools::jsonDecode($object->value, true);
                if (empty($newCarriers)) {
                    throw new UnexpectedValueException('At least one carrier must be selected');
                }
            } catch (UnexpectedValueException $e) {
                $object->delete();
                throw new SendcloudServicePointException('Invalid carrier configuration: '. $e->getMessage());
            }
            $this->removeOrphanConfiguration($shop, self::SETTINGS_SELECTED_CARRIERS, $object->id);
            $this->updateCarrierSelection($shop);
        }

        if (self::SETTINGS_SERVICE_POINT === $object->name) {
            $this->removeOrphanConfiguration($shop, self::SETTINGS_SERVICE_POINT, $object->id);
            $this->updateCarrierSelection($shop);
        }
        return true;
    }

    /**
     * Return the configuration of all selected carriers in the context of the current shop.
     *
     * @param Shop $shop The shop which we want to retrieve selected carriers for. Defaults to the
     *                   Shop in the current context.
     * @return array
     */
    public function getSelectedCarriers(Shop $shop = null)
    {
        $shop = $shop === null ? Context::getContext()->shop : $shop;
        $configuration = $this->getShopConfiguration($shop, self::SETTINGS_SELECTED_CARRIERS);
        $carriers = Tools::jsonDecode($configuration, true);
        return is_array($carriers) ? $carriers : array();
    }

    /**
     * Update carrier selection and configure carriers accordingly; existing carriers are kept as is,
     * new carriers are created and the rest is deleted.
     *
     * @return void
     */
    public function updateCarrierSelection(Shop $shop)
    {
        $script = $this->getServicePointScript($shop);
        if (is_null($script)) {
            // This method is only valid once service points were correctly configured.
            return;
        }

        $selectedCarriers = $this->getSelectedCarriers($shop);
        $selectedCodes = array_keys($selectedCarriers);
        $registeredCarrierCodes = $this->getAllRegisteredCarrierCodes();

        // Note: it might have difference between *selected* carriers and *registered* carriers.
        // Check what have changed so we can add/remove carriers based on that difference.
        $allKeys = array_unique(array_merge($selectedCodes, $registeredCarrierCodes));
        $preserve = array_unique(array_intersect($selectedCodes, $registeredCarrierCodes));

        foreach ($allKeys as $code) {
            if (in_array($code, $preserve)) {
                continue;
            }
            if (!in_array($code, $selectedCodes)) {
                // We have to remove unused carriers as the consumer cannot use the service point+
                // selection with it.
                $this->removeCarrier($shop, $code);
            } else {
                $name = $selectedCarriers[$code];
                $this->createCarrier($shop, $code, $name);
            }
        }

        // Finally, clear the legacy carrier configuration
        $this->removeLegacyCarrier();
    }

    /**
     * Remove the legacy carrier which was carrier-agnostic as we now support carrier-specific service point carriers.
     */
    private function removeLegacyCarrier()
    {
        $lastKnowLegacyID = Configuration::getGlobalValue(self::LEGACY_CARRIER_CONFIGURATION);
        if (!$lastKnowLegacyID) {
            // Nothing to be done...
            return;
        }
        SendcloudTools::log(sprintf('Sendcloud: removing legacy carrier; last know ID=%d', $lastKnowLegacyID));
        $carrier = new Carrier((int)$lastKnowLegacyID);
        if ($carrier->deleted || !$carrier->active) {
            SendcloudTools::log(sprintf(
                'Sendcloud: last know carrier is deleted or disabled; ID=%d',
                $lastKnowLegacyID
            ));
            // Sometimes the last known ID of a carrier gets desynchronised and the last know ID refers to a previous
            // version (deleted) Sendcloud carrier. Use the reference_id to get the current active one and delete it.
            $activeSQL = sprintf(
                "SELECT `id_carrier` FROM `%s`
                 WHERE external_module_name='%s' AND
                 id_reference=%d AND deleted=0 AND active=1",
                pSQL(_DB_PREFIX_ . 'carrier'),
                pSQL($this->moduleName),
                (int)$carrier->id_reference
            );
            $activeLegacyID = Db::getInstance()->getValue($activeSQL);

            SendcloudTools::log(sprintf('Sendcloud: active legacy carrier found.; ID=%d', $activeLegacyID));

            $carrier = new Carrier((int)$activeLegacyID);
            $carrier->delete();
        } else {
            $carrier->delete();
        }
        Configuration::deleteByName(self::LEGACY_CARRIER_CONFIGURATION);
    }

    /**
     * Each PS carrier has an entry in the configuration table holding its latest synced ID. This will
     * return all (Sendcloud) carrier codes based in the configuration names.
     *
     * @return array List if Sendcloud-specific carrier codes (i.e.: colissimo, dpd, chronopost, mondial_relay)
     */
    private function getAllRegisteredCarrierCodes()
    {
        $allConfigSQL = sprintf(
            "SELECT name FROM `%s` WHERE name LIKE '%s%%'",
            pSQL(_DB_PREFIX_ . 'configuration'),
            pSQL(self::SETTINGS_CARRIER_PREFIX)
        );
        $data = Db::getInstance()->query($allConfigSQL)->fetchAll(PDO::FETCH_COLUMN);
        $codes = array();
        foreach ($data as $entry) {
            $sanitized = str_replace(self::SETTINGS_CARRIER_PREFIX, '', $entry);
            $sanitized = str_replace('_REFERENCE', '', $sanitized);
            // Sendcloud carrier codes are sent in lowercase, but configurations are saved in all-caps.
            $code = Tools::strtolower($sanitized);
            $codes[] = $code;
        }

        $codes = array_unique($codes);
        return $codes;
    }

    /**
     * Given a `Carrier` instance, return the registered Sendcloud carrier code for it
     *
     * @param Carrier $carrier
     * @return string|null The carrier code or NULL in case the configuration is not found
     */
    public function getCarrierCode(Carrier $carrier)
    {
        $sql = sprintf(
            "SELECT name FROM `%s` WHERE name LIKE '%s%%' AND value=%d",
            pSQL(_DB_PREFIX_ . 'configuration'),
            pSQL(self::SETTINGS_CARRIER_PREFIX),
            (int)$carrier->id
        );
        $config = Db::getInstance()->getValue($sql);
        if (!$config) {
            return null;
        }
        $code = str_replace(self::SETTINGS_CARRIER_PREFIX, '', $config);
        return Tools::strtolower($code);
    }

    /**
     * Remove the service point delivery carrier.
     *
     * @param ObjectModel $object
     * @return bool `true` if the carrier was successfuly removed.
     */
    public function deactivateServicePoints($object)
    {
        if (!$this->isServicePointConfiguration($object, array(self::SETTINGS_SERVICE_POINT))) {
            return;
        }

        $this->removeAllCarriers();
        return true;
    }

    /**
     * Save the given carrier ID to the module global settings.
     *
     * @param string $code Sendcloud internal identifier of a carrier (i.e: colissimo, chronopost, etc)
     * @param int $carrier_id
     */
    public function saveCarrier($code, $carrier_id)
    {
        Configuration::updateGlobalValue(self::getCarrierConfigName($code), (int)$carrier_id);
        $carrier = new Carrier($carrier_id);
        Configuration::updateGlobalValue(
            self::getCarrierConfigName($code, true),
            // If there is no reference, we should make this carrier point to itself.
            !empty($carrier->id_reference) ? $carrier->id_reference : (int)$carrier_id
        );
        return $carrier;
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
     * @param Shop $shop Shop instance related to the service point configuration. Defaults to
     *                   the shop available in the current context (i.e. API call is always for
     *                   a specific shop)
     * @return string|null Service Point script located @ Sendcloud. NULL in case it wasn't set
     */
    public function getServicePointScript(Shop $shop = null)
    {
        // Use the current shop in the context if none is passed explicitly
        $shop = $shop ===  null ? Context::getContext()->shop : $shop;
        return $this->getShopConfiguration($shop, self::SETTINGS_SERVICE_POINT);
    }

    /**
     * Check for carrier restriction in relation to Payment Methods.
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
     * Configuration name that holds the latest saved carrier ID so that everytime a carrier is
     * updated, the module knows wich carrier to pick.
     *
     * @param string $code Sendcloud-specific carrier code
     * @param boolean $reference Return the reference configuration
     */
    public static function getCarrierConfigName($code, $reference = false)
    {
        $code = Tools::strtoupper($code);
        $name = self::SETTINGS_CARRIER_PREFIX . $code;
        return $reference === true
            ? $name . '_REFERENCE'
            : $name;
    }

    /**
     * Retrieve the latest ID saved for a specific (Sendcloud) carrier code (i.e. dpd, ups).
     * PrestaShop is designed in such a way that it doesn't allow us to save metadata to the
     * carrier itself and the general guideline (officially documented) is to keep a reference of
     * the latest active carrier around. We also need it's "reference carrier" to be saved to make
     * sure carrier synchronisation is done properly.
     *
     * @see SendcloudConnector::getOrSynchroniseCarrier()
     * @param string $code Sendcloud internal carrier code (i.e.: ups, dpd, colissimo...)
     * @param boolean $reference If TRUE, then it returns the ID of the very first version of a carrier.
     * @return int The lastest saved Carrier ID, based on $code
     */
    private static function getSyncedCarrierID($code, $reference = false)
    {
        return Configuration::getGlobalValue(self::getCarrierConfigName($code, $reference));
    }

    /**
     * Avoid polluting the database with multiple (perhaps incorrect) data related to service points.
     * If an attempt to enable the feature successfuly creates the configuration but Sendcloud refuses
     * to save it we may start to create several service point configurations.
     *
     * PrestaShop is designed in such a way that it allows us to have multiple configurations with
     * the same name, shop, and shop group, hence the deletion of previously added configurations.
     *
     * @param string $name
     * @param int|null $preserve_id configuration ID passed here is not removed.
     */
    private function removeOrphanConfiguration(Shop $shop, $name, $preserve_id)
    {
        $configuration = pSQL(_DB_PREFIX_.'configuration');
        $configName = pSQL($name);
        $shopID = (int)$shop->id;
        $configID = (int)$preserve_id;

        $removeOrphansSQL = "DELETE FROM `{$configuration}`
            WHERE name='{$configName}' AND
            id_shop='{$shopID}' AND
            id_configuration != ${configID}";

        $deletedRows = Db::getInstance()->execute($removeOrphansSQL);
        if (!$deletedRows) {
            throw new SendcloudServicePointException('Unable to remove orphan configurations.');
        }
    }

    /**
     * We got some variations between configuration creation/retrieval between PS 1.5
     * and PS 1.6+ using the Webservice feature.
     *
     * While PS 1.5 doesn't allow us to create configuration using the Webservice
     * _without specifying_ a Shop ID, on PS 1.6 this goes fine. Since the service
     * point configuration *MUST* be shop-specific, we always create the configuration
     * _with_ a Shop ID defined.
     *
     * Unfortunately, with multishop turned off, an attempt to retrieve a
     * shop-specific configuration fails, because the `$shopID` is reset on
     * `ConfigurationCore::get()` and the proper value cannot be retrieved with
     * PrestaShop 1.6+
     *
     * @param Shop $shop Shop to inspect for the desired configuration.
     * @param string $configName Configuration name to look for.
     * @param boolean $cache Save the value to the class instance to avoid multiple DB hits.
     * @return mixed Configuration value.
     */
    private function getShopConfiguration(Shop $shop, $configName, $cache = true)
    {
        $value = null;
        $savedConfigurations = isset($this->savedConfigurations[$shop->id])
            ? $this->savedConfigurations[$shop->id]
            : array();
        $savedValue = null;

        if ($cache === true && isset($savedConfigurations[$configName])) {
            $savedValue = $savedConfigurations[$configName];
        }

        if (!$savedValue) {
            $retrieve_sql = sprintf(
                "SELECT `value` FROM `%s`
                     WHERE name='%s' AND
                     id_shop='%d'
                     ORDER BY `id_configuration` DESC",
                pSQL(_DB_PREFIX_ . 'configuration'),
                pSQL($configName),
                (int)$shop->id
            );
            $value = Db::getInstance()->getValue($retrieve_sql);
            if ($value !== false) {
                // Set the value temporarily to avoid multiple database lookups
                // for this configuration.
                $savedConfigurations[$configName] = $value;
            }

            if ($cache === true) {
                $this->savedConfigurations[$shop->id] = $savedConfigurations;
            }
        } else {
            $value = $savedValue;
        }
        return $value;
    }

    /**
     * Determine if `$object` is an external configuration used to enable service points.
     *
     * @param ObjectModel $object
     * @return bool
     */
    private function isServicePointConfiguration($object, $limit_names = null)
    {
        if (is_null($object) || !($object instanceof Configuration)) {
            return false;
        }
        $configNames = !is_array($limit_names)
            ? array(self::SETTINGS_SERVICE_POINT, self::SETTINGS_SELECTED_CARRIERS)
            : $limit_names;
        return in_array($object->name, $configNames);
    }

    /**
     * Get the tracking URL based in the current Sendcloud panel URL.
     *
     * @deprecated This URL pattern does not work anymore and it MUST be phased out.
     * @return string
     */
    private function getTrackingURL()
    {
        return '';
        // FIXME: Use correct tracking URL here.
        // return SendcloudTools::getPanelURL('/shipping/track/@', null, false);
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
    private function createCarrier(Shop $shop, $code, $name)
    {
        $config = $this->carrierConfig;
        // Retrieve the latest known carrier ID, otherwise create a new entry
        $carrier = $this->getOrSynchroniseCarrier($code);

        if (!$carrier || $carrier->deleted || !$carrier->active) {
            $carrier = new Carrier();
        } elseif ($carrier->active) {
            // Update carrier to shop definition. No extra action is required.
            return $this->updateAssoCarrier($carrier, $shop);
        }

        foreach ($config as $prop => $value) {
            $carrier->$prop = $value;
        }
        // Initial carrier names would be something like: `Service Point Delivery: Colissimo`
        $carrier->name = "{$carrier->name}: $name";
        $carrier->external_module_name = $this->moduleName;

        // FIXME: tracking URLs in the current format will soon be phased out
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

        $this->synchroniseCarrier($carrier->id);
        $this->saveCarrier($code, $carrier->id);

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
     * @return void
     */
    private function addCarrierLogo($carrier_id)
    {
        $logo_src = realpath(dirname(__FILE__).'/../views/img/carrier_logo.jpg');
        $logo_dst = _PS_SHIP_IMG_DIR_.'/'.$carrier_id.'.jpg';
        if (!file_exists($logo_dst)) {
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
     * @param string $code the (Sendcloud) internal carrier code to delete a carrier
     * @param bool $force ignore multi shop checks and delete it anyway
     * @return bool `true` if the carrier is successfuly removed.
     */
    private function removeCarrier(Shop $shop, $code, $force = false)
    {
        $carrier = $this->getOrSynchroniseCarrier($code);

        if (!$carrier || !$carrier->id) {
            // The end user may not activate service points at all, leading to no carrier
            // information to be removed. In any case, cleanup any traces of carrier configuration
            Configuration::deleteByName(self::getCarrierConfigName($code));
            Configuration::deleteByName(self::getCarrierConfigName($code, true));
            $this->removeCarrierConfiguration();
            return true;
        }

        if (!$this->updateDefaultCarrier($carrier)) {
            // Avoid setting it as deleted if no other carrier could be set as
            // default. By doing that we avoid the user having no active carriers
            // in the shop.
            return false;
        }

        if (!$this->isCarrierSelectedByOtherShops($shop, $code) || $force === true) {
            // Service point script configuration is saved once per shop (in a multishop environment)
            // We'll remove the carrier only if it's not being used by any other shop configuration _OR_ if explicitly
            // forced to do so
            $carrier->delete();
            Configuration::deleteByName(self::getCarrierConfigName($code));
            Configuration::deleteByName(self::getCarrierConfigName($code, true));
        }

        $this->removeCarrierConfiguration();
        return true;
    }

    /**
     * If all Sendcloud carriers were removed, then remove service points configuration as well
     * otherwise both back and front office may render inconsistent UI states.
     */
    private function removeCarrierConfiguration()
    {
        $moduleCarriers = Db::getInstance()->getValue(sprintf(
            "SELECT COUNT(1) FROM `%s` WHERE external_module_name = '%s'",
            pSQL(_DB_PREFIX_ . 'carrier'),
            pSQL($this->moduleName)
        ));

        if ((int)$moduleCarriers == 0) {
            Configuration::deleteByName(self::SETTINGS_SERVICE_POINT);
            Configuration::deleteByName(self::SETTINGS_SELECTED_CARRIERS);
        }
    }

    /**
     * Check if a given carrier code is selected on another Shop (Sendcloud integration).
     *
     * @param string $code Sendcloud carrier code (i.e.: dpd, ups, colissimo)
     * @return boolean
     */
    private function isCarrierSelectedByOtherShops(Shop $shop, $code)
    {
        $carrierInUse = false;
        $otherSelections = Db::getInstance()->query(sprintf(
            "SELECT value from `%s` WHERE name='%s' AND id_shop != %d AND id_shop != 0",
            pSQL(_DB_PREFIX_ . 'configuration'),
            pSQL(self::SETTINGS_SELECTED_CARRIERS),
            (int)$shop->id
        ));

        foreach ($otherSelections as $config) {
            $selection = Tools::jsonDecode($config, true);
            $selectedCodes = is_array($selection) ? $selection : array();
            $carrierInUse = in_array($code, array_keys($selectedCodes));
            if ($carrierInUse === true) {
                // If it's used by other shops, then there's no need to keep checking
                break;
            }
        }
        return $carrierInUse;
    }

    /**
     * Remove all module carriers and its their related configuration.
     *
     * @return void
     */
    private function removeAllCarriers()
    {
        $activeCarriersSQL = sprintf(
            "SELECT id_carrier FROM `%s` WHERE external_module_name='%s' AND deleted=0 AND active=1",
            pSQL(_DB_PREFIX_ . 'carrier'),
            $this->moduleName
        );

        $data = Db::getInstance()->query($activeCarriersSQL)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($data as $carrierID) {
            $carrier = new Carrier((int)$carrierID);
            $this->updateDefaultCarrier($carrier);
            $carrier->delete();
        }

        $removeConfigSQL = sprintf(
            "DELETE FROM `%s` WHERE name LIKE '%s'",
            pSQL(_DB_PREFIX_ . 'configuration'),
            self::SETTINGS_CARRIER_PREFIX
        );

        Db::getInstance()->execute($removeConfigSQL);
        Configuration::deleteByName(self::SETTINGS_SELECTED_CARRIERS);

        $this->removeLegacyCarrier();
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
                if ($other['active'] &&
                    !$other['deleted'] &&
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
     * Retrieve the current settings related to Sendcloud connection for the
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
     * Update Sendcloud connection settings for the current shop.
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
