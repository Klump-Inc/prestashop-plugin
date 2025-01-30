<?php

class klumpklumpstatusModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $cart = $this->context->cart;
        $reference = Tools::getValue('reference');

        if (!$reference || !$this->module->active) {
            Tools::redirect('index.php?controller=order');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'klump') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->getTranslator()->trans('This payment method is not available.', [], 'Modules.Klump.Shop'));
        }

        if ((int)Tools::getValue('klump_iframe') == 1) {
            Tools::redirect('index.php?controller=order&step=3&gateway=klump');
            exit;
        }

        /**
         * Load customer information
         */
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        /**
         * Grab total and currency
         */
//        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        /**
         * Verify transaction
         */
        $verify_payment = $this->verifyPayment($reference);
        if(($verify_payment['state'] !== 'success') || !array_key_exists('data', $verify_payment) || $verify_payment['data']['status'] !== 'successful') {
            Tools::redirect('404');
        } else {
            $currency_order = new Currency($cart->id_currency);

            $extra_vars = [
                'transaction_id' => $reference,
                'payment_method' => 'Klump',
                'status' => 'Paid',
                'currency' => $currency_order->iso_code
            ];

            $this->module->validateOrder(
                (int)$cart->id,
                Configuration::get('PS_OS_PAYMENT'),
                $total,
                $this->module->displayName,
                'Klump Reference: '.$reference,
                $extra_vars,
                (int)$cart->id_currency,
                false,
                $customer->secure_key
            );
            Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key.'&reference='.$reference);
        }
    }

    /**
     * Verify a transaction from Klump
     *
     * @param [string] $reference
     * @return array
     */
    private function verifyPayment($reference)
    {
        // Get the merchant public key depending on the mode
        $merchantSecretkey = Configuration::get('KLUMP_MODE')
            ? Configuration::get('KLUMP_TEST_SECRET_KEY')
            : Configuration::get('KLUMP_LIVE_SECRET_KEY');


        $options = [
            'http' => [
                'method'=>"GET",
                'header'=> ["klump-secret-key:" . $merchantSecretkey . "\r\n"]
            ]
        ];

        $context = stream_context_create($options);
        $url = 'https://api.useklump.com/v1/transactions/' . $reference . '/verify';
        $request = file_get_contents($url, false, $context);
        $result = json_decode($request, true);
        return $result;
    }
}
