<?php
/**
 * Copyyright since 2024 Klump Inc. and Contributors
 * 
 * Klump is a Nigerian Buy Now Pay Later company
 * @author Klump Inc <engineering@useklump.com>
 * @copyright 2024 Klump Inc. and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

 use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}


class Klump extends PaymentModule
{
    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->name = 'klump';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Klump Inc.';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Klump - Buy Now, Pay Later(BNPL)', [], 'Modules.Klump.Admin');
        $this->description = $this->trans('Klump is a Nigerian Buy Now, Pay Later company. With Klump, you get the ability to split your payment into instalments.', [], 'Modules.Klump.Admin');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?', [], 'Modules.Klump.Admin');

        if (!Configuration::get('KLUMP_NAME')) {
            $this->warning = $this->trans('No name provided', [], 'Modules.Klump.Admin');
        }

        // Set logo
        $this->logo = 'modules/' . $this->name . '/views/img/logo.png';
    }

    /**
     * Install Klump module
     *
     * @return void
     */
    public function install()
    {
        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('actionFrontControllerSetMedia');
    }

    /**
     * Uninstall Klump module
     *
     * @return void
     */
    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName('KLUMP_NAME');
    }

    /**
     * Payment Options
     *
     * @param [type] $params
     * @return void
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return [];
        }

        $newOption = new PaymentOption();
        $newOption->setCallToActionText($this->trans('Pay in Instalments - Pay with Klump BNPL ', [], 'Modules.Klump.Shop'))
                  ->setModuleName($this->name)
                  ->setAction($this->context->link->getModuleLink($this->name, 'validation', [], true))
                  ->setForm($this->generateForm());

        return [$newOption];
    }

    /**
     * Inject Klump JS
     *
     * @return void
     */
    public function hookActionFrontControllerSetMedia()
    {
        // Check if we're on the order page
        if ($this->context->controller->php_self == 'order') {
            $this->context->controller->registerJavascript(
                'buynowpaylater-external',
                'https://js.useklump.com/klump.js',
                [
                    'position' => 'head',
                    'priority' => 100,
                    'server' => 'remote',
                    'attributes' => 'defer'
                ]
            );
        }
    }

    /**
     * Payment Return
     *
     * @param [type] $params
     * @return void
     */
    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return [];
        }

        if (isset($params['objOrder']) && $params['objOrder']->module == $this->name) {
            $this->context->smarty->assign([
                'reference' => $params['objOrder']->reference,
                'status' => 'waiting for BNPL payment approval',
            ]);
            return $this->fetch('module:klump/views/templates/hook/payment_return.tpl');
        }
    }

    /**
     * Generate Payment Form
     *
     * @return void
     */
    private function generateForm()
    {
        $form = '
            <form action="' . $this->context->link->getModuleLink($this->name, 'validation', [], true) . '" method="post">
                <input type="hidden" name="klump_bnplpayment" value="1"/>
            </form>
            <div id="klump__checkout"></div>
            <script>
                const payload = {
                    publicKey: "klp_pk_3f28e5f86dc94db1b29a138e73538a0ff0427a963583440abc55660975714a81",
                    data: {
                        amount: 40100,
                        shipping_fee: 100,
                        currency: "NGN",
                        first_name: "John",
                        last_name: "Doe",
                        email: "john@example.com",
                        phone: "08012345678",
                        redirect_url: "https://verygoodmerchant.com/checkout/confirmation",
                        merchant_reference: "what-ever-you-want-this-to-be",
                        meta_data: {
                        customer: "Elon Musk",
                        email: "musk@spacex.com"
                        },
                        items: [
                            {
                                image_url: "https://s3.amazonaws.com/uifaces/faces/twitter/ladylexy/128.jpg",
                                item_url: "https://www.paypal.com/in/webapps/mpp/home",
                                name: "Awesome item",
                                unit_price: 20000,
                                quantity: 2,
                            }
                        ]
                    },
                    onSuccess: (data) => {
                        console.log("html onSuccess will be handled by the merchant");
                        console.log(data);
                        ok = data;
                        return data;
                    },
                    onError: (data) => {
                        console.log("html onError will be handled by the merchant");
                        console.log(data);
                    },
                    onLoad: (data) => {
                        console.log("html onLoad will be handled by the merchant");
                        console.log(data);
                    },
                    onOpen: (data) => {
                        console.log("html OnOpen will be handled by the merchant");
                        console.log(data);
                    },
                    onClose: (data) => {
                        console.log("html onClose will be handled by the merchant");
                        console.log(data);
                    }
                }
                document.getElementById("klump__checkout").addEventListener("click", function () {
                    const klump = new Klump(payload);
                });
            </script>
        ';
        return $form;
    }
}