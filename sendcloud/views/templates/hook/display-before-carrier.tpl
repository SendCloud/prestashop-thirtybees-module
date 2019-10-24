{* 2019 Sendcloud Global B.V.
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
 *  @author    Sendcloud Global B.V. <contact@sendcloud.eu>
 *  @copyright 2019 Sendcloud Global B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*}
<input
  id="sendcloud__meta"
  name="sendcloud_meta"
  type="hidden"
  data-cart-id="{$cart->id|escape:'htmlall':'UTF-8'}"
  data-ps-flavor="{$prestashop_flavor|escape:'htmlall':'UTF-8'}"
  data-to-country="{$to_country|escape:'htmlall':'UTF-8'}"
  data-to-postal-code="{$to_postal_code|escape:'htmlall':'UTF-8'}"
  data-language="{$language|escape:'htmlall':'UTF-8'}"
  data-save-url="{$save_endpoint|escape:'htmlall':'UTF-8'}"
  data-multi-carrier="true"
/>

<input
  id="sendcloud_service_point_details"
  name="sendcloud_service_point_details"
  type="hidden"
  value="{$service_point_details|escape:'htmlall':'UTF-8'}"
/>

<div
  class="sendcloud-spp__warning sendcloud-spp__warning--{$prestashop_flavor|escape:'htmlall':'UTF-8'} alert alert-warning hidden"
>
  <span class="sendcloud-spp__warning-message outdated hidden">{l s='You are using an outdated browser. Please upgrade your browser to select a service point location.' mod='sendcloud'}</span>
  <span class="sendcloud-spp__warning-message missing hidden">{l s='You must select a Service Point Delivery location before proceding.' mod='sendcloud'}</span>
  <span class="sendcloud-spp__warning-message save-failed hidden">{l s='Unable to save the service point information. Please, try again.' mod='sendcloud'}</span>
</div>
