{* 2016 SendCloud Global B.V.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to contact@sendcloud.eu so we can send you a copy immediately.
 *
 *  @author    SendCloud Global B.V. <contact@sendcloud.eu>
 *  @copyright 2016 SendCloud Global B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*}
<div class="sendcloudshipping-{$prestashop_flavor|escape:'htmlall':'UTF-8'} row hidden"
     id="sendcloudshipping_service_point_picker"
     data-cart-id="{$cart->id|escape:'htmlall':'UTF-8'}"
     data-carrier-id="{$carrier->id|escape:'htmlall':'UTF-8'}"
     data-to-country="{$to_country|escape:'htmlall':'UTF-8'}"
     data-to-postal-code="{$to_postal_code|escape:'htmlall':'UTF-8'}"
     data-language="{$language|escape:'htmlall':'UTF-8'}"
     data-save-url="{$save_endpoint|escape:'htmlall':'UTF-8'}">
  <div class="col-sm-12">
    <div class="form-group clearfix">
      <a class="btn btn-default button button-small" id="sendcloudshipping_service_point_opener"
        href="#sendcloudshipping_service_point_picker">
        <span>
          {l s='Select Service Point' mod='sendcloud'}
          <i class="icon-chevron-right right"></i>
        </span>
      </a>
      <div class="sendcloudshipping-point-details">
        <input type="hidden" name="sendcloudshipping_service_point" value="{$service_point_details|escape:'htmlall':'UTF-8'}" />
        <span class="description"></span>
      </div>
    </div>
    <p class="hidden alert alert-warning">
     <span class="outdated hidden">{l s='You are using an outdated browser. Please upgrade your browser to select a service point location.' mod='sendcloud'}</span>
     <span class="missing hidden">{l s='You must select a Service Point Delivery location before proceding.' mod='sendcloud'}</span>
     <span class="failure hidden">{l s='Unable to save the service point information. Please, try again.' mod='sendcloud'}</span>
    </p>
  </div>
</div>
