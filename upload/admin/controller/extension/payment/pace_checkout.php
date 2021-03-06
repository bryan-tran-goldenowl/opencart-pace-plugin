<?php
class ControllerExtensionPaymentPaceCheckout extends Controller
{
	private $error = array();

	public function install()
	{
		$this->load->model('extension/payment/pace_checkout');
		$this->model_extension_payment_pace_checkout->install();
	}

	public function uninstall()
	{
		$this->load->model('extension/payment/pace_checkout');
		$this->model_extension_payment_pace_checkout->uninstall();
	}

	public function index()
	{
		$this->load->language('extension/payment/pace_checkout');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('extension/payment/pace_checkout');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('payment_pace_checkout', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment/pace_checkout', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/payment/pace_checkout', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);


		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$field = [
			'payment_pace_checkout_status',
			'payment_pace_checkout_pace_mode',
			'payment_pace_checkout_status_sandbox',
			'payment_pace_checkout_order_status_id',
			'payment_pace_checkout_order_status_transaction_cancelled',
			'payment_pace_checkout_order_status_transaction_expired',
			'payment_pace_checkout_username',
			'payment_pace_checkout_password',
			'payment_pace_checkout_username_sandbox',
			'payment_pace_checkout_password_sandbox',
			'payment_pace_checkout_product_widget_status',
			'payment_pace_checkout_product_theme_color',
			'payment_pace_checkout_product_primary_color',
			'payment_pace_checkout_product_second_color',
			'payment_pace_checkout_product_fontsize',
			'payment_pace_checkout_product_widget_style',
			'payment_pace_checkout_catalog_widget_status',
			'payment_pace_checkout_catalog_theme_color',
			'payment_pace_checkout_catalog_primary_color',
			'payment_pace_checkout_catalog_fontsize',
			'payment_pace_checkout_catalog_widget_style',
			'payment_pace_checkout_widget_status',
			'payment_pace_checkout_primary_color',
			'payment_pace_checkout_product_second_color',
			'payment_pace_checkout_second_color',
			'payment_pace_checkout_text_timeline_color',
			'payment_pace_checkout_background_color',
			'payment_pace_checkout_foreground_color',
			'payment_pace_checkout_fontsize',
			'payment_pace_checkout_fallback_widget',
			'payment_pace_checkout_logo_style'
		];


		foreach ($field as $value) {
			if (isset($this->request->post[$value])) {
				$data[$value] = $this->request->post[$value];
			} else {
				$data[$value] = $this->config->get($value);
			}
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/pace_checkout', $data));
	}

	protected function validate()
	{
		if (!$this->user->hasPermission('modify', 'extension/payment/pace_checkout')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
