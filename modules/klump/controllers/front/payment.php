<?php
class KlumpPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;
//        if (!$this->module->checkCurrency($cart))
//            Tools::redirect('index.php?controller=order');

        $total = sprintf(
            $this->getTranslator()->trans('%1$s (tax incl.)', array(), 'Modules.Paystack.Shop'),
            Tools::displayPrice($cart->getOrderTotal(true, Cart::BOTH))
        );

        $this->context->smarty->assign(array(
            'back_url' => $this->context->link->getPageLink('order', true, NULL, "step=3"),
//            'confirm_url' => $this->context->link->getModuleLink('paystack', 'validation', [], true),
//            'image_url' => $this->module->getPathUri() . 'card-logos.png',
//            'cust_currency' => $cart->id_currency,
//            'currencies' => $this->module->getCurrency((int)$cart->id_currency),
//            'total' => $total,
            'this_path' => $this->module->getPathUri(),
            'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/',

            'gateway_chosen' => 'klump',
//            'amount' => $this->context->cart->getOrderTotal(),
            'amount' => $total,
            'items' => json_encode($this->context->cart->getProducts()),
            'currency' => $this->context->currency->iso_code,
            'redirect_url' => $this->context->link->getModuleLink('klump', 'validation', [], true),
            'shipping_fee' => $this->context->cart->getOrderTotal(true, Cart::ONLY_SHIPPING),
            'merchant_reference' => 'order_' . $this->context->cart->id,
            'customer_first_name' => $this->context->customer->firstname,
            'customer_last_name' => $this->context->customer->lastname,
            'customer_email' => $this->context->customer->email,
            'customer' => $this->context->customer->id,
            'customer_address' => $this->context->cart->id_address_invoice,
            'merchant_public_key' => Configuration::get('KLUMP_PUBLIC_KEY'),
            'customer_phone' => $this->context->customer->phone,
        ));

        $this->setTemplate('payment_execution.tpl');
    }
}
