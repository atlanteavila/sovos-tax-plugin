<?php

namespace Sovos;

class SovosIntegration
{
    private Client $client;

    protected array $request;

    /** @var \stdClass|null|\Sovos\Models\SovosResponse */
    protected $response;

    protected array $shippingRequest = [];

    protected string $company;

    protected float $shippingCost = 0;

    protected float $shippingFee = 0;

    protected float $productsTax = 0;

    protected float $productsTotal = 0;

    protected float $discounts = 0;
    protected bool $isAudit = false;

    protected bool $isReversal = false;

    protected ?bool $isEbayOrder;

    protected bool $debug;

    protected string $website;

    protected ?string $newLine = null;

    protected array $debugMessages = [];

    protected ?\stdClass $shippingResponse = null;

    protected string $shipmentInformationAt = 'header';

    protected ?string $txwTrnDocId = null;

    protected ?array $statesWithAutomaticRetailDeliveryFees;

    protected bool $isTaxExempt = false;

    /**
     * The Org Codes are:
     * BGS - Buy Gold and Silver Corp
     * BX - Bullion Max
     * CM - CyberMetals
     * JM - JMB
     * PM - Provident Metals Corp
     * SL - Silver.com Inc
     */
    protected const COMPANIES = [
        "BGS", "BX", "CM", "JM", "PM", "SL",
        "BGS-T", "BX-T", "CM-T", "JM-T", "PM-T", "SL-T"
    ];

    protected ?string $state = null;

    protected ?\StateFee $stateFee = null;

    protected string $city;

    protected string $zipCode;

    protected string $street;

    protected ?int $order_id = null;

    protected ?int $customer_id = null;

    protected ?string $order_date = null;

    protected static ?\wpdb $connection = null;

    protected static ?\wpdb $jmconnection = null;

    protected ?float $automaticDeliveryFee = null;

    protected array $lineItems = [];

    protected array $statesWithTenderRules = [];

    protected static array $columnsMap = [
        'provident' => 'provident_id',
        'bgasc' => 'bg_id',
        'bmx' => 'bmx_id',
        'silver' => 'silver_id'
    ];

    protected bool $isCyberMetalOrder = false;

    protected static function connection(): \wpdb
    {
        if (is_null(static::$connection)) {
            global $wpdb;
            static::$connection = $wpdb;
        }

        return static::$connection;
    }

    protected static function jmconnection(): \wpdb
    {
        if (is_null(static::$jmconnection)) {
            global $wpdb;
            static::$jmconnection = (defined('JMSITE') && JMSITE === 'jmbullion') ? $wpdb : jmwpconnect();
        }

        return static::$jmconnection;
    }

    public function getTxwTrnDocId(): ?string
    {
        return $this->txwTrnDocId;
    }

    public function getStateFee(): ?\StateFee
    {
        return $this->stateFee;
    }

    public function getIsAudit(): bool
    {
        return $this->isAudit;
    }

    public function getIsTaxExempt(): bool
    {
        return $this->isTaxExempt;
    }

    public function setIsCyberMetalOrder(bool $isCyberMetalOrder): SovosIntegration
    {
        $this->isCyberMetalOrder = $isCyberMetalOrder;
        return $this;
    }

    public function getIsCyberMetalOrder(): bool
    {
        return $this->isCyberMetalOrder;
    }

    public function setOrderId(int $order_id): SovosIntegration
    {
        $this->order_id = $order_id;
        return $this;
    }

    public function getOrderId(): ?int
    {
        return $this->order_id;
    }

    public function setCustomerId(int $customer_id): SovosIntegration
    {
        $this->customer_id = $customer_id;
        return $this;
    }

    public function getCustomerId(): ?int
    {
        if (!is_null($this->customer_id)) {
            return $this->customer_id;
        }

        if (is_null($this->order_id)) {
            return null;
        }

        $customer_id =
            self::connection()
                ->get_var(
                    self::connection()->prepare("SELECT `meta_value` AS `customer_id` FROM `wp_postmeta` WHERE `post_id` = %d AND `meta_key` = %s",
                        $this->order_id,
                        '_customer_user')
                );

        if (is_null($customer_id)) {
            return null;
        }

        return $this->customer_id = (int)$customer_id;
    }

    /**
     *
     * @param string $mode Accepts 'line' and 'header'
     * @return $this
     * @throws \Exception
     */
    public function setShipmentInformationUsing(string $mode): SovosIntegration
    {
        $availableModes = ['line', 'header'];
        $mode = strtolower($mode);
        if (!in_array($mode, $availableModes)) {
            throw new \Exception("Please select a valid mode, currently modes supported: " . implode(', ', $availableModes));
        }

        $this->shipmentInformationAt = $mode;

        return $this;
    }

    public function when($value, $callback, $default = null): SovosIntegration
    {
        if ($value) {
            return $callback($this, $value) ?: $this;
        } elseif ($default) {
            return $default($this, $value) ?: $this;
        }

        return $this;
    }

    public function isTaxExempt(bool $isTaxExempt): SovosIntegration
    {
        $this->isTaxExempt = $isTaxExempt;
        return $this;
    }

    public function isAudit(bool $isAudit): SovosIntegration
    {
        $this->isAudit = $isAudit;
        return $this;
    }

    public function isReversal(bool $reversal): SovosIntegration
    {
        $this->isReversal = $reversal;
        return $this;
    }

    public function getIsReversal(): bool
    {
        return $this->isReversal;
    }

    public function discounts(float $discounts = 0): SovosIntegration
    {
        $this->discounts = $discounts;
        return $this;
    }

    public function shippingCost(float $cost = 0): SovosIntegration
    {
        $this->shippingCost = $cost;
        return $this;
    }

