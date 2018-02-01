/*
 * 2016 SendCloud Global B.V.
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
 */
/* global window, gencode */
(function($){
    'use strict';

    if ($ === undefined) {
        return;
    }

    $(document).ready(initialize);

    function initialize() {
        var connectForm = $('#sendcloud_shipping_connect_form');
        connectForm.attr({
            'target': '_blank',
            'rel': 'noopener noreferrer'
        });
        connectForm.on('submit', function () {
            generate_code(32);

            // Avoid double submission + reload screen.
            connectForm.find('button[type=submit]').prop('disabled', true);
            setTimeout(function () {
                window.location.reload(true);
            }, 5000);
        });
    }

    function generate_code(size) {
        if ($.isFunction(gencode)) {
            // Use the built-in function to create a new API Key whenever possible
            gencode(size);
        }

        var key = $('#code').val().replace(/\s\s*/g, '');
        if (key.length === 0) {
            // Polyfill the key generation...
            /* There are no O/0 in the codes in order to avoid confusion */
            var chars = "123456789ABCDEFGHIJKLMNPQRSTUVWXYZ";
            for (var i = 1; i <= size; ++i) {
                key += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            $('#code').val(key);
        }
    }
})(window.jQuery || undefined);
