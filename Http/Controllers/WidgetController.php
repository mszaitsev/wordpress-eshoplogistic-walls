<?php

namespace eshoplogistic\WCEshopLogistic\Http\Controllers;

use eshoplogistic\WCEshopLogistic\Api\EshopLogisticApi;
use eshoplogistic\WCEshopLogistic\Contracts\ResponseInterface;
use eshoplogistic\WCEshopLogistic\DB\OptionsRepository;
use eshoplogistic\WCEshopLogistic\Http\Foundation\Controller;
use eshoplogistic\WCEshopLogistic\Http\WpHttpClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WidgetController extends Controller {

	public function process( \WP_REST_Request $request ): ResponseInterface {
		$out    = [];
		$method = $request->get_param( 'method' );

		if ( ! empty( $method ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- REST API endpoint, nonce not required
			$query_data = isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : [];
			unset( $query_data['method'] );
			$cache_key  = md5( $method . json_encode( $query_data ) );
			$cache_data = get_transient( $cache_key );

			if ( ! empty( $cache_data ) ) {
				$out = $cache_data;
			} else {
				$raw = ( $method == 'widget/send' ) ? $request->get_param( 'raw' ) : '';

				if ( $request = $this->ApiQuery( trim( $method ), $query_data, $raw ) ) {
					if ( is_array( $request ) && ! empty( $request ) && isset( $request['http_status'] ) && $request['http_status'] == 200 ) {
						set_transient( $cache_key, $request, HOUR_IN_SECONDS );
					}
					$out = $request;
				}
			}
		}

		return $this->json( $out );
	}


	public function ApiQuery( string $method, array $data = [], string $raw = '' ) {
		$optionsRepository = new OptionsRepository();
		$eshopLogisticApi  = new EshopLogisticApi( new WpHttpClient() );

		$calculation = false;

		$apiKey = $optionsRepository->getOption( 'wc_esl_shipping_api_key' );
		if ( empty( $apiKey ) ) {
			return [];
		}

		$apiUrl = $eshopLogisticApi->getApiUrl();
		if ( empty( $apiUrl ) ) {
			return [];
		}


		$lc = substr( $apiUrl, - 1 );
		if ( $lc != '/' ) {
			$apiUrl .= '/';
		}

		// Direct cURL for widget API communication
		// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init
		// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_setopt
		// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_exec
		// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_close
		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_URL, $apiUrl . $method );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $curl, CURLOPT_POST, 1 );
		if ( preg_match( '/widget/', $method ) ) {
			# заказ из виджета отправляется в raw
			if ( $method == 'widget/send' ) {
				curl_setopt( $curl, CURLOPT_POSTFIELDS, $raw );
				curl_setopt( $curl, CURLOPT_HTTPHEADER, [
					'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36',
					'Content-Type: application/json'
				] );
			} elseif ( $method == 'widget/calculation' ) {
				if ( empty( $data['offers'] ) ) {
					curl_close( $curl );
					return false;
				}
				$encoded = json_decode( stripslashes( $data['offers'] ) );
				if ( $encoded === null && json_last_error() !== JSON_ERROR_NONE ) {
					curl_close( $curl );
					return false;
				}
				$data['offers'] = json_encode( $encoded );
				$data['debug']  = 1;
				$calculation    = true;
				curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
				curl_setopt( $curl, CURLOPT_HTTPHEADER, [
					'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36',
				] );
			} else {
				curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
				curl_setopt( $curl, CURLOPT_HTTPHEADER, [
					'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36',
				] );
			}
		} else {
			# выгрузка заказа в raw
			if ( $method == 'delivery/order' ) {
				$raw = json_encode( array_merge( $data, [ 'key' => $apiKey ] ) );
				curl_setopt( $curl, CURLOPT_POSTFIELDS, $raw );
				curl_setopt( $curl, CURLOPT_HTTPHEADER, [
					'Content-Type: application/json'
				] );
			} else {
				curl_setopt( $curl, CURLOPT_POSTFIELDS, array_merge( $data, [ 'key' => $apiKey ] ) );
			}
		}

		$result = curl_exec( $curl );
		curl_close( $curl );
		// phpcs:enable WordPress.WP.AlternativeFunctions

		if ( $result === false || $result === '' ) {
			return false;
		}

		$result = json_decode( $result, 1 );
		if ( ! is_array( $result ) ) {
			return false;
		}

		if ( is_array( $result ) ) {

				if ( $calculation && isset( $result['debug'] ) ) {
					if(isset($result['data']) && is_array($result['data']) && isset($result['data']['terminal']) && is_array($result['data']['terminal']) && isset($result['data']['terminal']['price']['value']) && !is_int($result['data']['terminal']['price']['value'])){
						$result['data']['terminal']['price']['value'] = (int)$result['data']['terminal']['price']['value'];
					}
					if(isset($result['data']) && is_array($result['data']) && isset($result['data']['door']) && is_array($result['data']['door']) && isset($result['data']['door']['price']['value']) && !is_int($result['data']['door']['price']['value'])){
						$result['data']['door']['price']['value'] = (int)$result['data']['door']['price']['value'];
					}
					if ( isset( $data['key'], $data['to'], $data['service'] ) ) {
						$keyWidget = explode( ':', $data['key'] );
						$cacheJson = array(
							'city' => $data['to'],
							'key'  => $keyWidget[0],
							'service' => $data['service']
						);
						$cache_key = md5( $method . json_encode( $cacheJson ) );
						set_transient( $cache_key, $result, HOUR_IN_SECONDS );
					}
				}

				return $result;
		}

		return false;
	}

}

