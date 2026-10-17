<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Shipping;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Shipping\ShippingProviderInterface;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfiguration;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfigurationFactory;
use GoldeneZeiten\Products\Shipping\Ups\Domain\Dto\UpsRate;
use GoldeneZeiten\Products\Shipping\Ups\Event\ModifyUpsShippingOptionsEvent;
use GoldeneZeiten\Products\Shipping\Ups\Rating\UpsRatingClient;
use GoldeneZeiten\Products\Shipping\Ups\Rating\UpsServiceCatalog;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * A real (non-fallback) carrier that offers live UPS rates. Being a real carrier, it supersedes the
 * shop's built-in table-rate shipping whenever it returns options; when it cannot - unconfigured, UPS
 * unreachable, or no rate for the basket - it returns none, and the table-rate fallback takes over, so
 * the checkout never dead-ends.
 */
final class UpsShippingProvider implements ShippingProviderInterface
{
    public const IDENTIFIER = 'ups';

    public function __construct(
        private readonly UpsConfigurationFactory $configurationFactory,
        private readonly UpsRatingClient $ratingClient,
        private readonly UpsServiceCatalog $serviceCatalog,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getPriority(): int
    {
        return 100;
    }

    /**
     * @return ShippingOption[]
     */
    public function quote(ShippingContext $context): array
    {
        $configuration = $this->configuration($context);
        if (!$configuration->isComplete()) {
            return [];
        }

        try {
            $rates = $this->ratingClient->rate($context, $configuration);
        } catch (\Throwable $exception) {
            $this->logger->error('UPS rating failed; leaving the basket to the fallback carrier.', ['exception' => $exception]);
            return [];
        }

        return $this->toOptions($rates, $context, $configuration);
    }

    /**
     * The configuration of the site the basket is shopped on; the express shipping-rate callback quotes
     * before core publishes the current request.
     */
    private function configuration(ShippingContext $context): UpsConfiguration
    {
        $site = $context->getSite();

        return $site !== null ? $this->configurationFactory->forSite($site) : $this->configurationFactory->forCurrentRequest();
    }

    public function resolve(string $optionIdentifier, ShippingContext $context): ?ShippingOption
    {
        foreach ($this->quote($context) as $option) {
            if ($option->getOptionIdentifier() === $optionIdentifier) {
                return $option;
            }
        }

        return null;
    }

    /**
     * @param UpsRate[] $rates
     * @return ShippingOption[]
     */
    private function toOptions(array $rates, ShippingContext $context, UpsConfiguration $configuration): array
    {
        $options = [];
        foreach ($rates as $rate) {
            if ($this->isOffered($rate, $context, $configuration)) {
                $options[] = $this->toOption($rate, $context, $configuration);
            }
        }

        $event = new ModifyUpsShippingOptionsEvent($options, $context, $configuration);
        $this->eventDispatcher->dispatch($event);

        return $event->getOptions();
    }

    private function isOffered(UpsRate $rate, ShippingContext $context, UpsConfiguration $configuration): bool
    {
        if (!$configuration->offersService($rate->serviceCode)) {
            return false;
        }
        if ($rate->currencyCode !== '' && $rate->currencyCode !== $context->getCurrency()) {
            // Never present a rate quoted in another currency as if it were the basket's own.
            $this->logger->warning('UPS rate currency differs from the basket currency; skipping the service.', [
                'service' => $rate->serviceCode,
                'rateCurrency' => $rate->currencyCode,
                'basketCurrency' => $context->getCurrency(),
            ]);
            return false;
        }

        return true;
    }

    private function toOption(UpsRate $rate, ShippingContext $context, UpsConfiguration $configuration): ShippingOption
    {
        $shipsFree = $configuration->shipsFree($rate->serviceCode, $context->getGoodsTotal()->getCents());
        return new ShippingOption(
            self::IDENTIFIER,
            $rate->serviceCode,
            $this->serviceCatalog->label($rate->serviceCode),
            $shipsFree ? Money::fromCents(0) : Money::fromDecimalString($rate->amount),
            null,
            $this->deliveryEstimate($rate->businessDaysInTransit),
        );
    }

    private function deliveryEstimate(string $businessDays): string
    {
        if ($businessDays === '') {
            return '';
        }
        $key = (int)$businessDays === 1
            ? 'LLL:EXT:products_shipping_ups/Resources/Private/Language/locallang.xlf:deliveryEstimate.businessDay'
            : 'LLL:EXT:products_shipping_ups/Resources/Private/Language/locallang.xlf:deliveryEstimate.businessDays';
        return (string)(LocalizationUtility::translate($key, null, [(int)$businessDays]) ?? $businessDays);
    }
}
