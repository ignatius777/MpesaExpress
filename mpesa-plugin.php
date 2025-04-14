<?php
/**
 * Plugin Name: Mpesa Express gateway
 * Plugin URI:  Plugin URL Link
 * Author:      Ignatius Kipchumba
 * Author URI:  Plugin Author Link
 * Description: Pay via Mpesa Stk push
 * Version:     0.1.0
 * License:     GPL-2.0+
 * License URI: 
 * Text Domain: stk-pay
 */

// Include the STK Push Handler
require_once plugin_dir_path(__FILE__) . 'includes/stk-push-handler.php';

// Include the payment status update logic
//require_once plugin_dir_path(__FILE__) . 'includes/payment-update.php';

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;

add_action('plugins_loaded', 'daraja_payment_init', 11);

function daraja_payment_init() {
    if (class_exists('WC_Payment_Gateway')) {
        class WC_daraja_Payment_Handler extends WC_Payment_Gateway {

            private $consumer_key;
            private $consumer_secret;
            private $shortcode;
            private $passkey;

            public function __construct() {
                $this->id                 = 'mpesa_payment';
                $this->method_title       = 'M-Pesa Payment';
                $this->method_description = 'Pay with M-Pesa via STK Push.';
                $this->has_fields         = true;

                $this->init_form_fields();
                $this->init_settings();

                // Set settings values
                $this->title            = $this->get_option('title');
                $this->description      = $this->get_option('description');
                $this->enabled          = $this->get_option('enabled');
                $this->set_consumer_key($this->get_option('consumer_key'));
                $this->set_consumer_secret($this->get_option('consumer_secret'));
                $this->set_shortcode($this->get_option('shortcode'));
                $this->set_passkey($this->get_option('passkey'));

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
                add_action('woocommerce_checkout_process', [$this, 'validate_fields']);
                add_action('woocommerce_checkout_create_order', [$this, 'save_mpesa_phone']);
            }

            public function init_form_fields() {
                $this->form_fields = [
                    'enabled' => [
                        'title'       => 'Enable/Disable',
                        'label'       => 'Enable M-Pesa Payment',
                        'type'        => 'checkbox',
                        'description' => 'Enable this payment gateway for customers.',
                        'default'     => 'yes',
                    ],
                    'title' => [
                        'title'       => 'Title',
                        'type'        => 'text',
                        'description' => 'This controls the title shown during checkout.',
                        'default'     => 'M-Pesa',
                        'desc_tip'    => true,
                    ],
                    'description' => [
                        'title'       => 'Description',
                        'type'        => 'textarea',
                        'description' => 'This controls the description shown during checkout.',
                        'default'     => 'You will receive an M-Pesa STK push to complete the payment.',
                    ],
                    'consumer_key' => [
                        'title'       => 'Consumer Key',
                        'type'        => 'text',
                        'description' => 'Daraja API Consumer Key.',
                        'default'     => '',
                    ],
                    'consumer_secret' => [
                        'title'       => 'Consumer Secret',
                        'type'        => 'text',
                        'description' => 'Daraja API Consumer Secret.',
                        'default'     => '',
                    ],
                    'shortcode' => [
                        'title'       => 'Paybill or Till Number',
                        'type'        => 'text',
                        'description' => 'Your M-Pesa shortcode (Paybill or Till Number).',
                        'default'     => '',
                    ],
                    'passkey' => [
                        'title'       => 'Lipa na M-Pesa Passkey',
                        'type'        => 'text',
                        'description' => 'Generated from the Daraja portal.',
                        'default'     => '',
                    ],
                ];
            }

            public function get_consumer_key() {
                return $this->consumer_key;
            }

            public function set_consumer_key($consumer_key) {
                $this->consumer_key = $consumer_key;
            }

            public function get_consumer_secret() {
                return $this->consumer_secret;
            }

            public function set_consumer_secret($consumer_secret) {
                $this->consumer_secret = $consumer_secret;
            }

            public function get_shortcode() {
                return $this->shortcode;
            }

            public function set_shortcode($shortcode) {
                $this->shortcode = $shortcode;
            }

            public function get_passkey() {
                return $this->passkey;
            }

            public function set_passkey($passkey) {
                $this->passkey = $passkey;
            }

            public function process_payment($order_id) {
                $order = wc_get_order($order_id);
                
                // Store order ID in session for use on the custom payment page
                WC()->session->set('mpesa_order_id', $order_id);

                $phone = $order->get_meta('_mpesa_phone');
                $amount = (int) $order->get_total();

                // Callback URL for M-Pesa Daraja API
                $callback_url = '';

                // Call the STK Push handler to initiate the payment
                $response = STK_Push_Handler::initiate_stk_push($order_id, $phone, $amount, [
                    'consumer_key'    => $this->get_consumer_key(),
                    'consumer_secret' => $this->get_consumer_secret(),
                    'shortcode'       => $this->get_shortcode(),
                    'passkey'         => $this->get_passkey(),
                    'callback_url'    => $callback_url
                ]);

                // Log the response from the STK Push API
                error_log('STK Push Response: ' . print_r($response, true));

                // Check if the response was successful
if (isset($response['ResponseCode']) && $response['ResponseCode'] == 0) {
    // Save CheckoutRequestID in the order meta
    $order->update_meta_data('_mpesa_checkout_id', $response['CheckoutRequestID']);

    // Save amount in the order meta
    $order->update_meta_data('_mpesa_amount', $amount);

    // Save order ID in the order meta (optional, since it's the post ID, but can be useful for traceability)
    $order->update_meta_data('_mpesa_order_id', $order_id);

    
    $order->save();

   
    wc_add_notice('STK Push sent successfully. Please enter your M-Pesa PIN to complete the payment.', 'success');

    
    return [
        'result' => 'success',
        'redirect' => '' // No redirect
    ];
}
            }

            public function validate_fields() {
                if (empty($_POST['billing_phone'])) {
                    wc_add_notice('Please enter a phone number for M-Pesa payment.', 'error');
                }
            }

            public function save_mpesa_phone($order) {
                if (!empty($_POST['billing_phone'])) {
                    $order->update_meta_data('_mpesa_phone', sanitize_text_field($_POST['billing_phone']));
                }
            }
        }
    }
}

add_filter('woocommerce_payment_gateways', 'register_mpesa_payment_gateway');
function register_mpesa_payment_gateway($gateways) {
    $gateways[] = 'WC_daraja_Payment_Handler';
    return $gateways;
}






