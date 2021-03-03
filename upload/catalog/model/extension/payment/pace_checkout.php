<?php
class ModelExtensionPaymentPaceCheckout extends Model
{
	public function getMethod($address, $total)
	{
		$this->load->language('extension/payment/pace_checkout');
		$this->load->model('setting/setting');

		$pace_setting = $this->model_setting_setting->getSetting('payment_pace_checkout');
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int) $this->config->get('payment_pace_checkout_geo_zone_id') . "' AND country_id = '" . (int) $address['country_id'] . "' AND (zone_id = '" . (int) $address['zone_id'] . "' OR zone_id = '0')");

		if ($this->config->get('payment_pp_standard_total') > $total) {
			$status = false;
		} elseif (!$this->config->get('payment_pace_checkout_geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}

		$method_data = array();

		/**
		 * Check availabel country and currency base on Pace Plan
		 * @since 1.0.1
		 */
		$this->load->model('extension/module/pace');
		$isAvailable = $this->model_extension_module_pace->isAvailable( (float) $total );

		if ( ! $isAvailable ) {
			return false;
		}

		if ($status) {
			$method_data = array(
				'code'       => 'pace_checkout',
				'title'      => $pace_setting['payment_pace_checkout_title'],
				'terms'      => '',
				'sort_order' => $this->config->get('payment_pace_checkout_sort_order')
			);
		}

		return $method_data;
	}
}
