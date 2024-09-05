<?php
class KlumpklumpModuleFrontController extends ModuleFrontController
{
    /**
     * Process order
     */
    public function postProcess()
    {
        $json = file_get_contents('php://input');
        var_dump($this->module);
        // $cart = $this->context->cart;
        // return $cart;
        /**
         * When no module isn't active
         */
        if (!$this->module->active) {
            Tools::redirect('index.php?controller=order');
        }

        /**
         * Send back to begining no delivery address
         * was entered and no active payment module was used.
         */
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            die($cart);
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == $this->moudule->name) {
                $authorized = true;
                break;
            }
        }
        if (!$authorized) {
            die($this->module->l('This payment method is not available.'));
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        // Process the payment here
        // This is where you'd integrate with your BNPL provider's API

        $this->module->validateOrder(
            $cart->id,
            Configuration::get('PS_OS_PAYMENT'),
            $total,
            $this->module->displayName,
            null,
            array(),
            (int)$currency->id,
            false,
            $customer->secure_key
        );

        Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
    }

    public function initContent()
    {
        if (!Tools::isSubmit('submitBuyNowPayLater')) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        parent::initContent();
    }
}
