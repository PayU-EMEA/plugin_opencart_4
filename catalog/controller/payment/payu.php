<?php

namespace Opencart\Catalog\Controller\Extension\PayU\Payment;
class PayU extends \Opencart\System\Engine\Controller
{
    const PAYU_VERSION = '4.0.0';

    private \Opencart\System\Library\Log $logger;

    //loading PayU SDK
    private function loadLibConfig(bool $sandbox)
    {
        require_once DIR_EXTENSION . 'payu/system/library/sdk_v21/openpayu.php';
        require_once DIR_EXTENSION . 'payu/system/payuoauthcache.php';

        \OpenPayU_Configuration::setMerchantPosId($this->config->get('payment_payu_merchantposid'));
        \OpenPayU_Configuration::setSignatureKey($this->config->get('payment_payu_signaturekey'));
        \OpenPayU_Configuration::setOauthClientId($this->config->get('payment_payu_oauth_client_id'));
        \OpenPayU_Configuration::setOauthClientSecret($this->config->get('payment_payu_oauth_client_secret'));
        \OpenPayU_Configuration::setEnvironment($sandbox ? 'sandbox' : 'secure');
        \OpenPayU_Configuration::setSender('OpenCart ver ' . VERSION . ' / Plugin ver ' . self::PAYU_VERSION);

        // \OpenPayU_Configuration::setOauthTokenCache(new \PayUOauthCache($this->cache));

        $this->logger = new \Opencart\System\Library\Log('payu.log');
    }

    public function index()
    {
        $this->load->language('extension/payu/payment/payu');

        $data['language'] = $this->config->get('config_language');

        return $this->load->view('extension/payu/payment/payu', $data);
    }

    public function confirm(): void
    {
        $this->load->language('extension/payu/payment/payu');

        $json = [];

        try {
            if (!isset($this->session->data['order_id'])) {
                throw new \Exception($this->language->get('error_order_id'));
            }

            if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method'] != 'payu') {
                throw new \Exception($this->language->get('error_payment_method'));
            }

            try {
                $this->loadLibConfig($this->config->get('payment_payu_sandbox'));
            } catch (\OpenPayU_Exception_Configuration $e) {
                throw new \Exception($this->language->get('error_openpayu_init'));
            }
        } catch (\Exception $e) {
            $json['error'] = $e->getMessage();
        }

        $order = $this->buildOrder();

