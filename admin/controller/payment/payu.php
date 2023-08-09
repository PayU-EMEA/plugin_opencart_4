<?php
namespace Opencart\Admin\Controller\Extension\PayU\Payment;
class PayU extends \Opencart\System\Engine\Controller {

    const PAYU_VERSION = '4.0.1';

    public function index() {
        $this->load->language('extension/payu/payment/payu');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/oc_payment_example/payment/credit_card', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['save'] = $this->url->link('extension/payu/payment/payu|save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

        $data['text_info'] = sprintf($this->language->get('text_info'), self::PAYU_VERSION);

        $data['payment_payu_merchantposid'] = $this->config->get('payment_payu_merchantposid');
        $data['payment_payu_signaturekey'] = $this->config->get('payment_payu_signaturekey');
        $data['payment_payu_oauth_client_id'] = $this->config->get('payment_payu_oauth_client_id');
        $data['payment_payu_oauth_client_secret'] = $this->config->get('payment_payu_oauth_client_secret');

        $data['payment_payu_new_status'] = $this->config->get('payment_payu_new_status');
        $data['payment_payu_completed_status'] = $this->config->get('payment_payu_completed_status');
        $data['payment_payu_canceled_status'] = $this->config->get('payment_payu_canceled_status');
        $data['payment_payu_waiting_for_confirmation_status'] = $this->config->get('payment_payu_waiting_for_confirmation_status');

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['payment_payu_geo_zone_id'] = $this->config->get('payment_payu_geo_zone_id');

        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $data['payment_payu_sandbox'] = $this->config->get('payment_payu_sandbox');
        $data['payment_payu_status'] = $this->config->get('payment_payu_status');
        $data['payment_payu_sort_order'] = $this->config->get('payment_payu_sort_order');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payu/payment/payu', $data));
    }

    public function save(): void {
        $this->load->language('extension/payu/payment/payu');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/payu/payment/payu')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['payment_payu_merchantposid']) {
            $json['error']['merchantposid'] = $this->language->get('error_merchantposid');
        }

        if (!$this->request->post['payment_payu_signaturekey']) {
            $json['error']['signaturekey'] = $this->language->get('error_signaturekey');
        }

        if (!$this->request->post['payment_payu_oauth_client_id']) {
            $json['error']['oauthclientid'] = $this->language->get('error_oauth_client_id');
        }

        if (!$this->request->post['payment_payu_oauth_client_secret']) {
            $json['error']['oauthclientsecret'] = $this->language->get('error_oauth_client_secret');
        }

        if (!$json) {
            $this->load->model('setting/setting');

            $this->model_setting_setting->editSetting('payment_payu', $this->request->post);

            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function install(): void
    {
        $this->load->model('extension/payu/payment/payu');
        $this->load->model('setting/setting');

        $settings = [
            'payment_payu_new_status' => 1,
            'payment_payu_completed_status' => 2,
            'payment_payu_canceled_status' => 7,
            'payment_payu_waiting_for_confirmation_status' => 1,
            'payment_payu_geo_zone_id' => 0,
            'payment_payu_sort_order' => 1,
            'payment_payu_sandbox' => 0
        ];
        $this->model_setting_setting->editSetting('payment_payu', $settings);
        $this->model_extension_payu_payment_payu->install();
    }

    public function uninstall(): void
    {
        $this->load->model('extension/payu/payment/payu');
        $this->load->model('setting/setting');

        $this->model_setting_setting->deleteSetting('payment_payu');
        $this->model_extension_payu_payment_payu->uninstall();
    }
}
