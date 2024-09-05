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

        // Is this plugin active
        $this->active = Configuration::get('DISABLE_KLUMP_OPTION_1');

        // Set basic configuration options
        $this->config_keys = [
            'KLUMP_NAME' => 'Klump',
        ];

        if (!Configuration::get('KLUMP_NAME')) {
            $this->warning = $this->trans('No name provided', [], 'Modules.Klump.Admin');
        }

        // Set logo
        $this->logo = 'modules/' . $this->name . '/logo.png';
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
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('moduleRoutes')
            && $this->installConfiguration();
    }

    /**
     * Uninstall Klump module
     *
     * @return void
     */
    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName('KLUMP_NAME')
            && $this->uninstallConfiguration();
    }

    /**
     * Payment Options
     *
     * @param [type] $params
     * @return void
     */
    public function hookPaymentOptions($params)
    {
        /**
         * Check if the module is active and activate Klump BNPL
         * on the store front.
         */
        if (!$this->active) {
            return '';
        }

        /**
         * Make sure the plugin can be used by only Nigerian merchants
         * else don't render checkout form
         */
        $id_default_currency = Configuration::get('PS_CURRENCY_DEFAULT');
        $defaultCurrency = new Currency($id_default_currency);
        $currency = $defaultCurrency->iso_code; // e.g., USD, EUR

        if ($currency !== 'NGN') {
            return '';
        }

        $newOption = new PaymentOption();

        // Set the label or name of the payment method
        $newOption->setCallToActionText($this->trans(
            'Pay with Klump Buy Now, Pay Later ', [], 'Modules.Klump.Shop')
        );

        // Set webhook link
        $newOption->setAction($this->context->link->getModuleLink($this->name, 'klump'));

        // Set module name
        $newOption->setModuleName($this->name);
        
        // Set the logo
        $newOption->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/logo.png'));

        // BNPL should only come in when a user has cart size more than N10,000
        $cart = $this->context->cart;
        if ($cart->getOrderTotal() < 10000) {
            $newOption->setAdditionalInformation('<div class="alert alert-warning">Increase cart total value to at least <strong>N10,000</strong> in order to use Buy Now, Pay Later.</div>');
        } else {
            $newOption->setForm($this->generateForm());
        }

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
     * Install configuration settings
     *
     * @return void
     */
    private function installConfiguration()
    {
        // Set default values for configuration keys
        foreach ($this->config_keys as $key => $val) {
            Configuration::updateValue($key, $val);
        }
        return true;
    }

    /**
     * Uninstall the module's configuration settings
     *
     * @return void
     */
    private function uninstallConfiguration()
    {
        // Remove configuration keys
        foreach ($this->config_keys as $key) {
            Configuration::deleteByName($key);
        }
        return true;
    }

    /**
     * Part of the configuration for the plugin
     *
     * This is a backoffice operation
     *
     * @return void
     */
    public function getContent()
    {
        $output = '';

        $publicTestKeyRegex = '/klp_pk_test_[a-zA-Z0-9]+/m';
        $secretTestKeyRegex = '/klp_sk_test_[a-zA-Z0-9]+/m';

        $publicLiveKeyRegex = '/klp_pk_[a-zA-Z0-9]+/m';
        $secretLiveKeyRegex = '/klp_sk_[a-zA-Z0-9]+/m';

        // Check if form is submitted
        if (Tools::isSubmit('submitKlumpBNPL')) {
            // Get submitted values
            $test_public_key = Tools::getValue('TEST_PUBLIC_KEY');
            $test_secret_key = Tools::getValue('TEST_SECRET_KEY');
            $live_public_key = Tools::getValue('LIVE_PUBLIC_KEY');
            $live_secret_key = Tools::getValue('LIVE_SECRET_KEY');
            $webhook_url = Tools::getValue('WEBHOOK_URL');
            $enable_test_mode = Tools::getValue('ENABLE_TEST_MODE_OPTION_1') ? true : false;
            $disable_klump = Tools::getValue('DISABLE_KLUMP_OPTION_1') ? false : true;

            // Initialize validation error array
            $errors = [];

            // validate test public key
            if (empty($test_public_key) || preg_match($publicTestKeyRegex, $test_public_key) === 0) {
                $errors[] = $this->l('Test public key is either empty or invalid. A valid test public keyy should be of the format klp_pk_test_xxxxxxxxxxxxxxx');;
            }

            // validate test secret key
            if (empty($test_secret_key) || preg_match($secretTestKeyRegex, $test_secret_key) === 0) {
                $errors[] = $this->l('Test secret key is either empty or invalid. A valid test secret key should be of the format klp_sk_test_xxxxxxxxxxxxxxx');
            }

            // validate live public key
            if (empty($live_public_key) || preg_match($publicLiveKeyRegex, $live_public_key) === 0) {
                $errors[] = $this->l('Live public key is either empty or invalid. A valid live public key should be of the format klp_pk_xxxxxxxxxxxxxxx');
            }

            // validate live secret key
            if (empty($live_secret_key) || preg_match($secretLiveKeyRegex, $live_secret_key) === 0) {
                $errors[] = $this->l('Live secret key is either empty or invalid. A valid live secret key should be of the format klp_sk_xxxxxxxxxxxxxxx');
            }

            // if error exist, display them
            if (count($errors) > 0) {
                $output .= $this->displayError(implode('<br>', $errors));
            } else {
                // Save configuration values
                Configuration::updateValue('TEST_PUBLIC_KEY', $test_public_key);
                Configuration::updateValue('TEST_SECRET_KEY', $test_secret_key);
                Configuration::updateValue('LIVE_PUBLIC_KEY', $live_public_key);
                Configuration::updateValue('LIVE_SECRET_KEY', $live_secret_key);
                Configuration::updateValue('WEBHOOK_URL', $webhook_url);
                Configuration::updateValue('ENABLE_TEST_MODE_OPTION_1', $enable_test_mode);
                Configuration::updateValue('DISABLE_KLUMP_OPTION_1', $disable_klump);
                $output .= $this->displayConfirmation($this->l('Settings updated successfully'));
            }
        }

        return $output . $this->renderForm();
    }

    /**
     * This form is used at the Configuration page
     * for the module. It will collect things like 
     * API keys, etc.
     *
     * This is a backoffice operation
     *
     * @return void
     */
    private function renderForm()
    {

        $id_default_currency = Configuration::get('PS_CURRENCY_DEFAULT');
        $defaultCurrency = new Currency($id_default_currency);
        $currency = $defaultCurrency->iso_code; // e.g., USD, EUR

        if ($currency !== 'NGN') {
            return '<div class="alert alert-warning">Please set your default currency to Nigerian Naira(NGN) before you can configure Klump\'s Buy Now, Pay Later</div>';
        }
        $helper = new HelperForm();

        // Set form properties
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->module = $this;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitKlumpBNPL';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        // Load current values
        $helper->fields_value['TEST_PUBLIC_KEY'] = Tools::getValue('TEST_PUBLIC_KEY', Configuration::get('TEST_PUBLIC_KEY'));
        $helper->fields_value['TEST_SECRET_KEY'] = Tools::getValue('TEST_SECRET_KEY', Configuration::get('TEST_SECRET_KEY'));

        $helper->fields_value['LIVE_PUBLIC_KEY'] = Tools::getValue('LIVE_PUBLIC_KEY', Configuration::get('LIVE_PUBLIC_KEY'));
        $helper->fields_value['LIVE_SECRET_KEY'] = Tools::getValue('LIVE_SECRET_KEY', Configuration::get('LIVE_SECRET_KEY'));

        $helper->fields_value['WEBHOOK_URL'] = Tools::getValue('WEBHOOK_URL', Configuration::get('WEBHOOK_URL'));
        $helper->fields_value['ENABLE_TEST_MODE_OPTION_1'] = Tools::getValue('ENABLE_TEST_MODE_OPTION_1', Configuration::get('ENABLE_TEST_MODE_OPTION_1'));

        $disable_klump = Configuration::get('DISABLE_KLUMP_OPTION_1') ? false : true;
        $helper->fields_value['DISABLE_KLUMP_OPTION_1'] = Tools::getValue('DISABLE_KLUMP_OPTION_1', $disable_klump);

        // Define form fields
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings'),
                    'icon' => 'icon-cogs'
                ],
                'description' => 'Fill out the form below to activate Klump\'s BNPL. You can get the values for the form below by checking your <a href="https://merchant.useklump.com/settings">merchant dashboard</a>',
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Test Public Key'),
                        'name' => 'TEST_PUBLIC_KEY',
                        'size' => 40,
                        'required' => true
                    ],[
                        'type' => 'password',
                        'label' => $this->l('Test Secret Key'),
                        'name' => 'TEST_SECRET_KEY',
                        'size' => 40,
                        'required' => true
                    ],[
                        'type' => 'text',
                        'label' => $this->l('Live Public Key'),
                        'name' => 'LIVE_PUBLIC_KEY',
                        'size' => 40,
                        'required' => true
                    ],[
                        'type' => 'password',
                        'label' => $this->l('Live Secret Key'),
                        'name' => 'LIVE_SECRET_KEY',
                        'size' => 40,
                        'required' => true
                    ],[ 
                        'label' => $this->l('Webhook URL'),
                        'name' => 'WEBHOOK_URL',
                        'size' => 40,
                        'desc' => 'Please copy and paste this webhook URL on your API Keys & Webhooks tab of your <a href="https://merchant.useklump.com/settings" target="_blank">settings page on your dashboard</a> &mdash; <strong><code>' . Tools::getHttpHost(true).__PS_BASE_URI__ . 'klump/webhook' . '</code></strong>'
                    ],[
                        'type' => 'checkbox',
                        'label' => $this->l('Enable Test Mode'),
                        'name' => 'ENABLE_TEST_MODE', // This is the base name
                        'required' => false,
                        'desc' => 'This will allow you to test your Klump BNPL integration without any real payments. Use this during development and testing. Uncheck this box when you are ready to go to production/live',
                        'values' => [
                            'query' => [
                                ['id' => 'OPTION_1', 'name' => $this->l('Enable')]
                            ],
                            'id' => 'id',      // The ID of the checkbox
                            'name' => 'name'   // The display name of the checkbox
                        ]
                        ], [
                            'type' => 'checkbox',
                            'label' => $this->l('Disable Klump'),
                            'name' => 'DISABLE_KLUMP', // This is the base name
                            'required' => false,
                            'desc' => 'This will will remove Klump Buy Now, Pay Later from your checkout page.',
                            'values' => [
                                'query' => [
                                    ['id' => 'OPTION_1', 'name' => $this->l('Disable')]
                                ],
                                'id' => 'id',      // The ID of the checkbox
                                'name' => 'name'   // The display name of the checkbox
                            ]
                        ]
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right'
                ]
            ]
        ];

        return $helper->generateForm([$fields_form]);
    }

    /**
     * Generate Payment Form
     * This form is displayed on the checkout page
     *
     * This is a user facing/store operation
     *
     * @return void
     */
    private function generateForm()
    {
        // Get the merchant public key depending on the mode
        $merchantPublickey = Configuration::get('ENABLE_TEST_MODE_OPTION_1')
            ? Configuration::get('TEST_PUBLIC_KEY')
            : Configuration::get('LIVE_PUBLIC_KEY');

        // Accessing cart information to populate checkout form
        $cart = $this->context->cart;
        if (!Validate::isLoadedObject($cart)) {
            return [];
        }

        // Build products array with images
        $products = [];
        foreach ($cart->getProducts() as $product) {
            $products[] = [
                'image_url' => $this->context->link->getImageLink($product['link_rewrite'], $product['id_image']),
                'item_url' => $this->context->link->getProductLink($product['id_product']),
                'name' => $product['name'],
                'unit_price' => $product['price'],
                'quantity' => $product['cart_quantity']
            ];
        }

        // Get customer information
        $customer = new Customer((int) $cart->id_customer);

        // Generate checkout form/button
        $form = '
            <form>
                <div id="klump__checkout"></div>
            </form>
            <script>
                const payload = {
                    publicKey: "' . $merchantPublickey . '",
                    data: {
                        amount: ' . $cart->getOrderTotal() . ',
                        shipping_fee: ' . $cart->getOrderTotal(true, Cart::ONLY_SHIPPING) . ',
                        currency: "NGN",
                        first_name: "' . $customer->firstname . '",
                        last_name: "' . $customer->lastname . '",
                        email: "' . $customer->email . '",
                        meta_data: {
                            customer: "' . $customer->firstname . ' ' . $customer->lastname . '",
                            email: "' . $customer->email . '",
                        },
                        items: ' . json_encode($products) . '
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
                        location.reload();
                    }
                }
                document.getElementById("klump__checkout").addEventListener("click", function () {
                    const klump = new Klump(payload);
                });
            </script>
        ';
        return $form;
    }

    /**
     * Custom controller for webhook
     *
     * @param [type] $params
     * @return void
     */
    public function hookModuleRoutes($params)
    {
        return array(
            'module-klump-webhook' => array(
                'controller' => 'Klump',
                'rule' => 'klump/webhook', // Custom URL (e.g., www.yourshop.com/my-custom-url)
                'keywords' => array(),
                'params' => array(
                    'fc' => 'module',
                    'module' => $this->name,
                ),
            ),
        );
    }
}