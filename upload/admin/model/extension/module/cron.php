<?php
const PENDING_STATUS = 1;
const CANCELED_STATUS = 7;
const COMPLETE_STATUS = 5;
const FAILED_STATUS = 10;
class ModelExtensionModuleCron extends Model
{

	public function checkCron()
	{
		$this->load->model('setting/setting');
		$setting = $this->model_setting_setting->getSetting('payment_pace_checkout');
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cron` where  updated_at > UTC_TIMESTAMP() and cron_id = 1");
		if (!$query->rows) {
			$this->db->query("UPDATE `" . DB_PREFIX . "cron` SET status = 0, updated_at = DATE_ADD(UTC_TIMESTAMP(), interval $setting[payment_pace_checkout_cron] second) where  cron_id = 1;");
			$this->getListOrder();
		} else {
			if (!$query->row['status'] &&  strtotime($query->row['updated_at']) - strtotime('now') < 120) {
				$this->getListOrder();
				$this->db->query("UPDATE `" . DB_PREFIX . "cron` SET status = 1 where  cron_id = 1;");
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

	private function callPaceApi($url, $params = [])
	{

		$data = $this->getUser();
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $data['api'] . $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));

		$headers = array();
		$headers[] = 'Content-Type: text/plain';
		$headers[] = 'Authorization: Basic ' . base64_encode($data['user_name'] . ':' . $data['password']);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$result = curl_exec($ch);
		$err = curl_error($ch);

		if ($err) {
			error_log('Error Response: ' . print_r($err, true) . PHP_EOL . PHP_EOL . 'Failed request: ' . print_r(
				array(
					'params'     => json_encode($params),
					'request' => $data['api'] . $url,
				),
				true
			));
			throw new Exception('There was a problem connecting to the Pace API');
		}
		$data = json_decode($result, true);

		return $data;
	}

	private function getListOrder()
	{
		$params = [
			"from" =>  date('Y-m-d', strtotime("-1 weeks")),
			"to"    => date('Y-m-d')
		];

		$data = $this->callPaceApi('/v1/checkouts/list', $params);


		if ($data['items']) {
			$this->compareJob($data['items']);
		}
	}

	private function cancelApi($transaction_id)
	{

		$this->callPaceApi('/v1/checkouts/' . $transaction_id . '/cancel');
	}



	private function handleUpdateOrderStatus($order_id, $status, $comment = '', $notify = false, $override = false)
	{
		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = '" . (int) $status . "', date_modified = NOW() WHERE order_id = '" . (int) $order_id . "'");

		$this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int) $order_id . "', order_status_id = '" . (int) $status . "', notify = '" . (int) $notify . "', comment = '" . $this->db->escape($comment) . "', date_added = NOW()");
	}


	private function check_order_manually_update($order, $pace_status = null, $transaction_id = 0)
	{

		$this->load->model('setting/setting');
		$setting = $this->model_setting_setting->getSetting('payment_pace_checkout');

		if ($pace_status == "pending_confirmation" && ($order['order_status_id'] ==  CANCELED_STATUS || $order['order_status_id'] == FAILED_STATUS)) {

			$this->cancelApi($transaction_id);
			return false;
		}

		if ($order['order_status_id'] ==  $setting['payment_pace_checkout_order_status_transaction_pending']) {
			return true;
		}



		if ($order['order_status_id']  != CANCELED_STATUS && $order['order_status_id']  != FAILED_STATUS) {
			return false;
		}

		$last_status = $this->get_last_order_status($order['order_id']);
		return isset($last_status['change_by']) && $last_status['change_by'] == "system";
	}

	private function get_last_order_status($order_id)
	{

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_status_history` WHERE order_id = '" . (int) $order_id . "' ORDER BY id DESC LIMIT 1");
		if ($query->num_rows) {
			if (isset($query->row['change_by'])) {
				return [
					'change_by' => $query->row['change_by'],

				];
			}
		}
		return [];
	}

