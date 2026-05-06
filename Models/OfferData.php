<?php

namespace eshoplogistic\WCEshopLogistic\Models;

use eshoplogistic\WCEshopLogistic\Contracts\OfferInterface;
use eshoplogistic\WCEshopLogistic\DB\OptionsRepository;

if ( ! defined('ABSPATH') ) {
	exit;
}

class OfferData implements OfferInterface
{
	/**
	 * @var array $data
	 */
	private $data;

	/**
	 * @var WC_Product $product
	 */
	private $product;

	public function __construct(array $data)
	{
		$this->data = $data;

		$this->product = $this->get('data');
	}

	public function getArticle()
	{
		$productId = $this->get('product_id');
		$variationId = $this->get('variation_id');

		if($variationId) return $variationId;

		return $productId;
	}

	public function getName()
	{
		if ($this->hasProduct()) {
			return $this->product->get_title();
		}

		if (isset($this->data['name']) && is_string($this->data['name'])) {
			return $this->data['name'];
		}

		return '';
	}

	public function getQuantity()
	{
		return intval(ceil($this->get('quantity')));
	}

	public function getTotal()
	{
		return $this->get('line_total');
	}

	public function getPriceProductLineTotal()
	{
		$lineTotal = $this->get('line_total');
		$count = $this->getQuantity();

		if($count <= 0)
			return 0;

		return $lineTotal / $count;
	}

	public function getPrice()
	{
		return apply_filters('wc_esl_offer_data_price', $this->hasProduct() ? $this->product->get_price() : 0);
	}

	public function getWeight()
	{
		if (!$this->hasProduct()) {
			return 0;
		}

		// * intval($this->get('quantity'))
		$weight = $this->prepareWeight($this->product->get_weight());
		$result = round(
			floatval(
				$weight
			),
			8
		);
		if($result === 0.0 && $weight)
			$result = 0.1;

		return $result;
	}

	public function getLength()
	{
		if (!$this->hasProduct()) {
			return 0;
		}

		return round(
			floatval(
				$this->prepareDimensionsInEsl($this->product->get_length())
			),
			8
		);
	}

	public function getWidth()
	{
		if (!$this->hasProduct()) {
			return 0;
		}

		return round(
			floatval(
				$this->prepareDimensionsInEsl($this->product->get_width())
			),
			8
		);
	}

	public function getHeight()
	{
		if (!$this->hasProduct()) {
			return 0;
		}

		return round(
			floatval(
				$this->prepareDimensionsInEsl($this->product->get_height())
			),
			8
		);
	}

	public function getDimensions()
	{
		return $this->getLength() . '*' . $this->getWidth() . '*' . $this->getHeight();
	}

	private function prepareWeight($weight)
	{
		$weight = (float)$weight;

		switch (get_option('woocommerce_weight_unit')) {
			case 'kg':
				return $weight;
				break;

			case 'g':
				return $weight / 1000;
				break;

			case 'lbs':
				return $weight / 2.2046;
				break;

			case 'oz':
				return $weight / 35.274;
				break;

			default:
				return $weight;
				break;
		}
	}

	private function prepareDimensions($dimension)
	{
		$dimension = (float)$dimension;

		switch (get_option('woocommerce_dimension_unit')) {
			case 'cm':
				return $dimension;
				break;

			case 'm':
				return $dimension * 100;
				break;

			case 'mm':
				return $dimension / 10;
				break;

			case 'in':
				return $dimension / 0.39370;
				break;

			case 'yd':
				return $dimension / 0.010936;
				break;

			default:
				return $dimension;
				break;
		}
	}

	private function prepareDimensionsInEsl($dimension)
	{
		$dimension = (float)$dimension;
		$optionsRepository = new OptionsRepository();
		$dimensionMeasurement = $optionsRepository->getOption('wc_esl_shipping_dimension_measurement');

		switch ($dimensionMeasurement) {
			case 'cm':
				return $dimension;
				break;

			case 'm':
				return $dimension * 100;
				break;

			case 'mm':
				return $dimension / 10;
				break;

			case 'in':
				return $dimension / 0.39370;
				break;

			case 'yd':
				return $dimension / 0.010936;
				break;

			default:
				return $dimension;
				break;
		}
	}


	private function hasProduct()
	{
		return is_object($this->product) && $this->product instanceof \WC_Product;
	}

	private function get($key)
	{
		if(!isset($this->data[$key])) return null;

		return $this->data[$key];
	}
}
