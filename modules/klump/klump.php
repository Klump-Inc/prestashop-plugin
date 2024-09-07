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
        $this->is_eu_compatible = 0;
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_
        ];
        $this->bootstrap = true;

        $this->controllers = ['validation', 'checkout'];

        parent::__construct();

        $this->displayName = $this->trans('Klump - Buy Now, Pay Later(BNPL)', [], 'Modules.Klump.Admin');
        $this->description = $this->trans(
            'Klump is a Nigerian Buy Now, Pay Later company. With Klump, you get the ability to split your payment into instalments.',
            [],
            'Modules.Klump.Admin'
        );

        $this->confirmUninstall = $this->trans(
            'Are you sure you want to uninstall Klump - Buy Now, Pay Later(BNPL)?',
            [],
            'Modules.Klump.Admin'
        );
        
        /**
         * Make sure the plugin can be used by only Nigerian merchants
         */
        $id_default_currency = Configuration::get('PS_CURRENCY_DEFAULT');
        $default_currency = new Currency($id_default_currency);
        $this->default_currency = $default_currency->iso_code; // e.g., USD, EUR

        if ($this->default_currency !== 'NGN') {
            $this->warning = $this.trans(
                'Please set your default currency to Nigerian Naira(NGN) before you can configure Klump\'s Buy Now, Pay Later module',
                [],
                'Modules.Klump.Admin'
            );
        }

        // Is this plugin active
        $this->active = Configuration::get('KLUMP_DISABLE');

        // Set basic configuration options
        $this->config_keys = [
            'KLUMP_NAME' => 'Klump',
            'KLUMP_TEST_PUBLIC_KEY' => '',
            'KLUMP_TEST_SECRET_KEY' => '',
            'KLUMP_LIVE_PUBLIC_KEY' => '',
            'KLUMP_LIVE_SECRET_KEY' => '',
            'KLUMP_MODE' => '',
            'KLUMP_DISABLE' => ''
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
            && $this->registerHook('moduleRoutes')
            && $this->installConfiguration();
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
        if ($this->default_currency !== 'NGN') {
            return '';
        }

        $gateway_chosen = 'none';

        if (Tools::getValue('gateway') == 'klump') {
            $gateway_chosen = 'klump';
        }

        $newOption = new PaymentOption();

        // Set the label or name of the payment method
        $newOption->setCallToActionText($this->trans(
            'Pay with Klump Buy Now, Pay Later ', [], 'Modules.Klump.Shop')
        );

        // Checkout
        $newOption->setAction($this->context->link->getModuleLink($this->name, 'checkout', [], true));

        // Set module name
        $newOption->setModuleName($this->name);
        
        // Set the logo
        $newOption->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/logo.png'));

        /**
         * This is injected into the form. This way,
         * the user gets redirected automatically if they ever select klump
         */
        $newOption->setInputs([
            'klump_iframe' => [
                'name' =>'klump_iframe',
                'type' =>'hidden',
                'value' =>'1',
            ]
        ]);

        // BNPL should only come in when a user has cart size more than N10,000
        $cart = $this->context->cart;

        if ($cart->getOrderTotal() < 10000) {
            $newOption->setAdditionalInformation('<div class="alert alert-warning">Increase cart total value to at least <strong>N10,000</strong> in order to use Buy Now, Pay Later.</div>');
        } else {
            if ($gateway_chosen == 'klump') {
                $newOption->setAdditionalInformation(
                    $this->generateForm()
                );
            }
        }

        return [$newOption];
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
        $merchantPublickey = Configuration::get('KLUMP_MODE')
            ? Configuration::get('KLUMP_TEST_PUBLIC_KEY')
            : Configuration::get('KLUMP_LIVE_PUBLIC_KEY');

        // If no key is set, then stop execution.
        if (empty($merchantPublickey)) {
            return '';
        }

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
        $id_address = Address::getFirstCustomerAddressId($customer->id);

        $params = [
            'merchant_public_key' => $merchantPublickey,
            'merchant_reference' => 'order_' . $cart->id . '_' . time(),
            'amount' => $cart->getOrderTotal(),
            'currency' => $this->default_currency,
            'customer' =>$customer->firstname . ' ' . $customer->lastname,
            'customer_first_name' => $customer->firstname,
            'customer_last_name' => $customer->lastname,
            'customer_email' => $customer->email,
            'items' => json_encode($products, JSON_PRETTY_PRINT),
            'shipping_fee' => $cart->getOrderTotal(true, Cart::ONLY_SHIPPING),
            'gateway_chosen' => 'klump',
            'redirect_url' => $this->context->link->getModuleLink($this->name, 'validation', [], true)
        ];
    
        if ($id_address) {
            $address = new Address($id_address);
            $phone = $address->phone;
            $params['customer_phone'] = $phone;
        }

        $this->context->smarty->assign(
            $params
        );
        return $this->context->smarty->fetch('module:klump/views/templates/front/checkout.tpl');
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
            return '';
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
            $test_public_key = Tools::getValue('KLUMP_TEST_PUBLIC_KEY');
            $test_secret_key = Tools::getValue('KLUMP_TEST_SECRET_KEY');
            $live_public_key = Tools::getValue('KLUMP_LIVE_PUBLIC_KEY');
            $live_secret_key = Tools::getValue('KLUMP_LIVE_SECRET_KEY');
            $enable_test_mode = Tools::getValue('KLUMP_MODE') ? true : false;
            $disable_klump = Tools::getValue('KLUMP_DISABLE') ? false : true;

            // Initialize validation error array
            $errors = [];

            // validate test public key
            if (empty($test_public_key) || preg_match($publicTestKeyRegex, $test_public_key) === 0) {
                $errors[] = $this->trans('Test public key is either empty or invalid. A valid test public keyy should be of the format klp_pk_test_xxxxxxxxxxxxxxx');;
            }

            // validate test secret key
            if (empty($test_secret_key) || preg_match($secretTestKeyRegex, $test_secret_key) === 0) {
                $errors[] = $this->trans('Test secret key is either empty or invalid. A valid test secret key should be of the format klp_sk_test_xxxxxxxxxxxxxxx');
            }

            // validate live public key
            if (empty($live_public_key) || preg_match($publicLiveKeyRegex, $live_public_key) === 0) {
                $errors[] = $this->trans('Live public key is either empty or invalid. A valid live public key should be of the format klp_pk_xxxxxxxxxxxxxxx');
            }

            // validate live secret key
            if (empty($live_secret_key) || preg_match($secretLiveKeyRegex, $live_secret_key) === 0) {
                $errors[] = $this->trans('Live secret key is either empty or invalid. A valid live secret key should be of the format klp_sk_xxxxxxxxxxxxxxx');
            }

            // if error exist, display them
            if (count($errors) > 0) {
                $output .= $this->displayError(implode('<br>', $errors));
            } else {
                // Save configuration values
                Configuration::updateValue('KLUMP_TEST_PUBLIC_KEY', $test_public_key);
                Configuration::updateValue('KLUMP_TEST_SECRET_KEY', $test_secret_key);
                Configuration::updateValue('KLUMP_LIVE_PUBLIC_KEY', $live_public_key);
                Configuration::updateValue('KLUMP_LIVE_SECRET_KEY', $live_secret_key);
                Configuration::updateValue('KLUMP_MODE', $enable_test_mode);
                Configuration::updateValue('KLUMP_DISABLE', $disable_klump);
                $output .= $this->displayConfirmation($this->trans('Settings updated successfully'));
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
        $helper->fields_value['KLUMP_TEST_PUBLIC_KEY'] = Tools::getValue('KLUMP_TEST_PUBLIC_KEY', Configuration::get('KLUMP_TEST_PUBLIC_KEY'));
        $helper->fields_value['KLUMP_TEST_SECRET_KEY'] = Tools::getValue('KLUMP_TEST_SECRET_KEY', Configuration::get('KLUMP_TEST_SECRET_KEY'));

        $helper->fields_value['KLUMP_LIVE_PUBLIC_KEY'] = Tools::getValue('KLUMP_LIVE_PUBLIC_KEY', Configuration::get('KLUMP_LIVE_PUBLIC_KEY'));
        $helper->fields_value['KLUMP_LIVE_SECRET_KEY'] = Tools::getValue('KLUMP_LIVE_SECRET_KEY', Configuration::get('KLUMP_LIVE_SECRET_KEY'));

        $helper->fields_value['KLUMP_MODE'] = Tools::getValue('KLUMP_MODE', Configuration::get('KLUMP_MODE'));

        $disable_klump = Configuration::get('KLUMP_DISABLE') ? false : true;
        $helper->fields_value['KLUMP_DISABLE'] = Tools::getValue('KLUMP_DISABLE', $disable_klump);

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
                        'type' => 'switch',
                        'label' => $this->trans('Mode'),
                        'name' => 'KLUMP_MODE',
                        'is_bool' => true,
                        'required' => true,
                        'desc' => 'Set your integration to either Test or Live. This will allow you to test your Klump BNPL integration without any real payments. Use this during development and testing. Uncheck this box when you are ready to go to production/live',
                         'values' => [
                            [
                                'id' => 'active_on',
                                'value' => false,
                                'label' => $this->trans('Live')
                            ],[
                                'id' => 'active_off',
                                'value' => true,
                                'label' => $this->trans('Test')
                            ]
                        ]
                    ],[
                        'type' => 'text',
                        'label' => $this->trans('Test Public Key'),
                        'name' => 'KLUMP_TEST_PUBLIC_KEY',
                        'size' => 40,
                        'required' => true
                    ],[
                        'type' => 'text',
                        'label' => $this->trans('Test Secret Key'),
                        'name' => 'KLUMP_TEST_SECRET_KEY',
                        'size' => 40,
                        'required' => true
                    ],[
                        'type' => 'text',
                        'label' => $this->trans('Live Public Key'),
                        'name' => 'KLUMP_LIVE_PUBLIC_KEY',
                        'size' => 40,
                        'required' => true
                    ],[
                        'type' => 'text',
                        'label' => $this->trans('Live Secret Key'),
                        'name' => 'KLUMP_LIVE_SECRET_KEY',
                        'size' => 40,
                        'required' => true
                    ],[
                        'type' => 'switch',
                        'label' => $this->trans('Disable Klump on Cart Page'),
                        'name' => 'KLUMP_DISABLE',
                        'is_bool' => true,
                        'required' => true,
                        'desc' => 'This will will remove Klump Buy Now, Pay Later from your checkout page.',
                         'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Disable')
                            ],[
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('Enable')
                            ]
                        ]
                    ]
                ],
                'submit' => [
                    'title' => $this->trans('Save'),
                    'class' => 'btn btn-default pull-right'
                ]
            ]
        ];

        return $helper->generateForm([$fields_form]);
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