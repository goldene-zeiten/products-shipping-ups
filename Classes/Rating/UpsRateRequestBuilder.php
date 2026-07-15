<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Rating;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfiguration;

/**
 * Builds the UPS "Shop" rate-request payload (the array serialised to RateRequest JSON) from the basket
 * context and the resolved configuration. Weight-only, postal-code + country: enough for a quote, and all
 * the shop knows. Integrators needing dimensions or multi-package splits add them via
 * {@see \GoldeneZeiten\Products\Shipping\Ups\Event\ModifyUpsRateRequestEvent}.
 */
final class UpsRateRequestBuilder
{
    private const PACKAGING_TYPE_CODE = '02';
    private const GRAMS_PER_KILOGRAM = 1000.0;
    private const GRAMS_PER_POUND = 453.59237;

    /**
     * @return array<string, mixed>
     */
    public function build(ShippingContext $context, UpsConfiguration $configuration): array
    {
        $origin = $this->address($configuration->originPostCode, $configuration->originCountryCode, $configuration->originCity);
        $shipper = $configuration->accountNumber !== ''
            ? ['ShipperNumber' => $configuration->accountNumber] + $origin
            : $origin;

        return [
            'RateRequest' => [
                'Request' => [
                    'RequestOption' => 'Shop',
                ],
                'Shipment' => [
                    'Shipper' => $shipper,
                    'ShipTo' => $this->address($context->getPostCode(), $context->getCountryCode()),
                    'ShipFrom' => $origin,
                    'Package' => [
                        $this->package($context, $configuration),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function address(string $postCode, string $countryCode, string $city = ''): array
    {
        $address = [
            'CountryCode' => strtoupper($countryCode),
        ];
        if ($postCode !== '') {
            $address['PostalCode'] = $postCode;
        }
        if ($city !== '') {
            $address['City'] = $city;
        }

        return [
            'Address' => $address,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function package(ShippingContext $context, UpsConfiguration $configuration): array
    {
        return [
            'PackagingType' => [
                'Code' => self::PACKAGING_TYPE_CODE,
                'Description' => 'Package',
            ],
            'PackageWeight' => [
                'UnitOfMeasurement' => [
                    'Code' => $configuration->weightUnit,
                    'Description' => $configuration->weightUnit === 'LBS' ? 'Pounds' : 'Kilograms',
                ],
                'Weight' => $this->weight($context->getTotalWeight(), $configuration->weightUnit),
            ],
        ];
    }

    private function weight(int $grams, string $unit): string
    {
        $divisor = $unit === 'LBS' ? self::GRAMS_PER_POUND : self::GRAMS_PER_KILOGRAM;

        // UPS rejects a zero weight; a basket without maintained weights still needs a positive figure.
        return number_format(max(0.1, $grams / $divisor), 1, '.', '');
    }
}
