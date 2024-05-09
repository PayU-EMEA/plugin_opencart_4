<?php

namespace Opencart\Catalog\Model\Extension\PayU\Payment;

class PayU extends \Opencart\System\Engine\Model
{
    // For compatibility with Opencart < 4.0.2
    public function getMethod(array $address = []): array
    {
        return $this->getMethods($address);
    }

    public function getMethods(array $address = []): array
    {
        $this->load->language('extension/payu/payment/payu');

        if ($this->cart->hasSubscription()) {
            $status = false;
        } elseif (!$this->config->get('config_checkout_payment_address')) {
            $status = true;
        } elseif (!$this->config->get('payment_bank_transfer_geo_zone_id')) {
            $status = true;
        } else {
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$this->config->get('payment_payu_geo_zone_id') . "' AND `country_id` = '" . (int)$address['country_id'] . "' AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')");

            if ($query->num_rows) {
                $status = true;
            } else {
                $status = false;
            }
        }

        $method_data = [];

        if ($status) {
            $option_data['payu'] = [
                'code' => 'payu.payu',
                'name' => $this->language->get('heading_title')
            ];

            $method_data = [
                'code' => 'payu',
                'title' => $this->language->get('heading_title'), // For compatibility with Opencart < 4.0.2
                'name' => $this->language->get('heading_title'),
                'option' => $option_data,
                'sort_order' => $this->config->get('payment_payu_sort_order')
            ];
        }

        return $method_data;
    }
}
