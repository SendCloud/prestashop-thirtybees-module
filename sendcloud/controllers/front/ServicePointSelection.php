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

/**
 * Controller responsible for saving the service point selection in the database
 * after the selection.
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
class SendcloudServicePointSelectionModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        parent::init();

        $cart = $this->context->cart;
        if (!Tools::isSubmit('ajax') || !$cart) {
            SendcloudTools::httpResponseCode(404);
            $this->ajaxDie(false);
        }

        $module = $this->module;
        $details = Tools::getValue('service_point_data');
        if (!$details) {
            SendcloudTools::httpResponseCode(400);
            $this->ajaxDie(Tools::jsonEncode(array(
                'error' => $module->getMessage('no_service_point')
            )));
        }

        $pointData = Tools::jsonDecode($details);
        if (!$pointData) {
            SendcloudTools::httpResponseCode(400);
            $this->ajaxDie(Tools::jsonEncode(array(
                'error' => $module->getMessage('unable_to_parse')
            )));
        }

        $point = SendcloudServicePoint::getFromCart($cart->id);
        $point->details = $details;
        $point->save();

        SendcloudTools::httpResponseCode(201);
        $this->ajaxDie('');
    }
}