        try {
            $response = \OpenPayU_Order::create($order);
            if ($response->getStatus() === 'SUCCESS') {
                $this->session->data['payu_order_id'] = $response->getResponse()->orderId;

                $this->load->model('checkout/order');

                $this->model_checkout_order->editTransactionId($this->session->data['order_id'], $response->getResponse()->orderId);
                $this->model_checkout_order->addHistory($this->session->data['order_id'], $this->config->get('payment_payu_new_status'), 'PayU - order id [' . $response->getResponse()->orderId . ']');

                $json['redirect'] = $response->getResponse()->redirectUri . '&lang=' . substr($this->config->get('config_language'), 0, 2);
            } else {
                $this->logger->write('OCR: ' . json_encode($order));
                $this->logger->write(
                    $response->getError() . ' [response: ' . json_encode($response->getResponse()) . ']'
                );

                $json['error'] = $this->language->get('error_ocr') . ' (' . $response->getStatus() . ': ' . \OpenPayU_Util::statusDesc($response->getStatus()) . ')';
            }
        } catch (\OpenPayU_Exception $e) {
            $this->logger->write('OCR: ' . json_encode($order));
            $this->logger->write('OCR Exception: ' . $e->getMessage());
            $json['error'] = $this->language->get('error_ocr');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function webhook(): void
    {
        try {
            $this->loadLibConfig($this->config->get('payment_payu_sandbox'));
        } catch (\OpenPayU_Exception_Configuration $e) {
            http_response_code(500);
            die('OPU Init error [' . $e->getMessage() . ']');
        }

        $body = file_get_contents('php://input');
        $data = trim($body);

        try {
            $result = \OpenPayU_Order::consumeNotification($data);
        } catch (\OpenPayU_Exception $e) {
            $this->logger->write('NOT: ' . $e->getMessage());
            http_response_code(500);
            die($e->getMessage());
        }

        if (property_exists($result->getResponse(), 'refund')) {
            die('Refund notification - ignore');
        }

        $this->load->model('checkout/order');

        $order_id = (int)preg_replace('/_.*$/', '', $result->getResponse()->order->extOrderId);
        $order = $this->model_checkout_order->getOrder($order_id);

        $payuOrderStatus = $result->getResponse()->order->status;
        $orderStatus = $order['order_status_id'];
        $statuses = [
            \OpenPayuOrderStatus::STATUS_CANCELED => $this->config->get('payment_payu_canceled_status'),
            \OpenPayuOrderStatus::STATUS_WAITING_FOR_CONFIRMATION => $this->config->get('payment_payu_waiting_for_confirmation_status'),
            \OpenPayuOrderStatus::STATUS_COMPLETED => $this->config->get('payment_payu_completed_status'),
            \OpenPayuOrderStatus::STATUS_PENDING => $this->config->get('payment_payu_new_status'),
        ];

        $statusesFlow = [
            \OpenPayuOrderStatus::STATUS_CANCELED => [$statuses[\OpenPayuOrderStatus::STATUS_WAITING_FOR_CONFIRMATION], $statuses[\OpenPayuOrderStatus::STATUS_PENDING]],
            \OpenPayuOrderStatus::STATUS_WAITING_FOR_CONFIRMATION => [$statuses[\OpenPayuOrderStatus::STATUS_PENDING]],
            \OpenPayuOrderStatus::STATUS_COMPLETED => [$statuses[\OpenPayuOrderStatus::STATUS_WAITING_FOR_CONFIRMATION], $statuses[\OpenPayuOrderStatus::STATUS_PENDING]]
        ];

        $this->load->model('localisation/order_status');
        $orderStatuses = [];
        foreach ($this->model_localisation_order_status->getOrderStatuses() as $v) {
            $orderStatuses[$v['order_status_id']] = $v['name'];
        }

        $ret = [];
        switch ($payuOrderStatus) {
            case \OpenPayuOrderStatus::STATUS_CANCELED:
            case \OpenPayuOrderStatus::STATUS_WAITING_FOR_CONFIRMATION:
            case \OpenPayuOrderStatus::STATUS_COMPLETED:
                $ret[] = 'Notify status [' . $payuOrderStatus . ']';

                if (in_array($orderStatus, $statusesFlow[$payuOrderStatus])) {
                    $newStatus = $statuses[$payuOrderStatus];
                    $paymentId = isset($result->getResponse()->properties) ? $this->extractPaymentIdFromProperties($result->getResponse()->properties) : null;
                    if ($orderStatus !== $newStatus) {
                        $this->model_checkout_order->addHistory($order_id, $newStatus, $paymentId ? 'PayU - payment id [' . $paymentId . ']' : '');
                        $ret[] = 'Change order status [' . $orderStatuses[$orderStatus] . '(' . $orderStatus . ')->' . $orderStatuses[$statuses[$payuOrderStatus]] . '(' . $statuses[$payuOrderStatus] . ')]';
                    } else {
                        $ret[] = 'Actual and new order status is the same [' . $orderStatuses[$orderStatus] . '(' . $orderStatus . ')] ';
                    }
                } else {
                    $ret[] = 'Order status not changed. Actual order status is [' . $orderStatuses[$orderStatus] . '(' . $orderStatus . ')] ';
                }
                break;
            default:
                $ret[] = 'Notify status [' . $payuOrderStatus . '] is ignored.';
        }

        echo implode('|', $ret);
    }

    private function buildOrder(): array
    {
        $this->language->load('extension/payu/payment/payu');

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $ocr = [];
        $ocr['merchantPosId'] = \OpenPayU_Configuration::getMerchantPosId();
        $ocr['description'] = $this->language->get('text_payu_order') . ' #' . $order_info['order_id'];
        $ocr['customerIp'] = $this->getIP($order_info['ip']);
        $ocr['notifyUrl'] = $this->url->link('extension/payu/payment/payu' . urlencode('|') . 'webhook', '', true);
        $ocr['continueUrl'] = $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true);
        $ocr['currencyCode'] = $order_info['currency_code'];
        $ocr['totalAmount'] = $this->toAmount(
            $this->currencyFormat($order_info['total'], $order_info['currency_code'])
        );
        $ocr['extOrderId'] = uniqid($order_info['order_id'] . '_', true);

        if (!empty($order_info['email'])) {
            $ocr['buyer'] = [
                'email' => $order_info['email'],
                'firstName' => $order_info['firstname'],
                'lastName' => $order_info['lastname']
            ];
        }

        return $ocr;
    }

    private function toAmount(string $value): int
    {
        return number_format($value * 100, 0, '', '');
    }

    private function currencyFormat(float $value, string $currencyCode): string
    {
        return $this->currency->format($value, $currencyCode, 0, false);
    }

    private function getIP(string $orderIP): string
    {
        return $orderIP === "::1"
        || $orderIP === "::"
        || !preg_match(
            "/^((?:25[0-5]|2[0-4][0-9]|[01]?[0-9]?[0-9]).){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9]?[0-9])$/m",
            $orderIP
        )
            ? '127.0.0.1' : $orderIP;
    }

    private function extractPaymentIdFromProperties(mixed $properties): ?string
    {
        if (is_array($properties)) {
            foreach ($properties as $property) {
                if ($property->name === 'PAYMENT_ID') {
                    return $property->value;
                }
            }
        }
        return null;
    }

}
