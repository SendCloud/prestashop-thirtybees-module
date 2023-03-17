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
<div
  data-carrier-id="{$carrier->id|escape:'htmlall':'UTF-8'}"
  data-carrier-code="{$carrier_code|escape:'htmlall':'UTF-8'}"
  class="sendcloud-spp sendcloud-spp--{$prestashop_flavor|escape:'htmlall':'UTF-8'} row hidden"
>
  <div class="col-sm-12 sendcloud-spp__selection">
    <div class="sendcloud-spp__selection-trigger">
      <button
        type="button"
        class="sendcloud-spp__pick-button btn btn-default button button-small"
      >
        <span>
          {l s='Select Service Point' mod='sendcloud'}
          {if $prestashop_flavor == 'ps17' || $prestashop_flavor == 'ps80'}
          <i class="material-icons" aria-hidden="true">chevron_right</i>
          {else}
          <i class="icon-chevron-right right"></i>
          {/if}
        </span>
      </button>
    </div>
    <div class="sendcloud-spp__selection-details"></div>
  </div>
</div>
