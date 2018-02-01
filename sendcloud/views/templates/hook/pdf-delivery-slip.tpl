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
<br />
<br />
<span class="big bold">{$txt_service_point_details|escape:'htmlall':'UTF-8'}</span>
<br>
<br>
<span class="bold">{$point_details->name|escape:'htmlall':'UTF-8'}</span><br />
{$point_details->street|escape:'htmlall':'UTF-8'} {$point_details->house_number|escape:'htmlall':'UTF-8'}<br />
{$point_details->postal_code|escape:'htmlall':'UTF-8'} {$point_details->city|escape:'htmlall':'UTF-8'}<br />
<br>
