<?php

declare(strict_types=1);

namespace App;

use App\Exceptions\InvalidAddressException;

class TaxServiceAddress {
    public string $streetAddress;
    public string $city;
    public string $state;
    public string $postalCode;
    public string $country;

    public function __construct(array $address)
    {
        $invalid_street_address = (
            !isset($address['streetAddress']) ||
            empty($address['streetAddress']) ||
            !is_string($address['streetAddress'])
        );

        $invalid_city = (
            !isset($address['city']) ||
            empty($address['city']) ||
            !is_string($address['city'])
        );

        $invalid_state = (
            !isset($address['state']) ||
            empty($address['state']) ||
            !is_string($address['state'])
        );

        $invalid_postal_code = (
            !isset($address['postalCode']) ||
            empty($address['postalCode']) ||
            !is_string($address['postalCode'])
        );

        $invalid_country = (
            !isset($address['country']) ||
            empty($address['country']) ||
            !is_string($address['country'])
        );

        if (
            $invalid_street_address ||
            $invalid_city ||
            $invalid_state ||
            $invalid_postal_code ||
            $invalid_country
        ) {
            throw new InvalidAddressException('The provided address is invalid.');
        }

        $this->streetAddress = $address['streetAddress'];
        $this->city = $address['city'];
        $this->state = $address['state'];
        $this->postalCode = $address['postalCode'];
        $this->country = $address['country'];
    }
}