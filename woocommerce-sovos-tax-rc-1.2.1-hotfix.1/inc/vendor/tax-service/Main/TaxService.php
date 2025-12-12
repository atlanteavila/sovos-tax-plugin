<?php

declare(strict_types=1);

namespace App;

use DateTime;
use GuzzleHttp;
use App\Exceptions\InvalidAddressException;
use App\Exceptions\InvalidOrderIdException;
use App\Exceptions\MissingAddressException;

class TaxService {
    protected GuzzleHttp\Client $client;
    protected string $user;
    protected string $password;

    protected string $orgCd;
    protected string $hmacKey;
    protected string $baseURL;
    protected string $geoCodeEndpoint = "/Twe/api/rest/geoCode/single";
    protected string $calcTaxDocEndpoint = "/Twe/api/rest/calcTax/doc";
    protected ?TaxServiceAddress $fromAddress = null;
    protected ?TaxServiceAddress $toAddress = null;
    protected $items = [];
    public bool $requestInProgress = false;
    public bool $refundTransaction = false;
    protected $customerId = null;

    public function __construct(string $user, string $password, string $orgCd, string $hmac_secret, string $url)
    {
        $this->client = new GuzzleHttp\Client();
        $this->user = $user;
        $this->password = $password;
        $this->orgCd = $orgCd;
        $this->hmacKey = $hmac_secret;
        $this->baseURL = $url;
    }

    /**
     *  Set the from address. Accepts a hash map with the following structure, all values
     *  must be strings.
     *  [
     *      "streetAddress" => "123 Fake St",
     *      "city" => "Faketown",
     *      "state" => "CA",
     *      "postalCode" => "12345",
     *      "country" => "USA"
     *  ]
     */
    public function setFromAddress(array $address) : TaxService
    {
        $fromAddress = new TaxServiceAddress($address);
        $this->fromAddress = $fromAddress;
        return $this;
    }

    /**
     *  Clear the from address.
     */
    public function clearFromAddress() : TaxService
    {
        $this->fromAddress = null;
        return $this;
    }

    /**
     *  Set the to address. Accepts a hash map with the following structure, all values
     *  must be strings.
     *  [
     *      "streetAddress" => "123 Fake St",
     *      "city" => "Faketown",
     *      "state" => "CA",
     *      "postalCode" => "12345",
     *      "country" => "USA"
     *  ]
     */
    public function setToAddress(array $address) : TaxService
    {
        $toAddress = new TaxServiceAddress($address);
        $this->toAddress = $toAddress;
        return $this;
    }

    /**
     *  Clear the to address.
     */
    public function clearToAddress() : TaxService
    {
        $this->toAddress = null;
        return $this;
    }

    /**
     *  Set the customer by passing in the customer id. The customer will then
     *  be associated with a Sovos transaction.
     */
    public function setCustomer(int|string $id) : TaxService
    {
        $this->customerId = (string)$id;
        return $this;
    }

    public function unsetCustomer() : TaxService
    {
        $this->customerId = null;
        return $this;
    }

    protected function prepareHeaderData(string $endpoint) : array
    {
        $current_date_time = new DateTime("now");
        $formatted_date_time = $current_date_time->format("Y-m-d\TH:i:s\Z");

        // Create the message and hmac signature, hmac signature needs to be base_64 encoded.
        $message = "POSTapplication/json".$formatted_date_time.$endpoint.$this->user.$this->password;
        $hmac = hash_hmac('sha1', $message, $this->hmacKey, true);
        $hmacBase64 = base64_encode($hmac);

        return [
            "Date" => $formatted_date_time,
            "Authorization" => "TAX " . $this->user . ":" . $hmacBase64
        ];
    }

    protected function apiResonse(bool $success = true, string $message = '', array $data = [], array $request = null) : array
    {
        $result = [
            "success" => $success,
            "message" => $message,
            "data" => $data
        ];
        if ($request !== null)
            $result['request'] = $request;
        return $result;
    }

    public function setItemProperties($taxClass, $totalCost, $quantity) : TaxService
    {
        $this->items[] = [
            'tax_class' => $taxClass,
            'line_total' => $totalCost,
            'quantity' => $quantity
        ];

        return $this;
    }

    public function clearItems() : TaxService
    {
        $this->items = [];
        return $this;
    }

