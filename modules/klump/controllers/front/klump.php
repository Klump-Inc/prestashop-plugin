<?php
class KlumpklumpModuleFrontController extends ModuleFrontController
{
    /**
     * Process order
     */
    public function postProcess()
    {

        // Get cart information
        $cart = $this->context->cart;

        /**
         * Check that the cart is valid
         */ 
        if (!$cart) {
            var_dump('Invalid cart');
        }

        if (!$this->module->active) {
            Tools::redirect('index.php?controller=order');
        }

        /**
         * Send back to begining no delivery address
         * was entered and no active payment module was used.
         */
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        // Process the payment here
        $klump_data = file_get_contents('php://input');
        $webhook_result = $this->validatePayment($klump_data);

        if ($webhook_result['status'] && $webhook_result['result']['event'] == 'klump.payment.transaction.successful' && $webhook_result['result']['data']['status'] == 'successful') {
            $this->module->validateOrder(
                (int)$cart->id,
                Configuration::get('PS_OS_PAYMENT'),
                $total,
                $this->module->displayName,
                null,
                [],
                (int)$currency->id,
                false,
                $customer->secure_key
            );
        }
        header("HTTP/1.1 200 OK");
        http_response_code(200);
    }

    /**
     * Validate payment from Klump
     *
     * @return void
     */
    private function validatePayment($klump_event_payload)
    {   /**
        * Use the Klump verify paidment here. 
        */
                
        $merchantSeckey = Configuration::get('KLUMP_ENABLE_TEST_MODE_OPTION_1')
            ? Configuration::get('KLUMP_TEST_SECRET_KEY')
            : Configuration::get('KLUMP_LIVE_SECRET_KEY');

        $klump_data = json_decode($klump_data, true);
        $hash = hash_hmac('sha512', $klump_data, $merchantSeckey);
        if ($hash === $_SERVER['x-klump-signature']) {
            /**
            * The request is verified and it's coming from Klump
            * Go ahead and process it. While at it, return a 200 status code. when you are done.
            */
            if (isset($klump_data['event'])) {
                return [
                    'status' => true,
                    'result' => $klump_data
                ];
            }
        }
        /**
         * 
         */
        header("HTTP/1.1 400 Bad Request");
        http_response_code(400);
    }

    public function initContent()
    {
        if (!Tools::isSubmit('submitBuyNowPayLater')) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        parent::initContent();
    }
}
