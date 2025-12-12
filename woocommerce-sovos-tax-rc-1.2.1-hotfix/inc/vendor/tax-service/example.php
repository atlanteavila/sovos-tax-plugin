<?php

namespace App;

use App\TaxService;

require __DIR__ . '/vendor/autoload.php';

/**
 *  This is an example of what we will get from the woocommerce side, an array 
 *  of cart objects. The only information we need is the sovosTaxCode which is
 *  the good service id that Sovos defines, the item quantity and the total cost
 *  of all quantities of a given item.
 */
$woocommerceCartObjects = [
    [
        "sovosTaxCode" => "2052388",
        "itemQty" => 1,
        "totalCost" => 954.00
    ],
    [
        "sovosTaxCode" => "2052331",
        "itemQty" => 2,
        "totalCost" => 98.00
    ],
];

/**
 *  Instantiate a new TaxService, we need to pass in the username, password, hmac secret
 *  and the base URL of the sovos API. This information is probably stored on the wordpress
 *  side of the application either in an .env file or the database.
 */
$taxService = new TaxService(
    "restapiUAT@PINEHURST", 
    "Welcome$1", 
    "e6e1171a-ed32-4f2a-9662-1c7748b0549c",
    "https://gtduat.sovos.com"
);

// Set the from and to address for an accurate calculation
$taxService
    ->setFromAddress([
        "streetAddress" => "5 Trotter Hills Circle",
        "city" => "Pinehurst",
        "state" => "North Carolina",
        "postalCode" => "28374",
        "country" => "United States" 
    ])
    ->setToAddress([
        "streetAddress" => "100 Worthington Dr",
        "city" => "Porter",
        "state" => "Indiana",
        "postalCode" => "46304",
        "country" => "United States" 
    ]);

/**
 *  The response from both the quoteTax() and calculateTax() methods will return an associative 
 *  array with the following structure...
 * 
 *  [
 *      "success" => bool
 *      "message" => string
 *      "data" => array
 *  ]
 * 
 *  If the response is successful the data key will contain the response from Sovos
 */

$taxQuote = $taxService->quoteTax($woocommerceCartObjects);

if ($taxQuote["success"]) {
    // We should provide a unique order number to Sovos when we persist the tax obligation. If this isn't
    // provided by wordpress / woocommerce we can generate one.
    $orderNumber = "SAMPLE_ORDER-" . (string) time() . rand(1, 99999);

    // Provide the cart items and the order number.
    $taxCalcResult = $taxService->calculateTax($woocommerceCartObjects, $orderNumber);
    var_dump($taxCalcResult);
}