    protected function prepareLineItems() : array
    {
        $creditDebit = $this->refundTransaction ? 2 : 1;
        $items = $this->items;
        
        $lineItems = [];

        foreach ($items as $item) {
            if (
                !isset($item['tax_class']) ||
                !isset($item['line_total']) ||
                !isset($item['quantity'])
            )
                continue;

            array_push($lineItems, [
                "origDocNum" => "test123",
                "origTrnDt" => date("Y-m-d"),
                "debCredIndr" => $creditDebit,
                "trnTp" => 1,
                "orgCd" => $this->orgCd,
                "goodSrvCd" => $item['tax_class'], // This is the tax code
                "grossAmt" => $item['line_total'], // This is the total cost of all quantities of the item
                "qnty" => $item['quantity'], // This is the quantity of the item
                "custVendCd" => $this->customerId,
                "sFCity" => $this->fromAddress->city,
                "sFStateProv" => $this->fromAddress->state,
                "sFPstlCd" => $this->fromAddress->postalCode,
                "sFCountry" => $this->fromAddress->country,
                "sTStNameNum" => $this->toAddress->streetAddress,
                "sTCity" => $this->toAddress->city,
                "sTStateProv" => $this->toAddress->state,
                "sTPstlCd" => $this->toAddress->postalCode,
                "sTCountry" => $this->toAddress->country,
            ]);
        }

        return $lineItems;
    }