    public function getShippingRequest(): array
    {
        return $this->shippingRequest;
    }

    public function getShippingResponse(): ?\stdClass
    {
        return $this->shippingResponse;
    }

    public function getDebugMessages(): array
    {
        return $this->debugMessages;
    }

    public function isRequestTypeHeader(): bool
    {
        return $this->shipmentInformationAt === 'header';
    }

    public function isRequestTypeLine(): bool
    {
        return $this->shipmentInformationAt === 'line';
    }

    public function getProductIdBySite(int $product_id): int
    {
        if (JMSITE === 'jmbullion') {
            return $product_id;
        }

        if (!array_key_exists(JMSITE, self::$columnsMap)) {
            throw new \Exception('Invalid JMSITE provided');
        }

        $column = self::$columnsMap[JMSITE];

        return (int)self::jmconnection()
            ->get_var("SELECT `$column` FROM `wp_jm_productmeta` WHERE `product_id` = {$product_id}");
    }

    /**
     * Helper function to return a desired array with products and his taxes as the one used by
     * `transformSalesTax` function
     */
    public function getLineItems(): array
    {
        return $this->lineItems;
    }

    /**
     * Check if JMB-10633 for Colorado state was applied
     *
     * @return bool
     */
    public function hasAutomaticDeliveryFeeBeenRemoved(): bool
    {
        return !is_null($this->automaticDeliveryFee);
    }

    public function getRemovedAutomaticDeliveryFee(): float
    {
        return $this->hasAutomaticDeliveryFeeBeenRemoved() ? $this->automaticDeliveryFee : 0;
    }

    public function getProductsTotal(): float
    {
        return $this->productsTotal;
    }

    public function statesWithTenderRules(): array
    {
        return $this->statesWithTenderRules;
    }

    protected function shipFrom(): array
    {
        // Ship From
        return [
            'sFCity' => 'Las Vegas',
            'sFStateProv' => 'NV',
            'sFPstlCd' => '89119',
            'sFStNameNum' => '5757 Wayne Newton Blvd',
            'sFCountry' => "United States",
        ];
    }

    protected function buildProductLinePayload(float $total, int $sovosCode): array
    {
        // JMB-10640 Sovos - Order Reversals
        //
        // Everything in a purchase call is the same in the reversal call.
        // You would just put a - in front of the gross amount. It would be in your best interest to send in
        // Original document date, and original document number if your reversals will come in as their own invoice number.

        if ($this->isReversal && $total > 0) {
            $total *= -1;
        }

        $this->productsTotal = bcadd($this->productsTotal, $total, 4);

        $payload = [
            'orgCd' => $this->company,
            'goodSrvCd' => $sovosCode,
            'grossAmt' => $total,
            'sTCity' => $this->city,
            'sTStateProv' => $this->state,
            'sTPstlCd' => $this->zipCode,
            'sTStNameNum' => $this->street,
            'sTCountry' => "United States",
        ];

        /** The field is custVendCd passed at the line level. You absolutely can send the customer code on every
         * transaction is you want. Exemptions will only apply if there is an active exemption on file for the
         * particular ship to state.  Otherwise you can choose to pass it only when you want a exemption to be
         * considered on the transaction. This again though would only apply if the exemption is current
         * (not expired) or on file.
         */

        // Skipping Guests Orders
        if (!is_null($this->customer_id) && $this->customer_id !== 0) {
            $payload['custVendCd'] = $this->customer_id;
        }

        // JMB-10940
        // Customer number should be passed at the line level in the field `custVendCd` you can also pass the
        // `custVendName` (Limit 40 Characters)
        if ($this->isTaxExempt) {

            $payload = array_merge($payload, [
                'custVendCd' => is_null($this->customer_id) ? 1 : $this->customer_id,
                'custVendName' => 'Tax Exempt',
            ]);

        }

        return array_merge($this->shipFrom(), $payload);
    }

    public function statesWithAutomaticRetailDeliveryFees(): ?array
    {
        return $this->statesWithAutomaticRetailDeliveryFees;
    }


    public function getProductsTax(): float
    {
        return (float) $this->productsTax;
    }

    public function getOriginalSovosProductsTax(): float
    {
        if (!is_object($this->response) && property_exists($this->response, 'originalSovosProductsTax')) {
            return (float) $this->response->originalSovosProductsTax;
        }

        return 0;
    }

    public function getTotalTax(): float
    {
        return $this->getProductsTax() + $this->getAutomaticDeliveryFee();
    }

    public function getAutomaticDeliveryFee(): float
    {
        return (float) $this->automaticDeliveryFee;
    }

    public function getResponse(): ?\stdClass
    {
        return $this->response;
    }

    public function getRequest(): array
    {
        return $this->request ?? [];
    }

    public function getCompany(): string
    {
        return $this->company;
    }

    /**
     * @throws \Exception
     */
    public function __construct(string $company = null, bool $debug = true)
    {

        $this->debug = $debug;
        $this->newLine = (PHP_SAPI === "cli") ? PHP_EOL : "<br />";

        if (!defined('SOVOS_COMPANY')
            || !defined('SOVOS_USERNAME')
            || !defined('SOVOS_PASSWORD')
            || !defined('SOVOS_HMAC_KEY')) {
            throw new \Exception('Sovos API: Missing credentials. Please ensure that all remote credentials are properly configured');
        }

        if (!in_array(SOVOS_COMPANY, self::COMPANIES)) {
            throw new \Exception("Sovos API: Company not supported: " . SOVOS_COMPANY);
        }

        // JMB-10838: This line its only useful for testing purposes via /admin/sovos-manual-test.php
        if (!is_null($company) && in_array($company, self::COMPANIES)) {
            $this->company = $company;
        } else {
            $this->company = SOVOS_COMPANY;
        }

        $this->client = new Client(SOVOS_USERNAME, SOVOS_PASSWORD, SOVOS_HMAC_KEY);

        // Since each state handles a different name for this fee, we'll support it manually

        $this->statesWithAutomaticRetailDeliveryFees = [
            'CO' => ['Retail Delivery Fees'],
            'MN' => ['Road Improvement and Food Delivery Fee'],
        ];

        $statesWithTenderRules = self::jmconnection()
            ->get_results("SELECT DISTINCT(`state`) FROM `sovos_state_tender_rules` ORDER BY `state`", ARRAY_N);

        $this->statesWithTenderRules =
            !is_null($statesWithTenderRules) ?
                array_column($statesWithTenderRules, 0) :
                [];
    }

