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
{capture name='sendcloud_shipping_wslink'}
    <a href="{$prestashop_webservice_docs|escape:'htmlall':'UTF-8'}" rel="external" target="_blank">
        {l s='Webservice' mod='sendcloud'}
        <span class="icon icon-mail-forward"></span>
    </a>
{/capture}

{capture name='sendcloud_shipping_wsinfo'}
    {l s='By connecting with SendCloud the %s feature of Prestashop will be activated and the required API Key created.' sprintf='{WEBSERVICE}' mod='sendcloud'}
{/capture}
{assign var='webservice_info' value=$smarty.capture.sendcloud_shipping_wsinfo}

<div id="sendcloud_shipping_container" class="row sendcloud_shipping {$prestashop_flavor|escape:'htmlall':'UTF-8'}">

    <div class="col-lg-12">

        <div class="row sendcloud_shipping_connect">
            {if $multishop_warning}
                <div class="panel">
                    <div class="panel-heading">
                        <i class="ps-icon ps-icon-warning icon icon-warning"></i>
                        {l s='Select a Shop' mod='sendcloud'}
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-lg12">
                                <div class="info alert alert-info">
                                    {l s='SendCloud settings are shop-specific, therefore you must select a shop before you’re able to continue.' mod='sendcloud'}
                                </div>
                                <div class="sendcloud-shop-demo text-center">
                                    <img src="{$multishop_warning|escape:'htmlall':'UTF-8'}" alt="" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            {else}
            <div class="panel">
              <div class="panel-heading">
              <i class="ps-icon ps-icon-broken-link icon icon-chain-broken"></i>
              {if $prestashop_flavor == 'ps15'}
              <img src="../img/t/AdminWebservice.gif">
              {/if}
              {l s='Connect with SendCloud' mod='sendcloud'}
              </div>

              <div class="panel-body">
                <div class="row">
                  <div class="col-lg-9 intro-text">

                      <h4>{l s='Saving time and shipping costs with UPS, DHL, DPD and more' mod='sendcloud'}</h4>

                      <p>
                      {l s='SendCloud is the smart shipping solution for ecommerce. With SendCloud you can easily ship packages with multiple carriers like DHL, DPD, UPS and more. You can easily import all your orders, print shipping labels within one click and send automated store-branded Track and Trace emails to your customers. In addition, you can automatically take care of returns with your personal return portal via SendCloud.' mod='sendcloud'}
                      </p>

                      <div class="info alert alert-info">
                          <ul>
                            <li>{$webservice_info|escape:'htmlall':'UTF-8'|replace:'{WEBSERVICE}':$smarty.capture.sendcloud_shipping_wslink}</li>
                          </ul>
                      </div>

                      <p>
                        <a
                          class="link external"
                          href="{$sendcloud_panel_url|escape:'htmlall':'UTF-8'}"
                          rel="external noopener noreferrer" target="_blank">
                            {l s='Go to SendCloud panel' mod='sendcloud'}
                        </a>

                        <a class="link external pull-right" rel="external noopener noreferer" target="_blank"
                           href="https://addons.prestashop.com/contact-community.php?id_product=24482">{l s='Contact support' mod='sendcloud'}</a>
                      </p>
                  </div>

                  <div class="col-lg-3 api-permissions">
                      {if $is_connected && $connect_settings.key }
                      <h4>{l s='API Key' mod='sendcloud'}</h4>
                      <p><code>{$connect_settings.key|escape:'htmlall':'UTF-8'}</code></p>
                      {/if}
                      <h4>{l s='Required API Resources' mod='sendcloud'}</h4>
                      <ul>
                      {foreach from=$api_resources item=resource}
                          <li><code>{$resource|escape:'htmlall':'UTF-8'}</code></li>
                      {/foreach}
                      </ul>
                  </div>
                </div>
              </div>

                {if $can_connect}
              <div class="panel-footer">
                  <form method="post" enctype="multipart/form-data" id="sendcloud_shipping_connect_form">
                      <fieldset>
                          <div class="form-group">
                              <input type="hidden" class="hidden" id="code" name="new_key" />
                              <button
                                      class="btn btn-default button pull-right sendcloudshipping-connect"
                                      type="submit"
                                      name="connectBtn">
                                  <i class="process-icon-save"></i>
                                  {l s='Connect with SendCloud' mod='sendcloud'}
                              </button>
                          </div>
                      </fieldset>
                  </form>
              </div>
                {/if}

            </div>

            <div class="panel">
              <div class="panel-heading">
                <i class="ps-icon ps-icon-world icon icon-globe"></i>
              {if $prestashop_flavor == 'ps15'}
              <img src="../img/admin/world.gif">
              {/if}
              {l s='Service Point Delivery' mod='sendcloud'}
              </div>

              <div class="panel-body">
                <div class="row">
                  <div class="col-md-12">
                    <h4>{l s='Shipping Packages has never been so easy.' mod='sendcloud'}</h4>
                    <div class="intro-text">
                      <p>
                        {l s='Service Points are places that accept packages to be retrieved later by the customer (e.g. a grocery store near home or work).' mod='sendcloud'}
                        {l s='By enabling Service Points, your customers should be able to select a Service Point delivery location at checkout.' mod='sendcloud'}
                      </p>
                    </div>

                    {if !empty($service_point_warning)}
                    <p class="alert alert-warning">{$service_point_warning|escape:'htmlall':'UTF-8'}</p>
                    {elseif $service_point_carrier}
                    <div class="row">
                      <div class="col-lg-9">
                        <p class="success alert alert-success">
                          {l s='Service Points are enabled and correctly configured.' mod='sendcloud'}
                        </p>
                      </div>
                      <div class="col-lg-3">
                        <dl>
                          {assign var='shipping_zones' value=$service_point_carrier->getZones()}
                          <dt>{l s='Shipping Zones' mod='sendcloud'}</dt>
                          <dd>
                            <ul>
                              {foreach from=$shipping_zones item='zone'}
                              <li class="zone">{$zone.name|escape:'htmlall':'UTF-8'}</li>
                              {/foreach}
                            </ul>
                          </dd>
                        </dl>
                      </div>
                    </div>
                    {/if}
                  </div>
                </div>
              </div>

              {if $is_connected && $service_point_script && $service_point_carrier && !$service_point_carrier->deleted}
              <div class="panel-footer">
                  <a href="{$service_point_carrier_link|escape:'htmlall':'UTF-8'}" class="btn btn-default button pull-right">
                  <i class="process-icon-edit"></i>
                  {l s='Change carrier details.' sprintf=$service_point_carrier->name mod='sendcloud'}
                  </a>
              </div>
              {/if}
            </div>
            {/if}{* current_shop_id *}
        </div>
    </div>
</div>
{if $connect_url}
<script>window.location.href="{$connect_url|escape:'htmlall':'UTF-8'}";</script>
<noscript><meta http-equiv="refresh" content="0;url={$connect_url|escape:'htmlall':'UTF-8'}" /></noscript>
{/if}
