<?php

/*
Plugin Name: Payment Gateway for Sellery/Kanoo on WooCommerce
Plugin URI: https://sllry.com/sellery-payment-gateway
Description: Easily connect your WooCommerce store with Sellery and accept Kanoo payments
Version: 1.3
Author: Shane D Rosenthal & Justin Cancino
Author URI: https://sllry.com
License:     GPL2

Payment Gateway for Sellery/Kanoo on WooCommerce is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Payment Gateway for Sellery/Kanoo on WooCommerce is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Payment Gateway for Sellery/Kanoo on WooCommerce.
*/

add_filter('woocommerce_payment_gateways', 'sellery_add_gateway_class');
add_action('plugins_loaded', 'sellery_init_gateway_class');

function sellery_add_gateway_class($gateways)
{
    $gateways[] = 'Sellry_Payment_Gateway'; // your class name is here
    return $gateways;
}

function sellery_init_gateway_class()
{
    class Sellry_Payment_Gateway extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id = 'sellery';
            $this->icon = 'https://my-sellery.s3.amazonaws.com/kanoo.png';
            $this->has_fields = false;
            $this->method_title = 'Sellery Gateway';
            $this->method_description = 'Pay with Sellery';
            $this->supports = array(
                'products'
            );
            $this->sellery_init_form_fields();
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->api_key = $this->get_option('api_key');
            $this->sellery = 'https://sllry.com/api/wp-order/';
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_sellery_complete', array($this, 'sellery_webhook'));
        }

        public function sellery_init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'label' => 'Enable Sellery Gateway',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'Pay with Sellery/Kanoo',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Pay with Sellery/Kanoo.',
                ),
                'api_key' => array(
                    'title' => 'API Key',
                    'type' => 'text'
                ),
            );
        }

        public function process_payment($order_id)
        {
            global $woocommerce;

            $order = wc_get_order($order_id);
            $args = array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(),
                'body' => array(
                    'order' => json_decode($order),
                    'url' => get_site_url()
                ),
            );
            $response = wp_remote_post(($this->sellery) . sanitize_text_field($this->api_key), $args);
            if (!is_wp_error($response)) {
                $data = json_decode($response['body'])->data;
                if ($data->response === 'SUCCESS') {
                    $order->add_order_note('Order being processed by Sellery/Kanoo.', true);
                    $woocommerce->cart->empty_cart();
                    return array(
                        'result' => 'success',
                        'redirect' => $data->kanoo_url
                    );
                } else {
                    wc_add_notice('Connection issue', 'error');
                    return;
                }
            } else {
                wc_add_notice('Connection error.', 'error');
                return;
            }
        }

        public function sellery_webhook()
        {
            $order = wc_get_order(sanitize_text_field($_GET['id']));
            $order->payment_complete();
            $order->reduce_order_stock();
            $order->add_order_note( 'Order paid via Sellery/Kanoo. Transaction ID: ' . sanitize_text_field($_GET['transaction_id']), true);
        }
    }
}
