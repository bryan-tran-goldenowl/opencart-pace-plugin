<?php
class ModelExtensionModuleCron extends Model
{

	public function checkCron()
	{
		$this->load->model('setting/setting');
		$setting = $this->model_setting_setting->getSetting('payment_pace_checkout');
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cron` where  updated_at > UTC_TIMESTAMP()");
		if (!$query->rows) {
			$this->db->query("UPDATE `" . DB_PREFIX . "cron` SET status = 0, updated_at = DATE_ADD(UTC_TIMESTAMP(), interval $setting[payment_pace_checkout_cron] second);");
		} else {
			if (!$query->row['status'] &&  strtotime($query->row['updated_at']) - strtotime('now') < 120) {
				$this->getListOrder();
				$this->db->query("UPDATE `" . DB_PREFIX . "cron` SET status = 1;");
			}
		}
	}

	public function getOrder($order_id)
	{
		$qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . (int) $order_id . "' LIMIT 1");

		if ($qry->num_rows) {
			$order = $qry->row;
			$order['transactions'] = $this->getOrderTransaction($order_id);
			return $order;
		} else {
			return false;
		}
	}

	public function getOrderTransaction($order_id)
	{
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_transaction` WHERE order_id = '" . (int) $order_id . "' ORDER BY id DESC");
		if ($query->num_rows) {
			return [
				'id' => $query->row['id'],
				'order_id' => $query->row['order_id'],
				'transaction_id' => $query->row['transaction_id'],
				json_decode($query->row['data'], true)
			];
		}
		return [];
	}

	private function getListOrder()
	{

		$data = $this->getUser();
		$params = [
			"from" =>  date('yy-m-01'),
			"to"	=> date('yy-m-d')
		];
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $data['api'] . '/v1/checkouts/list');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));

		$headers = array();
		$headers[] = 'Content-Type: text/plain';
		$headers[] = 'Authorization: Basic ' . base64_encode($data['user_name'] . ':' . $data['password']);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$result = curl_exec($ch);
		$data = json_decode($result, true);
		if ($data['items']) {
			$this->compareJob($data['items']);
		}
		$result = curl_exec($ch);
	}



	private function handleUpdateOrderStatus($order_id, $status, $comment = '', $notify = false, $override = false)
	{
		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = '" . (int) $status . "', date_modified = NOW() WHERE order_id = '" . (int) $order_id . "'");

		$this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int) $order_id . "', order_status_id = '" . (int) $status . "', notify = '" . (int) $notify . "', comment = '" . $this->db->escape($comment) . "', date_added = NOW()");
	}

	private function compareJob($data)
	{

		$this->load->model('setting/setting');
		$setting = $this->model_setting_setting->getSetting('payment_pace_checkout');
		$orders = [];

		foreach ($data as $key => $transaction) {
			foreach ($transaction  as $value) {
				$orders[$value['referenceID']] = $value;
			}
		}

		foreach ($orders as $key => $value) {
			$order = $this->getOrder($value['referenceID']);
			if ($order) {

				if ($order['payment_code'] == "pace_checkout") {
					switch ($value['status']) {
						case 'cancelled':
							if ($order['order_status_id'] != 7) {
								$this->handleUpdateOrderStatus($value['referenceID'], (int) $setting['payment_pace_checkout_order_status_transaction_cancelled']);
							}
							break;

						case 'pending_confirmation':
							if ($order['order_status_id'] != 1) {
								$this->handleUpdateOrderStatus($value['referenceID'], 1);
							}
							break;
						case 'approved':
							if ($order['order_status_id'] != 5) {
								$this->handleUpdateOrderStatus($value['referenceID'], 5);
							}
							break;

						case 'expired':
							if ($order['order_status_id']() != 14) {
								$this->handleUpdateOrderStatus($value['referenceID'], $setting['payment_pace_checkout_order_status_transaction_expired']);
							}
							break;
					}
				}
			}
		}
	}




	public function getUser()
	{
		$this->load->model('setting/setting');

		$setting = $this->model_setting_setting->getSetting('payment_pace_checkout');
		$user_name = $setting['payment_pace_checkout_username_sandbox'];
		$password = $setting['payment_pace_checkout_password_sandbox'];
		$api = "https://api-playground.pacenow.co";
		if ($setting['payment_pace_checkout_status'] && !$setting['payment_pace_checkout_status_sandbox']) {
			$api = 'https://api.pacenow.co';
			$user_name = $setting['payment_pace_checkout_username'];
			$password = $setting['payment_pace_checkout_password'];
		}
		return [
			'api' => $api,
			'user_name' => $user_name,
			'password' => $password
		];
	}
}