    /**
     * @param string $state
     * @return $this
     * @throws \Exception
     */
    public function state(string $state): SovosIntegration
    {

        $stateResult = self::jmconnection()
            ->get_row(
                self::jmconnection()->prepare(
                    "SELECT `state`, `state_name` FROM `tax_state` WHERE `state` = %s OR `state_name` = %s",
                    $state,
                    $state,
                )
            );

        if (is_null($stateResult)) {
            throw new \Exception("Invalid state information provided: {$state}");
        }

        $this->state = $stateResult->state;

        return $this;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function isEbayOrder(): bool
    {

        // Currently supported sites
        if (!in_array(JMSITE, ['jmbullion', 'bgasc'])) {
            return $this->isEbayOrder = false;
        }

        if(is_null($this->order_id)) {
            return false;
        }

        if (!is_null($this->isEbayOrder)) {
            return $this->isEbayOrder;
        }

        $order_type =
            self::connection()
                ->get_var(
                    self::connection()
                        ->prepare("SELECT `order_type` FROM `wp_jm_ordermeta` WHERE `order_id` = %d",
                            $this->order_id
                        )
                );


        if (is_null($order_type)) {
            $this->isEbayOrder = false;
        } else {
            $this->isEbayOrder = $order_type === 'ebay';
        }

        return $this->isEbayOrder;
    }

    /**
     * Since Digital Orders doesn't have any implementation of WC_Order, we need to mimic the internal functionalities
     * from the original code with this protected function
     *
     * @param int $order_id
     * @return array
     */
    protected function prepareSalesTaxDataForDigitalOrder(int $order_id): array
    {
        $salesTaxData = [];

        $order = self::jmconnection()
            ->get_row("SELECT *, DATE(`date_added`) AS `date_formatted` FROM `cm_digital_order` WHERE `cm_id` = {$order_id}");

        if($order->date_formatted === '0000-00-00') {
            $order->date_formatted = date('Y-m-d');
        }

        $salesTaxData['customer_id'] = null;

        $orderDiscount = floatval(str_replace(',', '', $order->discount));
        $orderTotal = floatval($order->total);

        $salesTaxData['order_details'] = [
            'order_id' => $order_id,
            'order_type' => 'digital',
            'order_date' => $order->date_formatted,
            'order_total' => $orderTotal,
            'shipping_total' => 0,
            'discount_total' => $orderDiscount
        ];

        $products = json_decode($order->product_json);

        foreach ($products as $product) {

            $salesTaxData['order_details']['order_items'][] = [
                'product_id' => $product->id,
                'quantity' => $product->qty,
                'line_total' => $product->line_item_total,
            ];

        }

        $salesTaxData['destination_address'] = [
            'address_1' => $order->street_address1,
            'city' => $order->city,
            'state' => $order->state,
            'postal_code' => $order->zip
        ];

        return $salesTaxData;
    }

    /**
     * Implementation to support Digital Orders into Sovos at OneCodeBase
     * Used by `/ato/sovos-tax-push-cm-digital.php`
     *
     * @param int $order_id
     * @return $this|SovosIntegration
     * @throws \Exception
     */
    public function calculateDigitalOrderTax(int $order_id): SovosIntegration
    {
        $salesTaxData = $this->prepareSalesTaxDataForDigitalOrder($order_id);

        $this->setIsCyberMetalOrder(true);

        return $this->calculateTax($salesTaxData, $order_id);
    }

    /**
     * Function used to push and finalize an order with Sovos, used by `/ato/sovos-tax-push.php` and
     * `/ato/sovos-tax-push-cm.php`
     *
     * @param \WC_Order $order
     * @param string $ship_date
     * @return $this
     * @throws \Exception
     */
    public function calculateOrderTax(\WC_Order $order, string $ship_date): SovosIntegration
    {
        $salesTaxData = prepareSalesTaxData($order);

        // When calculating order taxes according to Sovos, we need to use `ship_date` instead of the regular `order_date`
        // as the final date
        $salesTaxData['order_details']['order_date'] = $ship_date;

        return $this->calculateTax($salesTaxData, $order->get_id());
    }

    /**
     *  Refund order, by default, any refunds will be tagged with today’s date if no refund date or an empty refund
     * date is provided. Used by `/ato/sovos-tax-push-cm-digital.php`
     *
     * @param int $order_id
     * @param null|string $refundDate
     * @return $this
     *
     * @throws \Exception
     */
    public function refundDigitalOrder(int $order_id, ?string $refundDate = null): SovosIntegration
    {
        $salesTaxData = $this->prepareSalesTaxDataForDigitalOrder($order_id);

        if (!$this->isReversal) {
            $this->isReversal(true);
        }

        if (is_null($refundDate) || preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $refundDate) !== 1) {
            $refundDate = date("Y-m-d");
        }

        $salesTaxData['order_details']['order_date'] = $refundDate;

        $this->setIsCyberMetalOrder(true);

        return $this->calculateTax($salesTaxData, $order_id);
    }