    /**
     *  When calculating the tax we need to either provide a geocode or address. We
     *  are not using the geocodes right now but we have this method in case we do
     *  want to use it in the future.
     */
    public function getGeocode(string $city, string $state, string $postalCode, string $country) : array
    {
        $headerData = $this->prepareHeaderData($this->geoCodeEndpoint);

        $formBody = [
            "usrname" => $this->user,
            "pswrd" => $this->password,
            "city" => $city,
            "stateProv" => $state,
            "pstlCd" => $postalCode,
            "country" => $country,
        ];

        try {
            $response = $this->client->request("POST", $this->baseURL.$this->geoCodeEndpoint, [
                "headers" => [
                    "Content-Type" => "application/json", 
                    "Accept" => "application/json",
                    "Date" => $headerData["Date"],
                    "Authorization" => $headerData["Authorization"]
                ],
                "body" => json_encode($formBody)
            ]);
            $decodedResponseBody = json_decode($response->getBody()->getContents(), true);
            return $this->apiResonse(true, "Fetched geocode successfully", [ 
                "geocode" => $decodedResponseBody['geoCd'] 
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($e->hasResponse()) {
                return $this->apiResonse(false, $e->getResponse()->getBody()->getContents(), []);
            } else {
                return $this->apiResonse(false, $e->getMessage(), []);
            }
        } catch (\Exception $e) {
            return $this->apiResonse(false, $e->getMessage(), []);
        }
    }

    /**
     *  This is the endpoint that calculates the tax and persists it on the Sovos
     *  end.
     *  @param bool   $audit is set to true by default, set it to false to avoid persisting it in Sovos
     *  @param string $orderId should be provided when we persist the order to Sovos. If it is not a random number is generated.
     *  @return array returns an array with the following structure. If the request succeeds there will be some information in
     *                the data property.
     *
     *              [
     *                  "success" => bool
     *                  "message" => string
     *                  "data" => array
     *              ]
     */
    public function calculateTax(string $orderId, bool $audit = true) : array
    {
        $this->requestInProgress = true;

        try {
            if ($this->fromAddress === null || $this->toAddress === null) {
                throw new MissingAddressException;
            }

            if ($audit === true && empty($orderId)) {
                throw new InvalidOrderIdException;
            }
    
            $lineItems = $this->prepareLineItems();
    
            $headerData = $this->prepareHeaderData($this->calcTaxDocEndpoint);

            $formBody = [
                "usrname" => $this->user,
                "pswrd" => $this->password,
                "currn" => "USD",
                "isAudit" => $audit,
                "tdmRequired" => true,
                "trnDocNum" => $orderId,
                "txCalcTp" => "1",
                "docDt" => date("Y-m-d"),
                "lines" => $lineItems
            ];

            $response = $this->client->request("POST", $this->baseURL.$this->calcTaxDocEndpoint, [
                "headers" => [
                    "Content-Type" => "application/json", 
                    "Accept" => "application/json",
                    "Date" => $headerData["Date"],
                    "Authorization" => $headerData["Authorization"]
                ],
                "body" => json_encode($formBody)
            ]);

            $this->requestInProgress = false;
            $decodedResponse = json_decode($response->getBody()->getContents(), true);
            $message = $audit ? "Tax calculation successful" : "Tax quote successful";

            // Replace username and pswrd from the response with REDACTED
            $redactedFormBody = $formBody;
            $redactedFormBody['usrname'] = 'REDACTED';
            $redactedFormBody['pswrd']   = 'REDACTED';
            return $this->apiResonse(true, $message, $decodedResponse, $redactedFormBody);
        } catch (MissingAddressException $e) {
            $this->requestInProgress = false;
            return $this->apiResonse(false, "From and to shipping address are required", []);
        } catch (InvalidAddressException $e) {
            $this->requestInProgress = false;
            return $this->apiResonse(false, "The address is invalid", []);
        } catch(InvalidOrderIdException $e) {
            $this->requestInProgress = false;
            return $this->apiResonse(false, "Invalid order number", []);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->requestInProgress = false;
            if ($e->hasResponse()) {
                return $this->apiResonse(false, $e->getResponse()->getBody()->getContents(), []);
            } else {
                return $this->apiResonse(false, $e->getMessage(), []);
            }
        }
    }

    /**
     *  This can be used to get a quote for calculating the tax.
     *  @return array returns an array with the following structure. If the request succeeds there will be some information in
     *                the data property.
     *
     *              [
     *                  "success" => bool
     *                  "message" => string
     *                  "data" => array
     *              ]
     */
    public function quoteTax() : array
    {
        // For the quote we can generate a dummy value for the order_id
        $transactionNumber = (string) time() . rand(1, 99999);
        return $this->calculateTax($transactionNumber, false);
    }

    /**
     *  Get the tax transaction record from Sovos for a given order. This method accepts the txwTrnDocId
     *  field that we get back from Sovos when the tax is calculated.
     *  @param string $transactionDocId
     *  @return array returns an array with the following structure. If the request succeeds there will be some information in
     *                the data property.
     *
     *              [
     *                  "success" => bool
     *                  "message" => string
     *                  "data" => array
     *              ]
     */
    public function transactionDetail(string $transactionDocId) : array
    {
        $this->requestInProgress = true;
        $endpoint = "/Twe/api/rest/calcTax/result/byDocID";

        try {
            $headerData = $this->prepareHeaderData($endpoint);

            $formBody = [
                "usrname" => $this->user,
                "pswrd" => $this->password,
                "txwTrnDocId" => $transactionDocId,
            ];

            $response = $this->client->request("POST", $this->baseURL.$endpoint, [
                "headers" => [
                    "Content-Type" => "application/json", 
                    "Accept" => "application/json",
                    "Date" => $headerData["Date"],
                    "Authorization" => $headerData["Authorization"]
                ],
                "body" => json_encode($formBody)
            ]);

            $this->requestInProgress = false;
            $decodedResponse = json_decode($response->getBody()->getContents(), true);

            return $this->apiResonse(true, "Transaction detail request successful", $decodedResponse);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->requestInProgress = false;
            if ($e->hasResponse()) {
                return $this->apiResonse(false, $e->getResponse()->getBody()->getContents(), []);
            } else {
                return $this->apiResonse(false, $e->getMessage(), []);
            }
        } catch (\Exception $e) {
            $this->requestInProgress = false;
            return $this->apiResonse(false, $e->getMessage(), []);
        }
    }

    /**
     *  This method can be used to get a refund. We can set the item to refund using the setItemProperties() method. Once the refund is completed we
     *  will remove the refund items to have a clean slate.
     *  @param string $refundId, this can be a unique id from woocommerce that identifies the refund.
     *  @return array returns an array with the following structure. If the request succeeds there will be some information in
     *                the data property.
     *
     *              [
     *                  "success" => bool
     *                  "message" => string
     *                  "data" => array
     *              ]
     */
    public function refund(string $refundId) : array
    {
        $this->refundTransaction = true;
        $result = $this->calculateTax($refundId);
        if ($result['success']) {
            $this->clearItems();
        }
        $this->refundTransaction = false;
        return $result;
    }

    /**
     *  This method can be used to clear the tax service. This will remove all the data that has been set
     *  on the tax service.
     */
    public function clearTaxService() : TaxService
    {
        $this->clearFromAddress();
        $this->clearToAddress();
        $this->clearItems();
        $this->unsetCustomer();

        return $this;
    }
}