	private function compareJob($data)
	{

		$this->load->model('setting/setting');
		$setting = $this->model_setting_setting->getSetting('payment_pace_checkout');
		$orders = [];

		foreach ($data as $key => $transaction) {
			usort($transaction, function ($a, $b) {
				return filter_var($a->transactionID, FILTER_SANITIZE_NUMBER_INT)  -  filter_var($b->transactionID, FILTER_SANITIZE_NUMBER_INT) > 0;
			});

			foreach ($transaction  as $value) {
				$orders[$value['referenceID']] = $value;
			}
		}


		foreach ($orders as $key => $pace_transaction) {
			$order = $this->getOrder($pace_transaction['referenceID']);

			if ($order) {
				if ($order['payment_code'] == "pace_checkout") {
					if ($this->check_order_manually_update($order, $pace_transaction['status'], $pace_transaction['transactionID'])) {
						switch ($pace_transaction['status']) {
							case 'cancelled':
								if ($order['order_status_id'] != 7) {
									$this->handleUpdateOrderStatus($pace_transaction['referenceID'], (int) $setting['payment_pace_checkout_order_status_transaction_cancelled']);
								}
								break;

							case 'pending_confirmation':
								if ($order['order_status_id'] != 1) {
									$this->handleUpdateOrderStatus($pace_transaction['referenceID'], (int) $setting['payment_pace_checkout_order_status_transaction_pending']);
								}
								break;
							case 'approved':
								if ($order['order_status_id'] != 5) {
									$this->handleUpdateOrderStatus($pace_transaction['referenceID'], (int) $setting['payment_pace_checkout_order_status_transaction_approved']);
								}
								break;

							case 'expired':
								if ($order['order_status_id']() != 14) {
									$this->handleUpdateOrderStatus($pace_transaction['referenceID'], (int) $setting['payment_pace_checkout_order_status_transaction_expired']);
								}
								break;
						}
					}
				}
			}
		}
	}

	public function checkCronPlans()
	{

		$this->checkConfigExist();
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cron` where  updated_at > UTC_TIMESTAMP() and cron_id = 2;");
		if (!$query->rows) {
			$this->db->query("UPDATE `" . DB_PREFIX . "cron` SET status = 0, updated_at = DATE_ADD(UTC_TIMESTAMP(), interval 3600 second) where cron_id = 2;");
			$this->handleStorePaymentplan();
		} else {
			if (!$query->row['status'] &&  strtotime($query->row['updated_at']) - strtotime('now') < 120) {
				$this->handleStorePaymentplan();
				$this->db->query("UPDATE `" . DB_PREFIX . "cron` SET status = 1 where cron_id = 2;");
			}
		}
	}

	public function checkConfigExist()
	{

		$query_setting = $this->db->query("SELECT * FROM `" . DB_PREFIX . "setting` where  `key` = 'payment_pace_checkout_plans';");
		if (!$query_setting->rows) {
			$this->db->query("INSERT INTO `oc_setting` ( `store_id`, `code`, `key`, `value`, `serialized`)
			VALUES
				(0, 'payment_pace_checkout', 'payment_pace_checkout_plans', '', 0);
			");
		}

		$query_cron = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cron` where  `cron_id` = 2;");
		if (!$query_cron->rows) {
			$this->db->query("INSERT INTO `oc_cron` (`cron_id`, `created_at`, `updated_at`)
         	VALUES
             (2, UTC_TIMESTAMP(), UTC_TIMESTAMP());
         	");
		}
	}

	public function handleStorePaymentplan($data)
	{
		$data = $this->getPacePlan();
		if (!!$data) {
			$this->load->model('setting/setting');
			$json_data = json_encode($data);
			$this->model_setting_setting->editSettingValue('payment_pace_checkout', 'payment_pace_checkout_plans', $json_data);
		}
	}

	public function getPacePlan()
	{
		try {
			$credential = $this->getUser();

			$ch = curl_init();
			$token = base64_encode($credential['user_name'] . ':' . $credential['password']);

			curl_setopt_array($ch, array(
				CURLOPT_URL            => $credential['api'] . '/v1/checkouts/plans',
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_ENCODING       => '',
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => 'GET',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER     => array(
					"Content-Type: text/plain",
					"authorization: Basic {$token}",
					"cache-control: no-cache"
				)
			));

			$getPacePlan = json_decode(curl_exec($ch));
			$error = curl_error($ch);

			curl_close($ch);

			if ($error) {
				throw new Exception($error);
			}

			if (isset($getPacePlan->error)) {
				throw new Exception($getPacePlan->error->message . '. code: ' . $getPacePlan->correlation_id);
			}

			return $getPacePlan->list;
		} catch (Exception $e) {
			$this->log->write($e->getMessage());
			return [];
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