    /**
     *  Refund order, by default, any refunds will be tagged with today’s date if no refund date or an empty refund
     * date is provided. Used by `/ato/sovos-tax-push.php` and `/ato/sovos-tax-push-cm.php`
     *
     * @param \WC_Order $order
     * @param null|string $refundDate
     * @return $this
     *
     * @throws \Exception
     */
    public function refundOrder(\WC_Order $order, ?string $refundDate = null): SovosIntegration
    {
        $salesTaxData = prepareSalesTaxData($order);

        if (!$this->isReversal) {
            $this->isReversal(true);
        }

        if (is_null($refundDate) || preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $refundDate) !== 1) {
            $refundDate = date("Y-m-d");
        }

        $salesTaxData['order_details']['order_date'] = $refundDate;

        return $this->calculateTax($salesTaxData, $order->get_id());
    }

    /**
     * @param array $salesTaxData
     * @param null|int $order_id
     * @param string|null $state
     * @return $this
     * @throws \Exception
     */
    public function calculateTax(array $salesTaxData, ?int $order_id = null, ?string $state = null): SovosIntegration
    {

        $this->order_id = $order_id;

        if (is_null($state)
            && !array_key_exists('destination_address', $salesTaxData)
            && !array_key_exists('state', $salesTaxData['destination_address'])) {
            throw new \Exception("Missing state");
        }

        if (is_null($state)) {
            $state = $salesTaxData['destination_address']['state'] ?? '';
        }

        // New method with extra checks to avoid incorrect state assignment
        $this->state($state);

        // Reset eBay status
        $this->isEbayOrder = null;

        if ($this->isEbayOrder()) {
            // Skipping report ebay orders with Sovos
            $this->debug('eBay order detected, skipping', $this->order_id ?? 'order');
            return $this;
        }

        // Once we finish an order, the date should be the shipping date instead of the natural order_date, in
        // `refundOrder` and `calculateOrderTax` methods, we're manually overriding this value inside $salesTaxData
        $this->order_date = $salesTaxData['order_details']['order_date'] ?? date('Y-m-d');

        $this->discounts = abs((float)$salesTaxData['order_details']['discount_total']);
        $this->shippingCost = abs((float)$salesTaxData['order_details']['shipping_total']);

        $this->city = $salesTaxData['destination_address']['city'];
        $this->zipCode = $salesTaxData['destination_address']['zip'] ?? $salesTaxData['destination_address']['postal_code'] ?? '';
        $this->street = $salesTaxData['destination_address']['street'] ?? $salesTaxData['destination_address']['address_1'] ?? '';

        if (!is_null($this->order_id) && $this->order_id > 0) {

            $customer_id =
                (int)self::connection()
                    ->get_var(
                        self::connection()->prepare("SELECT `meta_value` AS `customer_id` FROM `wp_postmeta` WHERE `post_id` = %d AND `meta_key` = %s",
                            $this->order_id,
                            '_customer_user')
                    );

            $this->setCustomerId($customer_id);

        } elseif (array_key_exists('customer_id', $salesTaxData)) {
            $this->setCustomerId((int)$salesTaxData['customer_id']);
        } else {
            $this->setCustomerId(get_current_user_id());
        }

        $this->isOrderClientTaxExempt();

        if ($this->shouldBypassSovosRequest($salesTaxData)) {
            $this->hydrateZeroTaxResponse($salesTaxData);

            return $this;
        }

        $this->request($salesTaxData);
        $this->processResponse();

        return $this;
    }

    public function isOrderClientTaxExempt(): bool
    {
        if (is_null($this->order_id)) {
            return false;
        }

        $cert =
            self::connection()->get_row(
                self::connection()->prepare(
                    "SELECT * FROM `reseller` WHERE `customer_id` = %d AND `state` = %s AND `cert_expires` >= NOW()",
                    $this->customer_id,
                    $this->state
                )
            );

        $this->isTaxExempt = !empty($cert);
        return $this->isTaxExempt;
    }

    /**
     * Decide whether we can skip making a Sovos API request to reduce transaction volume.
     *
     * @param array $salesTaxData
     */
    protected function shouldBypassSovosRequest(array $salesTaxData): bool
    {
        // Skip if the customer is recognized as a wholesaler/reseller.
        if ($this->isWholesaleCustomer()) {
            $this->debug('Wholesale customer detected, skipping Sovos call.', 'bypass');

            return true;
        }

        // Skip if the order is covered by a valid tax exemption certificate.
        if ($this->isTaxExempt) {
            $this->debug('Tax exempt order detected, skipping Sovos call.', 'bypass');

            return true;
        }

        // Skip when all products involved are marked tax exempt in WooCommerce.
        if ($this->areAllProductsTaxExempt($salesTaxData)) {
            $this->debug('All products are tax exempt, skipping Sovos call.', 'bypass');

            return true;
        }

        return false;
    }

    /**
     * Determine whether the current customer should be treated as a wholesaler and avoid Sovos calls.
     */
    protected function isWholesaleCustomer(): bool
    {
        if (is_null($this->customer_id) || $this->customer_id === 0) {
            return false;
        }

        $user = get_userdata($this->customer_id);

        if ($user === false) {
            return false;
        }

        $roles = $user->roles ?? [];

        // Common wholesale roles used by WooCommerce wholesale extensions.
        $wholesaleRoles = ['wholesale_customer', 'wholesaler', 'b2b'];

        $isWholesale = (bool) array_intersect($wholesaleRoles, $roles);

        /**
         * Filter to allow custom logic for wholesale detection.
         *
         * @param bool   $isWholesale
         * @param int    $customer_id
         * @param array  $roles
         */
        return (bool) apply_filters('sovos_integration_is_wholesale_customer', $isWholesale, $this->customer_id, $roles);
    }

    /**
     * Check whether every product in the request is already marked tax exempt.
     *
     * @param array $salesTaxData
     */
    protected function areAllProductsTaxExempt(array $salesTaxData): bool
    {
        $items = $salesTaxData['order_details']['order_items'] ?? [];

        if (empty($items)) {
            return false;
        }

        $allExempt = true;

        foreach ($items as $item) {
            $product_id = $item['product_id'] ?? null;

            if (is_null($product_id)) {
                $allExempt = false;
                break;
            }

            $isExempt = false;

            if (function_exists('wc_get_product')) {
                $product = wc_get_product($product_id);
                if ($product instanceof \WC_Product) {
                    $isExempt = $product->get_tax_status() === 'none';
                }
            }

            /**
             * Filter to override the tax-exempt status check per product.
             *
             * @param bool $isExempt   Current exemption status.
             * @param int  $product_id Product being evaluated.
             */
            $isExempt = (bool) apply_filters('sovos_integration_is_tax_exempt_product', $isExempt, $product_id);

            if (!$isExempt) {
                $allExempt = false;
                break;
            }
        }

        return $allExempt;
    }

    /**
     * Populate an empty response for bypassed requests so downstream consumers have consistent data.
     *
     * @param array $salesTaxData
     */
    protected function hydrateZeroTaxResponse(array $salesTaxData): void
    {
        $this->productsTax = 0;
        $this->automaticDeliveryFee = 0;
        $this->shippingFee = 0;

        $lineResults = [];
        $this->lineItems = [];

        foreach ($salesTaxData['order_details']['order_items'] ?? [] as $index => $item) {
            $grossAmt = (float) ($item['line_total'] ?? 0);

            $lineResults[$index] = (object) [
                'lnNm' => $index + 1,
                'txAmt' => 0,
                'grossAmt' => $grossAmt,
                'jurRslts' => [],
            ];

            $this->lineItems[] = (object) [
                'id' => $this->getProductIdBySite($item['product_id'] ?? null),
                'product_id' => $item['product_id'] ?? null,
                'taxable_amount' => $grossAmt,
                'tax_collectable' => 0,
            ];
        }

        $this->response = (object) [
            'txAmt' => 0,
            'lnRslts' => $lineResults,
            'txwTrnDocId' => null,
        ];
    }

    /**
     * @param array $salesTaxData
     * @return void
     * @throws \Exception
     */
    protected function request(array $salesTaxData): void
    {

        global $woocommerce;

        // Global_Tax_Determination_REST_API_Developer_Guide available on ticket JMB-8946
        // https://jmbdev.atlassian.net/browse/JMB-8946

        // 'custAttrbs'
        // Global_Tax_Determination_REST_API_Developer_Guide, page 36, 46, 151 of the rest api guide
        //
        // Current supported keys inside 'custAttrbs':
        // 'SPOTPRICE' = true/false, used to determine if a product is a "high premium" based on the rules in New York
        //               (and any other States)
        //
        // 'isAudit' = true when you want to commit the data to the database to use for reporting you will change
        // 'isAudit' = false when you only are making quote calls.
        //
        // Shipping cost it's handled via 'dlvrAmt'
        // Global_Tax_Determination_REST_API_Developer_Guide, page 37, 155 and 158

        // Discounts are handled via 'discnts'
        // Global_Tax_Determination_REST_API_Developer_Guide, page 38, 49,

        $request = [
            'isAudit' => $this->isAudit,
            'currn' => "USD",
            // Items
            'lines' => [],
        ];

        if ($this->order_id > 0) {
            $request['trnDocNum'] = $this->order_id;
        }

        if (!is_null($this->order_date)) {
            $request['docDt'] = $this->order_date;
        }

        // 'discntTpCd' values, the key used on 'discnts'
        // 1  => Discount
        // 2  => Prompt Payment
        // 3  => Quantity Discounts
        // 4  => Retailer Coupon
        // 5  => Manufacturer Coupon
        // 6  => Rebates
        // 7  => Trade-ins
        // 8  => Reward Program / Retailer Coupon
        // 9  => Reward Program / Manufacturer Promotions
        // 10 => Reward Program / Credit Card Company
        // 11 => Gift Voucher

        if ($this->discounts > 0) {
            $request['discnts'] = [
                1 => $this->discounts
            ];
        }

        $this->lineItems = [];
        $this->productsTotal = 0;

        foreach ($salesTaxData['order_details']['order_items'] ?? [] as $item) {

            $product_id = $item['product_id'] ?? null;

            // Note: Only for testing environments we've added support to pass 'manuallyAssignedSovosCode' attribute, this will only
            //       be used for testing purposes at /admin/sovos-manual-test.php and /admin/funcs/sovos-manual-test-ajax.php

            $payload = $this->buildProductLinePayload(
                (float)$item['line_total'],
                array_key_exists('manuallyAssignedSovosCode', $item)
                    ? $item['manuallyAssignedSovosCode']
                    : $this->getSovosIdFromProductId($product_id)
            );

            // Since we can use an empty $order_id for testing we're going skip this section when $order_id = 0
            if ((
                    !is_null($this->order_id) // Stored order
                    || (isset($woocommerce) && $woocommerce instanceof \WooCommerce) // Regular user session
                ) && $product_id !== null) {
                // Check custom attributes

                $customAttributes = $this->getCustomAttributes($product_id, (int)$item['quantity'], (float)$item['line_total']);

                if (!empty($customAttributes)) {
                    $payload['custAttrbs'] = $customAttributes;
                }
            }

            $request['lines'][] = $payload;

            // I'm adding internally the functionality of `transformSalesTax` function
            $this->lineItems[] = (object)[
                'id' => $this->getProductIdBySite($product_id),
                'product_id' => $product_id,
                'taxable_amount' => 0,
                'tax_collectable' => 0,
            ];

        }

        /* Add PAYMENT_METHOD custAttrb*/
        $payType = $salesTaxData['order_details']['payment_type'] ?? null;
        if (!empty($payType)) {
            $request['custAttrbs'] = [
                'PAYMENT_METHOD' => $payType,
            ];
        }

        // Shipping costs
        if ($this->shippingCost > 0) {

            if ($this->isRequestTypeHeader()) {

                // Set shipping costs at header level
                $request['dlvrAmt'] = $this->shippingCost;

            }
        }

        $this->request = $request;

        $this->sovosRequest();

    }

    protected function sovosRequest(): void
    {
        $this->response = $this->client->request("calcTax/doc", $this->request);
    }

    protected function rearrangeProperties($originalObject, $newOrder): \stdClass
    {
        $newObject = new \stdClass();

        foreach ($newOrder as $property) {
            if (property_exists($originalObject, $property)) {
                $newObject->$property = $originalObject->$property;
            }
        }

        // Optionally, copy any remaining properties not in the new order
        foreach ($originalObject as $property => $value) {
            if (!property_exists($newObject, $property)) {
                $newObject->$property = $value;
            }
        }

        return $newObject;
    }

    /**
     * Check if the current state used could contain an automatic state delivery fee, in that case we'll need to recalculate
     * the order tax and the tax proportion for that particular product in product line
     */
    public function hasAutomaticDeliveryFee(): bool {
        return array_key_exists($this->state, $this->statesWithAutomaticRetailDeliveryFees);
    }

    /**
     * In this function, we primarily extract the tax applied to each product in the product line. Additionally, we find,
     * extract, and proportionally recalculate the delivery fee applied to each specific product. Finally, we recalculate
     * the total product tax applied to the order.
     *
     * It's important to note that the delivery fee is currently applied only in Colorado and Minnesota but may be used
     * in more states in the future. These states, along with their tax descriptions, are listed inside the
     * `statesWithAutomaticRetailDeliveryFees` property.
     */
    protected function cleanupAutomaticResponseDeliveryFees(): void
    {

        foreach ($this->response->lnRslts as $productIndex => $lineResult) {

            if (!property_exists($lineResult, 'txAmt')
                || !property_exists($lineResult, 'jurRslts')
                || !is_array($lineResult->jurRslts)) {
                continue;
            }

            $productTax = (float)$lineResult->txAmt;

            // If the current state doesn't attach an automatic delivery fee, we don't need to recalculate the whole
            // order tax to extract the proportion from the product in which this tax was assigned
            if ($productTax > 0 && $this->hasAutomaticDeliveryFee()) {

                $this->response->lnRslts[$productIndex]->txRate = 0;
                $this->response->lnRslts[$productIndex]->productPercentage =
                    bcdiv($this->request['lines'][$productIndex]['grossAmt'], $this->productsTotal, 4);
                $this->response->lnRslts[$productIndex]->shippingProportion =
                    ($productTax <= 0) ? 0 :
                        bcmul($this->response->lnRslts[$productIndex]->productPercentage, $this->shippingCost, 4);

                foreach ($lineResult->jurRslts as $jurisdictionIndex => $jurisdictionResult) {

                    if (!property_exists($jurisdictionResult, 'txName')
                        || !property_exists($jurisdictionResult, 'txAmt')
                        || !property_exists($jurisdictionResult, 'txRate')) {
                        continue;
                    }

                    // We're going to manually remove this calculation returned by Sovos since we're doing the
                    // shipping tax costs ourselves. For each state, we'll have an array with all possible automatic
                    // shipping fees applied to that particular state.

                    $automaticDeliveryFee = array_filter(
                        $this->statesWithAutomaticRetailDeliveryFees[$this->state],
                        fn($description) => stripos($jurisdictionResult->txName, $description) !== false
                    );

                    if (!empty($automaticDeliveryFee)) {

                        $this->automaticDeliveryFee = (float)$jurisdictionResult->txAmt;

                        // Update order tax
                        $this->productsTax = (float)bcsub($this->productsTax, $this->automaticDeliveryFee, 2);
                        if ($this->productsTax < 0) {
                            $this->productsTax = 0;
                        }
                        $this->response->txAmt = $this->productsTax;

                        // Update current product tax
                        $productTax = (float)bcsub($productTax, $this->automaticDeliveryFee, 2);
                        if ($productTax < 0) {
                            $productTax = 0;
                        }
                        $this->response->lnRslts[$productIndex]->txAmt = $productTax;
                        $this->response->lnRslts[$productIndex]->jurRslts[$jurisdictionIndex]->txAmt = $productTax;

                        // At this point we're sure that current product tax it's bigger than zero
                        $this->debug($this->getOriginalSovosProductsTax(), 'original_tax');
                        $this->debug($this->getProductsTax(), 'total_amount');
                        $this->debug($this->getRemovedAutomaticDeliveryFee(), 'automatic_state_fee');
                        $this->debug("Manual {$this->state} State Fee Display Removed: {$this->automaticDeliveryFee}", 'shipping');

                    } else {
                        $this->response->lnRslts[$productIndex]->txRate
                            = (float)bcadd($this->response->lnRslts[$productIndex]->txRate, $jurisdictionResult->txRate, 4);
                    }
                }

                $this->shippingFee = bcadd(
                    $this->shippingFee,
                    bcmul(
                        $this->response->lnRslts[$productIndex]->shippingProportion,
                        $this->response->lnRslts[$productIndex]->txRate,
                        4
                    ),
                    4);

            }

            // Update lineItems with calculated values
            $this->lineItems[$productIndex]->taxable_amount = $this->response->lnRslts[$productIndex]->grossAmt;
            $this->lineItems[$productIndex]->tax_collectable = $this->response->lnRslts[$productIndex]->txAmt;

            // Note: The following line is intended to improve readability in the tax-breakdown view only.
            $this->response->lnRslts[$productIndex] = $this->rearrangeProperties(
                $this->response->lnRslts[$productIndex],
                ['lnNm', 'txRate', 'productPercentage', 'shippingProportion', 'txAmt', 'grossAmt', 'jurRslts', 'mergedResult', 'deMsg']
            );

        }

        // JMB-11634
        if($this->hasAutomaticDeliveryFee()) {

            if (!class_exists('StateFeeService')) {
                require_once ABSPATH . '/classes/StateFeeService.php';
            }

            $stateFee = self::jmconnection()
                ->get_row(
                    self::jmconnection()
                        ->prepare(
                            <<<EOF
                            SELECT
                                COALESCE(NULLIF(`fee_desc_url`, ''), '#') AS `url`,
                                COALESCE(NULLIF(`fee_title`, ''), CONCAT(`state_name`, ' Delivery Fee')) AS `title`,
                                CAST(%s AS DECIMAL(10,2)) AS `amount`
                            FROM `tax_state`
                            WHERE `state` = %s
                        EOF,
                            number_format((float) ($this->automaticDeliveryFee ?? 0), 2, '.', ''),
                            $this->getState()
                        )
                );

            if($stateFee) {
                $this->stateFee = \StateFeeService::getStateFeeFromData($stateFee);
            }
        }

    }

    protected function processResponse(): void
    {

        $this->shippingFee = 0;

        // Check if there's an error present, on this case I'll set anyway 'txAmt' property to avoid breaking issues
        if (!property_exists($this->response, 'txAmt')) {
            $this->response->txAmt = 0;
        }

        if (property_exists($this->response, 'txwTrnDocId') && $this->response->txwTrnDocId !== '0') {
            $this->txwTrnDocId = $this->response->txwTrnDocId;
        }

        // $this->response->originalSovosProductsTax holds the original tax result directly from Sovos.
        // This variable is crucial for post-processing tasks, such as calling 'statesWithAutomaticRetailDeliveryFees'.
        // By storing the original result here, we ensure it can be used for future comparisons.
        // $productsTax holds the final tax result after all our internal updates.

        $this->productsTax = $this->response->originalSovosProductsTax = (float)$this->response->txAmt;

        // We're storing the original Sovos calculation under a new property called `originalSovosProductsTax`

        if ($this->isRequestTypeHeader()) {
            // We have created this extra method to work all post-request calculations
            if (property_exists($this->response, 'lnRslts') && is_array($this->response->lnRslts)) {

                // These are the list of currently applied operations:

                // Sovos - Colorado State Fee Display
                $this->cleanupAutomaticResponseDeliveryFees();

            }
        }

        // Handle Shipping Tax via products line
        if ($this->isRequestTypeLine()) {
            // From 2004-04-17 we need to create a new request with code 70 if we received a tax amount from Sovos,
            // according to today's meeting we need to make an extra request with just one line to receive shipping tax
            if ($this->shippingCost > 0 && $this->response->txAmt > 0) {

                $request = $this->request;

                // April 17 we went back to code 70 and separate request for shipping values
                $request['lines'][] = $this->buildProductLinePayload($this->shippingCost, 70);

                $this->shippingRequest = $request;
                $this->shippingResponse = $this->client->request("calcTax/doc", $request);

            }
        }

        if ($this->shippingCost > 0) {

            // We're only making the second request if in the first call there's a tax involved
            if ($this->isRequestTypeLine() && $this->response->txAmt > 0) {
                if (property_exists($this->shippingResponse, 'lnRslts') && is_array($this->shippingResponse->lnRslts)) {

                    $count = count($this->shippingResponse->lnRslts);

                    // Shipping information should be always the last element pushed
                    // This final check is just to confirm whether the response includes a special item containing shipment results
                    if ($count !== count($this->shippingRequest['lines'])) {
                        throw new \Exception("Sovos response didn't include shipment information with order ID {$this->order_id}");
                    }

                    $this->shippingFee = (float)$this->shippingResponse->lnRslts[$count - 1]->txAmt ?? 0;
                }
            }
        }
    }

    /**
     * @param $product_id
     * @return int
     * @throws \Exception
     */
    public function getSovosIdFromProductId($product_id): int
    {

        // https://jmbdev.atlassian.net/browse/JMB-11049
        // Add an option to automatically assign code 89 as the Sovos Code for products in Cybermetals orders
        if ($this->getIsCyberMetalOrder()) {
            return 89;
        }

        $query = <<<END
                SELECT `sovos_id` FROM `tax_product_sovos`
                    INNER JOIN `sovos_codes` ON `tax_product_sovos`.`sovos_code_id` = `sovos_codes`.`id`
                WHERE `tax_product_sovos`.`product_id` = %d
            END;


        $code = static::jmconnection()->get_var(static::jmconnection()->prepare($query, $product_id));

        if (empty($code)) {
            throw new \Exception("There's not hardcoded Sovos Code for product id: '{$product_id}'");
        }

        return (int)$code;

    }

    private function getCustomAttributes(int $product_id, int $quantity = 0, $line_total = 0): array
    {

        $customAttributes = [];

        //  'SPOTPRICE' = true/false, used to determine if a product is a "high premium" based on the rules in New York (and any other States)
        if ($this->isHighPremiumProduct($product_id, $quantity, $line_total)) {
            $customAttributes['SPOTPRICE'] = true;
        }

        return $customAttributes;

    }

    protected function debug(string $message, string $object)
    {
        if (PHP_SAPI === "cli") {
            echo "  [>] {$message}{$this->newLine}";
        }

        if (!array_key_exists($object, $this->debugMessages)) {
            $this->debugMessages[$object] = [];
        }

        // Skipping debug messages over web requests
        $this->debugMessages[$object][] = $message;
    }

    public function isHighPremiumProduct(int $product_id, int $quantity, float $line_total): bool
    {

        if (!in_array($this->state, $this->statesWithTenderRules)) {
            return false;
        }

        if ($this->debug) {
            $this->debug("isHighPremiumProduct {$this->state} check for product {$product_id} with order {$this->order_id}, quantity ($quantity), line total ($line_total):", $product_id);
        }

        $unit_price = $line_total / $quantity;

        // In the original code, we never reach this point when a user visits the checkout page
        if (empty($this->order_id)) {

            $metalQuery = <<<EOF
                SELECT `metal_type`, `price` AS `base`, `product_weight` FROM `wp_jm_productmeta`
                INNER JOIN `latest_spot` ON `wp_jm_productmeta`.`metal_type` = `latest_spot`.`metal`
                WHERE `product_id` = %d
            EOF;

            $metalQuery = self::jmconnection()->prepare($metalQuery, $product_id);

        } else {

            // Original code
            $metalQuery = <<<EOF
                SELECT `pm`.`metal_type`, `om`.`spot` AS `base`, `pm`.`product_weight` FROM `wp_jm_orders_meta` `om` 
                JOIN `wp_jm_productmeta` `pm` ON `om`.`product_id` = `pm`.`product_id` 
                WHERE `om`.`order_id` = %d AND `om`.`product_id` = %d
            EOF;

            $metalQuery = self::jmconnection()->prepare($metalQuery, $this->order_id, $product_id);
        }

        $isLegalTender
            = (bool)self::jmconnection()
            ->get_row(
                self::jmconnection()->prepare(
                    <<<EOF
                        SELECT EXISTS(
                            SELECT 1
                            FROM `sovos_state_tender_rules`
                            INNER JOIN `tax_product_sovos` ON `sovos_state_tender_rules`.`sovos_code_id` = `tax_product_sovos`.`sovos_code_id`
                            WHERE `tax_product_sovos`.`product_id` = %d AND `sovos_state_tender_rules`.`state` = %s
                        )
                    EOF,
                    $product_id,
                    $this->state),
            );

        //get metal type, weight and spot
        $metal_info
            = self::jmconnection()
            ->get_row($metalQuery);

        if(is_null($metal_info)) {
            return false;
        }

        $productName = '';
        if ($this->debug) {
            $productName = self::jmconnection()->get_var(
                self::jmconnection()->prepare("SELECT `product_title` FROM `wp_jm_productmeta` WHERE `product_id` = %d", $product_id)
            );
        }

        //silver coins
        if ($metal_info->metal_type === "XAG" && $isLegalTender) {

            if ($unit_price >= (($metal_info->base * $metal_info->product_weight) * 1.4)) {
                if ($this->debug) {
                    $this->debug("NY, {$productName} ($product_id) Silver 140% of spot", $product_id);
                }

                return true;
            }

        } elseif ($metal_info->metal_type == "XAU" && $metal_info->product_weight <= 0.25) {

            if ($unit_price >= (($metal_info->base * $metal_info->product_weight) * 1.2)) {
                if ($this->debug) {
                    $this->debug("NY, {$productName} ($product_id) Gold 120% of spot", $product_id);
                }

                return true;
            }

        } elseif (in_array($metal_info->metal_type, ["XPT", "XPD", "XAU"]) && $isLegalTender) {

            if ($unit_price >= (($metal_info->base * $metal_info->product_weight) * 1.15)) {
                if ($this->debug) {
                    $this->debug("NY, {$productName} ($product_id) Plat/Palladium 115% of spot", $product_id);
                }

                return true;
            }

        } elseif (in_array($metal_info->metal_type, ["XAU", "XAG", "XPT", "XPD"])) {

            if ($unit_price >= (($metal_info->base * $metal_info->product_weight) * 1.15)) {
                if ($this->debug) {
                    $this->debug("NY, {$productName} ($product_id) over 115% of spot", $product_id);
                }

                return true;
            }

        }

        return false;
    }

    /**
     * Returns the tax calculation result as an array.
     *
     * The returned array includes:
     * - status (string) The operation status. Always returns 'success'.
     * - amount_to_collect (float) The total tax amount to collect, including product taxes and, if applicable, delivery fees
     * - product_tax (float) The product_tax tax value, this value doesn't include any extra tax, like automatic delivery fee
     * - delivery_fee (float) The automatic delivery fee
     * - state_fee (StateFee|null) The StateFee object, present if any taxes are applied.
     * - response The Sovos response data.
     * - request The product request data containing the order information sent to Sovos.
     * - line_items (array) The list of product line items.
     *
     * @return array{
     *     status: string,
     *     amount_to_collect: float,
     *     product_tax: float,
     *     delivery_fee: float,
     *     state_fee: \StateFee|null,
     *     response: \Sovos\Models\SovosResponse,
     *     request: \Sovos\Models\SovosRequest,
     *     line_items: array
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => 'success',
            'amount_to_collect' => round($this->getTotalTax(), 2),
            'product_tax' => round($this->getProductsTax(), 2),
            'delivery_fee' => round($this->getAutomaticDeliveryFee(), 2),
            'state_fee' => $this->getStateFee(),
            'response' => $this->getResponse(),
            'request' => $this->getRequest(),
            'line_items' => $this->getLineItems(),
        ];
    }

}