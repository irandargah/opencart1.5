<?php
class ControllerPaymentIrandargah extends Controller
{
    protected function index()
    {
        $this->language->load('payment/irandargah');
        $this->data['button_confirm'] = $this->language->get('button_confirm');

        $this->data['text_wait'] = $this->language->get('text_wait');
        $this->data['text_ersal'] = $this->language->get('text_ersal');
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/irandargah.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/payment/irandargah.tpl';
        } else {
            $this->template = 'default/template/payment/irandargah.tpl';
        }

        $this->render();
    }
    public function confirm()
    {

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $this->data['Amount'] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
        $this->data['PIN'] = $this->config->get('irandargah_PIN');
        $this->data['ResNum'] = $this->session->data['order_id'];
        $this->data['return'] = $this->url->link('checkout/success', '', 'SSL');
        $this->data['cancel_return'] = $this->url->link('checkout/payment', '', 'SSL');
        $this->data['back'] = $this->url->link('checkout/payment', '', 'SSL');

        $client = new SoapClient('https://www.dargaah.com/wsdl', array('encoding' => 'UTF-8'));

        if ((!$client)) {
            $json = array();
            $json['error'] = "Can not connect to IranDargah.<br>";

            $this->response->setOutput(json_encode($json));
        }

        $amount = intval($this->data['Amount']) / $order_info['currency_value'];
        if ($this->currency->getCode() != 'RLS') {
            $amount = $amount * 10;
        }

        $this->data['order_id'] = $this->session->data['order_id'];
        $callbackUrl = $this->url->link('payment/irandargah/callback&order_id=' . $this->data['order_id']);

        $result = $client->IRDPayment(
            array(
                'merchantID' => $this->data['PIN'],
                'amount' => $amount,
                'description' => 'سفارش شماره: ' . $order_info['order_id'],
                'mobile' => '',
                'orderId' => $this->data['ResNum'],
                'callbackURL' => $callbackUrl,
            )
        );
        if ($result->status == 200) {
            $this->data['action'] = 'https://www.dargaah.com/ird/startpay/' . $result->authority;
            $json = array();
            $json['success'] = $this->data['action'];
            $this->response->setOutput(json_encode($json));
        } else {
            $this->CheckState($result->status);
        }
    }

    public function CheckState($status)
    {
        $json = array();
        switch ($status) {
            case "-1":
                $json['error'] = "اطلاعات ارسالی ناقص می باشند";
                break;
            case "-2":
                $json['error'] = "وب سرويس نا معتبر می باشد";
                break;
            case "0":
                $json['error'] = "عمليات پرداخت طی نشده است";
                break;
            case "1":
                break;
            case "-11":
                $json['error'] = "مقدار تراکنش تطابق نمی کند";
                break;

            case "-12":
                $json['error'] = "زمان پرداخت طی شده و کاربر اقدام به پرداخت صورتحساب ننموده است";
                break;

            default:
                $json['error'] = "خطای نامشخص. کد خطا: ";
                break;
        }

    }

    public function verify_payment($authority, $amount)
    {
        if ($authority) {
            $client = new SoapClient('https://www.dargaah.com/wsdl', array('encoding' => 'UTF-8'));
            if ((!$client)) {
                echo "Error: can not connect to IranDargah.<br>";
                return false;
            } else {

                if ($this->currency->getCode() == 'TOM') {
                    $amount = $amount * 10;
                }
                $this->data['PIN'] = $this->config->get('irandargah_PIN');
                $result = $client->IRDVerification(
                    array(
                        'merchantID' => $this->data['PIN'],
                        'authority' => $authority,
                        'amount' => $amount,
                    )
                );
                $this->CheckState($result);

                if ($result->Status == 100) {
                    return true;
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }

        return false;
    }

    public function callback()
    {
        $authority = $this->request->get['authority'];
        $order_id = $this->request->get['orderId'];
        $MerchantID = $this->config->get('irandargah_PIN');
        $debugmod = false;

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);

        $amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false); //echo $this->data['Amount'];
        if ($order_info) {
            if (($this->verify_payment($authority, $amount)) or ($debugmod == true)) {
                $this->model_checkout_order->confirm($order_id, $this->config->get('irandargah_order_status_id'), 'شماره رسيد ديجيتالي; Authority: ' . $authority);

                $this->response->setOutput('<html><head><meta http-equiv="refresh" CONTENT="2; url=' . $this->url->link('checkout/success') . '"></head><body><table border="0" width="100%"><tr><td>&nbsp;</td><td style="border: 1px solid gray; font-family: tahoma; font-size: 14px; direction: rtl; text-align: right;">با تشکر پرداخت تکمیل شد.لطفا چند لحظه صبر کنید و یا  <a href="' . $this->url->link('checkout/success') . '"><b>اینجا کلیک نمایید</b></a></td><td>&nbsp;</td></tr></table></body></html>');

            } else {
                $this->response->setOutput('<html><body><table border="0" width="100%"><tr><td>&nbsp;</td><td style="border: 1px solid gray; font-family: tahoma; font-size: 14px; direction: rtl; text-align: right;">پرداخت موفقيت آميز نبود.1<br /><br /><a href="' . $this->url->link('checkout/cart') . '"><b>بازگشت به فروشگاه</b></a></td><td>&nbsp;</td></tr></table></body></html>');
            }
        }
    }

}
