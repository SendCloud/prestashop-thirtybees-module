<?php
/**
 * Service Point to cart relation.
 *
 *  @author    SendCloud Global B.V. <contact@sendcloud.eu>
 *  @copyright 2016 SendCloud Global B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  @category  Shipping
 *  @package   Sendcloud
 *  @link      https://sendcloud.eu
 */

/**
 * We keep track of customers selected service points so we can show it's details
 * on any order-related screen (e.g: Order Details in the front office, backoffice,
 * delivery slips, e-mails, etc.)
 *
 *  @author    SendCloud Global B.V. <contact@sendcloud.eu>
 *  @copyright 2016 SendCloud Global B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  @category  Shipping
 *  @package   Sendcloud
 *  @link      https://sendcloud.eu
 */
class SendcloudServicePoint extends ObjectModel
{
    /**
     * Customer delivery address.
     *
     * @var int
     */
    public $id_address_delivery;

    /**
     * Related customer cart.
     *
     * @var int
     */
    public $id_cart;

    /**
     * Selected Service Point details.
     *
     * @var string
     */
    public $details;

    /**
     * Date added
     */
    public $date_add;

    /**
     * Last updated date.
     */
    public $date_upd;

    /**
     * Standard `ObjectModel` definition. Contains the database fields this
     * entity has.
     *
     * @var array
     */
    public static $definition = array(
        'table' => 'sendcloud_service_points',
        'primary' => 'id_service_point',
        'multilang' => false,
        'fields' => array(
            'id_cart' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'id_address_delivery' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'details' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
        ),
    );


    /**
     * Retrieve a `SendcloudServicePoint` instance related to the `$cart_id`, or
     * a new instance to relate to the given `$cart_id`.
     *
     * @return SendcloudServicePoint
     */
    public static function getFromCart($cart_id)
    {
        $query = 'SELECT id_service_point
        FROM `'._DB_PREFIX_.self::$definition['table'].'`
        WHERE id_cart="'.(int)$cart_id.'"';
        $id = Db::getInstance()->getValue($query);

        $point = new self($id ? $id : null);
        $point->id_cart = (int)$cart_id;

        return $point;
    }

    /**
     * Parse the JSON data with the service point information and return an
     * object.
     *
     * @return stdClass|null returns `null` if there're no details.
     */
    public function getDetails()
    {
        if (!$this->details) {
            return null;
        }
        return Tools::jsonDecode($this->details);
    }
}
