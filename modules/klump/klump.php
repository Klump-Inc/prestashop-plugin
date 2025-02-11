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

require_once _PS_MODULE_DIR_ . 'klump/services/KlumpProductSync.php';

// https://devdocs.prestashop-project.org/8/modules/creation/tutorial/
class Klump extends PaymentModule
{
    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->name = 'klump';
        $this->tab = 'payments_gateways';
        $this->version = '0.1.0';
        $this->author = 'Klump Inc.';
        $this->is_eu_compatible = 0;
        // If your module needs to display a warning message in the “Modules” page, then you must set this attribute to 1.
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_
        ];
        $this->bootstrap = true;

        $this->controllers = ['payment', 'validation'];

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
        if (!$id_default_currency = Configuration::get('PS_CURRENCY_DEFAULT')) {
            $this->warning = $this->trans('Default currency not provided.', [], 'Modules.Mymodule.Admin');
        }
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
     * @return boolean
     */
    public function install()
    {
        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('moduleRoutes')
            && $this->registerHook('actionUpdateQuantity')
            && $this->registerHook('actionObjectProductUpdateAfter')
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
     * @return boolean
     */
    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName('KLUMP_NAME')
            && Configuration::deleteByName('KLUMP_TEST_PUBLIC_KEY')
            && Configuration::deleteByName('KLUMP_TEST_SECRET_KEY')
            && Configuration::deleteByName('KLUMP_LIVE_PUBLIC_KEY')
            && Configuration::deleteByName('KLUMP_LIVE_SECRET_KEY')
            && Configuration::deleteByName('KLUMP_MODE')
            && Configuration::deleteByName('KLUMP_DISABLE')
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
     * @param $params
     * @return PaymentOption[]|void
     */
    public function hookPaymentOptions($params)
    {
        /**
         * Check if the module is active and activate Klump BNPL
         * on the store front.
         */
        if (!$this->active) {
            return;
        }

        /**
         * Make sure the plugin can be used by only Nigerian merchants
         * else don't render checkout form
         */
        $currency = $this->context->currency->iso_code;
        $country = $this->context->country->iso_code;
        if ($currency !== 'NGN' || $country !== 'NG') {
            return;
        }

        $config = $this->getConfigFieldsValues();
        if ($config['KLUMP_MODE'] == 1) {
            $merchantPublickey = $config['KLUMP_TEST_PUBLIC_KEY'];
        } else {
            $merchantPublickey = $config['KLUMP_LIVE_PUBLIC_KEY'];
        }

        if ($merchantPublickey == '') {
            return;
        }

        $gateway_chosen = 'none';
        $cart = $this->context->cart;

        if (Tools::getValue('gateway') == 'klump') {
            $gateway_chosen = 'klump';

            // Build products array with images
            $products = [];
            foreach ($cart->getProducts() as $product) {
                $products[] = [
                    'image_url' => $this->context->link->getImageLink($product['link_rewrite'], $product['id_image']),
                    'item_url' => $this->context->link->getProductLink($product['id_product']),
                    'name' => $product['name'],
                    'unit_price' => $product['price'],
                    'quantity' => (int) $product['quantity'],
                ];
            }

            // Get customer information
            $customer = new Customer((int) $cart->id_customer);
            $id_address = Address::getFirstCustomerAddressId($customer->id);
            $address = new Address($id_address);

            $params = [
                'merchant_public_key' => $merchantPublickey,
                'merchant_reference' => 'order_' . $cart->id . '_' . time(),
                'amount' => $cart->getOrderTotal(true, Cart::BOTH),
                'currency' => $this->default_currency,
                'customer' =>$customer->firstname . ' ' . $customer->lastname,
                'customer_first_name' => $customer->firstname,
                'customer_last_name' => $customer->lastname,
                'customer_email' => $customer->email,
                'customer_address' => $address->address1 . ', ' . $address->city ,
                'items' => json_encode($products),
                'shipping_fee' => $cart->getOrderTotal(true, Cart::ONLY_SHIPPING),
                'tax' => $cart->getOrderTotal(true, Cart::BOTH) - $cart->getOrderTotal(false, Cart::BOTH),
                'gateway_chosen' => 'klump',
                'redirect_url' => $this->context->link->getModuleLink($this->name, 'validation', [], true)
            ];

            if ($address->phone) {
                $phone = $address->phone;
                $params['customer_phone'] = $phone;
            }

            $this->context->smarty->assign(
                array(
                    'gateway_chosen' => 'klump',
                    'redirect_url'       => $this->context->link->getModuleLink($this->name, 'klump-status', [], true),
                )
            );

            $this->context->smarty->assign(
                $params
            );
        }

        $newOption = new PaymentOption();

        $newOption->setModuleName($this->name) // Set module name
            ->setCallToActionText($this->trans('Pay with Klump Buy Now, Pay Later ', [], 'Modules.Klump.Shop')) // Set the label or name of the payment method
            ->setAction($this->context->link->getModuleLink($this->name, 'checkout', [], true))
            ->setAdditionalInformation($this->context->smarty->fetch('module:klump/views/templates/hook/intro.tpl'))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/logo.png')) // Set the logo
            ->setInputs([
                'klump_iframe' => [
                    'name' =>'klump_iframe',
                    'type' =>'hidden',
                    'value' =>'1',
                ]
            ]);

        /**
         * This is injected into the form. This way,
         * the user gets redirected automatically if they ever select klump
         */

        // BNPL should only come in when a user has cart size more than N10,000
        if ($cart->getOrderTotal() < 10000) {
            $newOption->setAdditionalInformation('<div class="alert alert-warning">Increase cart total value to at least <strong>N10,000</strong> in order to use Buy Now, Pay Later.</div>');
        } else {
            if ($gateway_chosen == 'klump') {
                $newOption->setAdditionalInformation(
                    $this->context->smarty->fetch('module:klump/views/templates/front/checkout.tpl')
                );
            }
        }

        return [ $newOption ];
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
            return;
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
     * @return string
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
            $isSyncEnabled = Tools::getValue('KLUMP_ENABLE_SYNC', false);

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

            // Check if product sync is enabled but keys are missing
            if ($isSyncEnabled) {
                if ($enable_test_mode) {
                    // Prevent enabling product sync in Test Mode
                    $errors[] = $this->trans('Product sync is only available in Live Mode. Please disable Test Mode to enable product sync.');
                } elseif (empty($live_public_key) || empty($live_secret_key)) {
                    // Ensure Live Mode keys are provided
                    $errors[] = $this->trans('You cannot enable product sync without valid Live Public and Secret Keys.');
                }
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
                Configuration::updateValue('KLUMP_ENABLE_SYNC', $isSyncEnabled);
                $output .= $this->displayConfirmation($this->trans('Settings updated successfully'));
            }
        }

        // Check if "Sync All Products" button is clicked
        if (Tools::isSubmit('sync_all_products')) {
            $this->syncAllProducts();
            $output .= $this->displayConfirmation($this->trans('All products sync is initiated successfully.'));
        }

        // Determine if automatic product sync is enabled
        $isSyncEnabled = KlumpProductSync::isSyncEnabled();

        // Pass variables to the Smarty template
        $this->context->smarty->assign([
            'form_action' => AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
            'is_sync_enabled' => $isSyncEnabled, // Pass sync flag
        ]);

        return $output . $this->renderForm() . $this->context->smarty->fetch($this->local_path . 'views/templates/admin/config.tpl');

//        return $output . $this->renderForm();
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
    public function renderForm()
    {
        $id_default_currency = Configuration::get('PS_CURRENCY_DEFAULT');
        $defaultCurrency = new Currency($id_default_currency);
        $currency = $defaultCurrency->iso_code; // e.g., NGN, USD, EUR

        if ($currency !== 'NGN') {
            return '<div class="alert alert-warning">Please set your default currency to Nigerian Naira(NGN) before you can configure Klump\'s Buy Now, Pay Later</div>';
        }
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
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Test Public Key'),
                        'name' => 'KLUMP_TEST_PUBLIC_KEY',
                        'size' => 40,
                        'required' => true
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Test Secret Key'),
                        'name' => 'KLUMP_TEST_SECRET_KEY',
                        'size' => 40,
                        'required' => true
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Live Public Key'),
                        'name' => 'KLUMP_LIVE_PUBLIC_KEY',
                        'size' => 40,
                        'required' => true
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Live Secret Key'),
                        'name' => 'KLUMP_LIVE_SECRET_KEY',
                        'size' => 40,
                        'required' => true
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Disable Klump on Cart Page'),
                        'name' => 'KLUMP_DISABLE',
                        'is_bool' => true,
                        'required' => true,
                        'desc' => 'This will remove Klump Buy Now, Pay Later from your checkout page.',
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
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Enable Automatic Product Sync', [], 'Modules.Klump.Admin'),
                        'name' => 'KLUMP_ENABLE_SYNC', // Name of the configuration key
                        'is_bool' => true,
                        'required' => true,
                        'desc' => $this->trans(
                            'Enable this option to automatically sync products with the external API on update or purchase.',
                            [],
                            'Modules.Klump.Admin'
                        ),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Enable'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('Disable'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save'),
                    'class' => 'btn btn-default pull-right'
                ]
            ]
        ];
        $fields_form_customization = [];

        $helper = new HelperForm();

        // Set form properties
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->module = $this;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?: 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitKlumpBNPL';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm([$fields_form, $fields_form_customization]);
    }

    public function getConfigFieldsValues()
    {
        return array(
            'KLUMP_TEST_PUBLIC_KEY' => Tools::getValue('KLUMP_TEST_PUBLIC_KEY', Configuration::get('KLUMP_TEST_PUBLIC_KEY')),
            'KLUMP_TEST_SECRET_KEY' => Tools::getValue('KLUMP_TEST_SECRET_KEY', Configuration::get('KLUMP_TEST_SECRET_KEY')),
            'KLUMP_LIVE_PUBLIC_KEY' => Tools::getValue('KLUMP_LIVE_PUBLIC_KEY', Configuration::get('KLUMP_LIVE_PUBLIC_KEY')),
            'KLUMP_LIVE_SECRET_KEY' => Tools::getValue('KLUMP_LIVE_SECRET_KEY', Configuration::get('KLUMP_LIVE_SECRET_KEY')),
            'KLUMP_MODE' => Tools::getValue('KLUMP_MODE', Configuration::get('KLUMP_MODE')),
            'KLUMP_DISABLE' => Tools::getValue('KLUMP_DISABLE', Configuration::get('KLUMP_DISABLE') ? false : true),
            'KLUMP_ENABLE_SYNC' => Tools::getValue('KLUMP_ENABLE_SYNC', Configuration::get('KLUMP_ENABLE_SYNC')),
        );
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

    public function hookActionObjectProductUpdateAfter($params)
    {
        static $syncTracker = [];

        if (!isset($params['object']) || !$params['object'] instanceof Product) {
            return;
        }

        $productId = (int) $params['object']->id;

        // Skip if product was already synced during this request
        if (isset($syncTracker[$productId])) {
            return;
        }

        $syncTracker[$productId] = true;

        KlumpProductSync::syncProductOnUpdate($productId);
    }

    public function hookActionUpdateQuantity($params)
    {
        static $lastSyncedStock = [];

        // Check if the necessary parameters are provided
        if (!isset($params['id_product']) || !isset($params['quantity'])) {
            return;
        }

        $productId = (int) $params['id_product'];
        $newStock = (int) $params['quantity'];

        // Check if the stock has already been updated
        if (isset($lastSyncedStock[$productId]) && $lastSyncedStock[$productId] === $newStock) {
            return;
        }

        $lastSyncedStock[$productId] = $newStock; // Update tracker

        KlumpProductSync::syncProductOnUpdate($productId);
    }

    public function syncAllProducts()
    {
        // Get all product IDs from the database
        $query = new DbQuery();
        $query->select('id_product');
        $query->from('product');
        $productIds = Db::getInstance()->executeS($query);

        $batchSize = 100; // Process 100 products at a time
        $totalProducts = count($productIds);
        $batches = array_chunk($productIds, $batchSize);

        \PrestaShopLogger::addLog('Klump: Starting batch sync for ' . $totalProducts . ' products', 1);

        foreach ($batches as $batchIndex => $batch) {
            $allProductData = [];
            $context = Context::getContext();

            // Prepare data for current batch
            foreach ($batch as $product) {
                $productId = (int)$product['id_product'];
                $product = new Product($productId);
                $variants = $product->getAttributeCombinations($context->language->id);

                // If no variants, add the product itself
                if (empty($variants)) {
                    $allProductData[] = [
                        'name' => $product->name[$context->language->id],
                        'product_id' => $product->id,
                        'variant_id' => null,
                        'variant_name' => null,
                        'is_published' => (bool)$product->active,
                        'price' => (float)$product->price,
                        'old_price' => (float)(isset($product->base_price) ? $product->base_price : $product->price),
                        'description' => $product->description[$context->language->id],
                        'sku' => $product->reference,
                        'image' => $context->link->getImageLink(
                            $product->link_rewrite[$context->language->id],
                            $product->getCover($product->id)['id_image'],
                            'home_default'
                        ),
//                        'sub_category' => KlumpProductSync::getCategoryName($productId),
//                        'category' => KlumpProductSync::getParentCategoryName($productId),
                    ];
                } else {
                    // Add all variants
                    foreach ($variants as $variant) {
                        $allProductData[] = [
                            'name' => $product->name[$context->language->id],
                            'product_id' => $product->id,
                            'variant_id' => $variant['id_product_attribute'],
                            'variant_name' => $variant['attribute_name'],
                            'is_published' => (bool)$product->active,
                            'price' => (float)$variant['price'],
                            'old_price' => null,
                            'description' => $product->description[$context->language->id],
                            'sku' => $variant['reference'] ?: $product->reference,
                            'image' => $context->link->getImageLink(
                                $product->link_rewrite[$context->language->id],
                                $variant['id_image'] ?? $product->getCover($product->id)['id_image'],
                                'home_default'
                            ),
//                            'sub_category' => KlumpProductSync::getCategoryName($productId),
//                            'category' => KlumpProductSync::getParentCategoryName($productId),
                        ];
                    }
                }
            }

            // Sync current batch
            $sync = new KlumpProductSync();
            $sync->syncProducts($allProductData);

            \PrestaShopLogger::addLog(
                sprintf(
                    'Klump: Processed batch %d/%d with %d products/variants',
                    $batchIndex + 1,
                    ceil($totalProducts / $batchSize),
                    count($allProductData)
                ),
                1
            );

            // Add a small delay between batches to prevent overwhelming the API
            usleep(500000); // 0.5 second delay
        }

        \PrestaShopLogger::addLog('Klump: Completed batch sync of all products', 1);
    }
}
