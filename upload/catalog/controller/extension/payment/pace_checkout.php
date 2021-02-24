<?php
class ControllerExtensionPaymentPaceCheckout extends Controller
{
	public function index()
	{

		$this->load->model('setting/setting');
		$this->load->model('extension/module/pace');
		$order = $this->model_extension_module_pace->getOrder($this->session->data['order_id']);
		$setting = $this->model_setting_setting->getSetting('payment_pace_checkout');

		$data['payment_pace_checkout_widget_status']       = $setting['payment_pace_checkout_widget_status'];
		$data['payment_pace_checkout_primary_color']       = $setting['payment_pace_checkout_primary_color'];
		$data['payment_pace_checkout_second_color']        = $setting['payment_pace_checkout_second_color'];
		$data['payment_pace_checkout_text_timeline_color'] = $setting['payment_pace_checkout_text_timeline_color'];
		$data['payment_pace_checkout_background_color']    = $setting['payment_pace_checkout_background_color'];
		$data['payment_pace_checkout_foreground_color']    = $setting['payment_pace_checkout_foreground_color'];
		$data['payment_pace_checkout_fontsize']            = $setting['payment_pace_checkout_fontsize'];
		$data['price']                                     = (float) $order['total'];

		return $this->load->view('extension/payment/pace_checkout', $data);
	}

	public function confirm()
	{
		$errors = array();
		$this->load->model('extension/module/pace');
		$this->load->model('checkout/order');
		$this->load->model('setting/setting');
		$this->load->language('extension/payment/pace_checkout');
		try {
			if (isset($this->session->data['payment_method']['code'])  && $this->session->data['payment_method']['code'] == 'pace_checkout') {
				$data = $this->session->data;
				$order = $this->model_extension_module_pace->getOrder($this->session->data['order_id']);

				if (!$order) {
					throw new \Exception('Cannot get the order.');
				}

				$transaction = $this->model_extension_module_pace->getOrderTransaction($this->session->data['order_id']);

				if (!$transaction) {
					$result = $this->handleCreateTransaction($order);
					$transaction = json_decode($result, true);
					// attach order id to transaction
					$transaction['order_id'] = (int) $this->session->data['order_id'];

					if (isset($transaction['error'])) {
						throw new \Exception(sprintf($this->language->get('create_transaction_error'), $transaction['correlation_id']));
					}

					$order_status = (int) $this->model_extension_module_pace->updateOrderStatus($transaction); /*update orders status based on Pace transaction*/
					$this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $order_status);
					$this->model_extension_module_pace->insertTransaction($this->session->data['order_id'], $transaction['transactionID'], $result);
				}

				$setting                      = $this->model_setting_setting->getSetting('payment_pace_checkout');
				$transaction['pace_mode']     = $setting['payment_pace_checkout_pace_mode'];
				$transaction['pace_approved'] = isset($setting['payment_pace_checkout_order_status_transaction_approved']) ? $setting['payment_pace_checkout_order_status_transaction_approved'] : 5;
				
				// build transaction query on success url
				$parse_success_url = parse_url( $this->url->link('checkout/success') );
				$list_query = array();

				if ( isset( $parse_success_url['query'] ) ) {
					$route = explode( '=', $parse_success_url['query'] );
					$list_query[$route[0]] = $route[1];
				}
				$list_query['transactionId'] = $transaction['transactionID'];
				$list_query['merchantReferenceId'] = $transaction['merchantID'];
				$build_query = http_build_query( $list_query );
				
				$transaction['redirect_success'] = $parse_success_url['scheme'] . '://' . $parse_success_url['host'] . $parse_success_url['path'] . '?' .$build_query;
				$transaction['redirect_failure'] = $this->url->link('checkout/failure');
			} else {
				throw new \Exception('Session is expired please refresh a page');
			}


			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($transaction));
		} catch (Exception $e) {
			$error['error']  = $e->getMessage();
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($error));
		}
	}

	public function updateOrderStatus()
	{
		extract($_GET); /* retrieve get params */
		$this->load->model('checkout/order');

		$this->model_checkout_order->addOrderHistory($orderID, $status);

		$_sql = sprintf("UPDATE `%sorder` SET order_status_id=%d WHERE order_id=%d", DB_PREFIX, $status, $orderID);
		// do update
		$this->db->query($_sql);
	}

	private function setCart($order)
	{
		$url = ($this->request->server['HTTPS'] ? HTTPS_SERVER : HTTP_SERVER)
			. "admin/index.php?route=extension/payment/pace_checkout/runCron";
		$user = $this->model_extension_module_pace->getUser();
		$url = preg_replace("/([http|https]:\/\/)/", "$1$user[user_name]:$user[password]@", $url);
		$data = $this->session->data;
		return array(
			'items'		   => [],
			'amount'	   => $order['total'] * 100,
			'currency'     => $this->session->data['currency'] ? $this->session->data['currency'] : $this->config->get('config_currency'),
			'webhookUrl'   => $url,
			'referenceID'  => (string) $data['order_id'],
			'redirectUrls' => array(
				'success' => $this->url->link('checkout/success'),
				'failed'  => $this->url->link('checkout/failure')
			)
		);
	}

	public function get_source_order_items($items, &$source)
	{

		array_walk($items, function ($item, $id) use (&$source) {
			// get WC_Product item by ID
			$source_item = array(
				'itemID'         => "$item[product_id]",
				'itemType'       => 'qwerqwerqwe',
				'reference'      => "$item[product_id]",
				'name'           => $item['name'],
				'productUrl'     => $this->url->link('product/product', 'product_id=' . $item['product_id']),
				'imageUrl'       =>	$this->config->get('config_url') . "/" . $item['image'],
				'quantity'       => (int) $item['quantity'],
				'tags'           => [""],
				'unitPriceCents' => (string) $item["total"]
			);
			$source['items'][] =  $source_item;
		});

		return $source;
	}

	private function handleCreateTransaction($order)
	{
		$cart = $this->setCart($order);
		$this->get_source_order_items($this->cart->getProducts(), $cart);

		$ch = curl_init();

		$data = $this->model_extension_module_pace->getUser();

		curl_setopt($ch, CURLOPT_URL, $data['api'] . '/v1/checkouts');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cart));

		$headers = array();
		$headers[] = 'Content-Type: text/plain';
		$headers[] = 'Authorization: Basic ' . base64_encode($data['user_name'] . ':' . $data['password']);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$result = curl_exec($ch);
		if (curl_errno($ch)) {
			echo 'Error:' . curl_error($ch);
		}
		curl_close($ch);

		$ch = curl_init();
		return $result;
	}
}
