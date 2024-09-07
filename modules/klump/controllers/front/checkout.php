<?php
/**
 * Copyyright since 2024 Klump Inc. and Contributors
 * 
 * Klump is a Nigerian Buy Now Pay Later company
 * @author Klump Inc <engineering@useklump.com>
 * @copyright 2024 Klump Inc. and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

/**
 * This module is what loads the Klump payment gateway
 */
class KlumpCheckoutModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {   
        /**
         * Get cart information and validte if this transaction
         * has gone through checkout, else redirect to the checkout
         */
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

        /**
         * If this isn't authorized, kill transaction
         */
        if (!$authorized) {
            die($this->module->getTranslator()->trans('This payment method is not available.', [], 'Modules.Klump.Shop'));
        }
        
        /**
         * Load Klump's checkout.
         */
        if ((int)Tools::getValue('klump_iframe') == 1) {
            Tools::redirect('index.php?controller=order&step=3&gateway=klump');
            exit;
        }
    }
}
