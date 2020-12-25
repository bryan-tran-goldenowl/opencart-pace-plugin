<?php
class ModelExtensionPaymentPaceCheckout extends Model
{
	public function getMethod($address, $total)
	{
		$this->load->language('extension/payment/pace_checkout');

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

		$currencies = array(

			'SGD',
		);

		$data = $this->session->data;
		if ($data['payment_address']['country'] !== "Singapore") {
			$status = false;
		}
		if (!in_array(strtoupper($data['currency']), $currencies)) {
			$status = false;
		}



		if ($status) {
			$method_data = array(
				'code'       => 'pace_checkout',
				'title'      => $this->language->get('text_title'),
				'terms'      => '',
				'sort_order' => $this->config->get('payment_pace_checkout_sort_order')
			);
		}

		return $method_data;
	}
}
