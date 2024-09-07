<?php
/*
This module is what loads the Klump payment gateway
*/

/**
 * @since 1.5.0
 */
class KlumpCheckoutModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'klump')
            {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->getTranslator()->trans('This payment method is not available.', array(), 'Modules.Paystack.Shop'));
        }
		
		if ((int)Tools::getValue('klump_iframe') == 1) {
			Tools::redirect('index.php?controller=order&step=3&gateway=klump');
			exit;
		}
     }
}
