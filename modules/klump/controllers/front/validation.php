<?php
/**
 * Copyyright since 2024 Klump Inc. and Contributors
 * 
 * Klump is a Nigerian Buy Now Pay Later company
 * @author Klump Inc <engineering@useklump.com>
 * @copyright 2024 Klump Inc. and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
class KlumpValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $reference = Tools::getValue('reference');
    
        // Get Cart Information
        $cart = $this->context->cart;

        if (!$cart) {
            var_dump($cart);
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
        $currency = $this->context->currency;
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
