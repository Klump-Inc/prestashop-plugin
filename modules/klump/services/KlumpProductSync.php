<?php

class KlumpProductSync
{
    /**
     * API URL to sync products
     */
//    private const SYNC_URL = 'https://api.klump.com/products/sync';
    private const SYNC_URL = 'https://rarely-in-sunbeam.ngrok-free.app/v1/products/sync';

    /**
     * Get the sub-category name of a product.
     *
     * @param int $productId
     * @return string|null
     */
    private static function getCategoryName($productId)
    {
        $categoryIds = Product::getProductCategories($productId);

        if (!empty($categoryIds)) {
            $category = new Category(end($categoryIds)); // Get last category (sub-category)
            return $category->name[Context::getContext()->language->id] ?? null;
        }

        return null;
    }

    /**
     * Get the parent category name of a product.
     *
     * @param int $productId
     * @return string|null
     */
    private static function getParentCategoryName($productId)
    {
        $categoryIds = Product::getProductCategories($productId);

        if (!empty($categoryIds)) {
            $category = new Category(reset($categoryIds)); // Get first category (parent category)
            return $category->name[Context::getContext()->language->id] ?? null;
        }

        return null;
    }

    /**
     * Synchronize product with the external API on product update.
     *
     * @param int $productId
     */
    public static function syncProductOnUpdate($productId)
    {
        \PrestaShopLogger::addLog('Klump: Starting sync for product ID: ' . $productId, 1);

        $product = new \Product($productId);
        $context = \Context::getContext();

        $variants = $product->getAttributeCombinations($context->language->id);

        $productData = [];

        // If no variants, add the product itself
        if (empty($variants)) {
            $productData[] = [
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
                'sub_category' => self::getCategoryName($productId),
                'category' => self::getParentCategoryName($productId),
            ];
        } else {
            // Add variants
            foreach ($variants as $variant) {
                $productData[] = [
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
                    'sub_category' => self::getCategoryName($productId),
                    'category' => self::getParentCategoryName($productId),
                ];
            }
        }

        // Sync products or variants using API
        $sync = new self(); // Create instance
        $sync->syncProducts($productData);
    }

    /**
     * Synchronize product or variant data for all items in an order when the order status changes.
     *
     * @param int $orderId
     */
    public static function syncProductsOnOrderStatusUpdate($orderId)
    {
        $order = new Order($orderId);
        $products = $order->getProducts(); // Get all products in the order
        $context = Context::getContext();

        $productData = []; // Initialize payload array

        foreach ($products as $productItem) {
            // Load the product data
            $product = new Product($productItem['product_id']);
            $variants = $product->getAttributeCombinations($context->language->id);

            if (!empty($variants) && isset($productItem['product_attribute_id']) && $productItem['product_attribute_id'] != 0) {
                // If a specific variant was purchased, sync that variant only
                $variantId = $productItem['product_attribute_id'];
                foreach ($variants as $variant) {
                    if ($variant['id_product_attribute'] == $variantId) {
                        $productData[] = [
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
                            'sub_category' => self::getCategoryName($product->id),
                            'category' => self::getParentCategoryName($product->id),
                        ];
                        break; // Stop here since the variant has been processed
                    }
                }
            } else {
                // Sync the entire product if no variants are applicable
                $productData[] = [
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
                    'sub_category' => self::getCategoryName($product->id),
                    'category' => self::getParentCategoryName($product->id),
                ];
            }
        }

        // Sync products or variants using API
        $sync = new self();
        $sync->syncProducts($productData);
    }

    /**
     * Sync products to the external API.
     *
     * @param array $productData Array of products to sync
     */
    public function syncProducts(array $productData): void
    {
        // Check if syncing is enabled
        if (!$this->isSyncEnabled()) {
            return;
        }

        // Retrieve credentials from configuration
        $secretKey = \Configuration::get('KLUMP_LIVE_SECRET_KEY');
        $publicKey = \Configuration::get('KLUMP_LIVE_PUBLIC_KEY');

        if (empty($secretKey) || empty($publicKey)) {
            \PrestaShopLogger::addLog('Klump: Credentials for product sync not set', 3); // Log level: ERROR
            return;
        }

        try {
            // Generate HMAC signature
            $signature = hash_hmac('sha512', json_encode($productData), $secretKey);

            // Initialize cURL for API request
            $curl = curl_init(self::SYNC_URL);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($productData)); // Send JSON payload
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Klump-Signature: ' . $signature,        // HMAC signature
                'X-Klump-Public-Key: ' . $publicKey,      // Public key
                'X-Plugin-Type: PrestaShop',             // Plugin type
            ]);

            $response = curl_exec($curl); // Execute cURL request
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE); // Get response HTTP code
            curl_close($curl);

            // Handle response
            if ($httpCode >= 200 && $httpCode < 300) {
                \PrestaShopLogger::addLog('Klump: Product(s) synced successfully: ' . count($productData), 1); // Log level: INFO
            } else {
                \PrestaShopLogger::addLog('Klump: Product sync failed with HTTP code ' . $httpCode . '. Response: ' . $response, 3); // Log level: ERROR
            }

        } catch (\Exception $e) {
            // Log any exceptions during sync
            \PrestaShopLogger::addLog('Klump: Product sync failed: ' . $e->getMessage(), 3); // Log level: ERROR
        }
    }

    /**
     * Check if the product sync is enabled.
     *
     * @return bool
     */
    private function isSyncEnabled(): bool
    {
        return (bool)\Configuration::get('KLUMP_ENABLE_SYNC');
    }
}
