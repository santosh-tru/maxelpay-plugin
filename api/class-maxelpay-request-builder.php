<?php

namespace MAXELPAY\Api;
use MAXELPAY\Logger\MAXELPAY_Logger as MAXELPAY_Logger;

if ( !defined( 'ABSPATH' ) ) {
    exit();
}

/**
 * This file is used to send information for the API.
 * 
 * @package     api/MAXELPAY_Request_Builder
 */

if ( ! class_exists( 'MAXELPAY_Request_Builder' ) ) {
    final class MAXELPAY_Request_Builder {

         /** 
         * Requested URL for the MaxelPay API.
         */
        const API_URL = 'https://dev-api.maxelpay.com/api/';

        /**
        * @var string
        */
        private $paymentKey;

         /**
        * @var string
        */
         private $version = 'v1';

        /**
         * @param string $paymentKey
         */
        public function __construct( $paymentKey ) {
            $this->paymentKey = $paymentKey;
        }

        /**
         * @param array $data
         */
        public function maxelpay_create( array $data ) {

            return $this->maxelpay_send_request( $this->version.'/payments/sessions', $data );
        }

        /**
         * @param $uri
         * @param array $data
         */
        public function maxelpay_send_request( $uri, array $data = [] ) {

            $url = esc_url(self::API_URL . $uri);

            MAXELPAY_Logger::log_info('API URL is: ' . $url);

            $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            MAXELPAY_Logger::log_info($payload);

            $headers = [
                'Content-Type' => 'application/json',
                'X-API-KEY'    => $this->paymentKey,
            ];

            $response = wp_remote_post($url, [
                'method'      => 'POST',
                'body'        => $payload,
                'headers'     => $headers,
                'timeout'     => 120,
                'redirection' => 5,
                'httpversion' => '1.0',
            ]);

            $response_code = wp_remote_retrieve_response_code($response);

            MAXELPAY_Logger::log_info('API Response Code is: ' . $response_code);

            if (is_wp_error($response)) {
                
                MAXELPAY_Logger::log_error($response->get_error_message());
                throw new MAXELPAY_Request_Builder_Exception(
                        esc_html__('Something went wrong, maybe the server isn`t responding.', 'maxelpay'),
                        esc_html($response_code),
                        esc_url($uri)
                );
            }
            $response_body = wp_remote_retrieve_body($response);

            $json = json_decode($response_body, true);

            if (is_null($json)) {

                MAXELPAY_Logger::log_error('Something went wrong, maybe the server isn’t responding.');

                throw new MAXELPAY_Request_Builder_Exception(
                    esc_html__('Something went wrong, maybe the server isn’t responding.', 'maxelpay'),
                    esc_html($response_code),
                    esc_html($uri)
                );
            }

            if ($response_code !== 200 ) {
                if ($json['success'] == false ){
                    $error_message = $json['message']  ?? 'Something went wrong, maybe the server isn’t responding.';

                    if(!empty($json['errors']) && is_array($json['errors'])){
                        MAXELPAY_Logger::log_error(wp_json_encode($json['errors'], JSON_PRETTY_PRINT));
                    }else{
                        MAXELPAY_Logger::log_error($error_message);
                    }
                    throw new MAXELPAY_Request_Builder_Exception(
                        esc_html($error_message),
                        esc_html($response_code),
                        esc_url($uri)
                    );
                }
            }


            if (isset($json['data']) && !empty($json['data'])) {

                MAXELPAY_Logger::log_info(
                    'API response data: ' . wp_json_encode($json['data'])
                );

                wc_reduce_stock_levels($data['orderId']);
                MAXELPAY_Logger::log_info('Stocks are decreased only for products that have available quantities, and you can check which products are affected using the provided orderId ' . $data['orderId']);
                
                return $json['data'];
            }
        }
        public function maxelpay_supported_currency($currency) {

            $supported_currency = [
                'USD', 'JPY', 'BGN', 'CZK', 'DKK',
                'GBP', 'HUF', 'PLN', 'RON', 'SEK',
                'CHF', 'ISK', 'NOK', 'HRK', 'RUB',
                'TRY', 'AUD', 'BRL', 'CAD', 'CNY',
                'HKD', 'IDR', 'ILS', 'INR', 'KRW',
                'MXN', 'MYR', 'NZD', 'PHP', 'SGD',
                'THB', 'ZAR', 'EUR'
            ];
            
            // Check if the provided currency is in the list
            return in_array(strtoupper($currency), $supported_currency);
        }
    }
}