<?php
class ModelPaymentIrandargah extends Model
{

    public function getMethod()
    {
        $this->load->language('payment/irandargah');

        if ($this->config->get('irandargah_status')) {
            $status = true;
        } else {
            $status = false;
        }

        $method_data = array();

        if ($status) {
            $method_data = array(
                'code' => 'irandargah',
                'title' => $this->language->get('text_title'),
                'sort_order' => $this->config->get('irandargah_sort_order'),
            );
        }

        return $method_data;
    }
}
