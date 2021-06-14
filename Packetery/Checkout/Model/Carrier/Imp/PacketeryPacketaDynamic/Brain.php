<?php

declare(strict_types=1);

namespace Packetery\Checkout\Model\Carrier\Imp\PacketeryPacketaDynamic;

use Packetery\Checkout\Model\Carrier\AbstractDynamicCarrier;

class Brain extends \Packetery\Checkout\Model\Carrier\AbstractBrain
{
    /** @var \Packetery\Checkout\Model\Carrier\Imp\PacketeryPacketaDynamic\MethodSelect */
    private $methodSelect;

    /** @var \Packetery\Checkout\Model\ResourceModel\Carrier\CollectionFactory */
    private $carrierCollectionFactory;

    /** @var \Magento\Shipping\Model\Rate\ResultFactory */
    private $rateResultFactory;

    /**
     * Brain constructor.
     *
     * @param \Magento\Framework\App\Request\Http $httpRequest
     * @param \Packetery\Checkout\Model\Pricing\Service $pricingService
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Packetery\Checkout\Model\Carrier\Imp\PacketeryPacketaDynamic\MethodSelect $methodSelect
     * @param \Packetery\Checkout\Model\ResourceModel\Carrier\CollectionFactory $carrierCollectionFactory
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     */
    public function __construct(
        \Magento\Framework\App\Request\Http $httpRequest,
        \Packetery\Checkout\Model\Pricing\Service $pricingService,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Packetery\Checkout\Model\Carrier\Imp\PacketeryPacketaDynamic\MethodSelect $methodSelect,
        \Packetery\Checkout\Model\ResourceModel\Carrier\CollectionFactory $carrierCollectionFactory,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
    ) {
        parent::__construct($httpRequest, $pricingService, $scopeConfig);
        $this->methodSelect = $methodSelect;
        $this->carrierCollectionFactory = $carrierCollectionFactory;
        $this->rateResultFactory = $rateResultFactory;
    }

    /**
     * @param \Packetery\Checkout\Model\Carrier\AbstractCarrier $carrier
     * @return Config
     */
    public function createConfig(\Packetery\Checkout\Model\Carrier\AbstractCarrier $carrier): \Packetery\Checkout\Model\Carrier\Config\AbstractConfig {
        return new Config($this->getConfigData($carrier->getCarrierCode(), $carrier->getStore()));
    }

    /**
     * @return \Magento\Shipping\Model\Rate\Result
     */
    public function createRateResult(): \Magento\Shipping\Model\Rate\Result {
        return $this->rateResultFactory->create();
    }

    /** Represents all possible methods for all dynamic carriers
     *
     * @return \Packetery\Checkout\Model\Carrier\Imp\PacketeryPacketaDynamic\MethodSelect
     */
    public function getMethodSelect(): \Packetery\Checkout\Model\Carrier\Config\AbstractMethodSelect {
        return $this->methodSelect;
    }

    /**
     * @inheridoc
     */
    protected function getResolvableDestinationData(): array {
        return [];
    }

    /**
     * @param int|null $dynamicCarrierId
     * @return \Packetery\Checkout\Model\Carrier|null
     */
    public function getDynamicCarrierById(?int $dynamicCarrierId): ?AbstractDynamicCarrier {
        $model = $this->carrierCollectionFactory->create()->getItemByColumnValue('carrier_id', $dynamicCarrierId);
        if ($model === null) {
            return null;
        }

        return new DynamicCarrier($model);
    }

    /**
     * @return array
     */
    public function findResolvableDynamicCarriers(): array {
        /** @var \Packetery\Checkout\Model\ResourceModel\Carrier\Collection $collection */
        $collection = $this->carrierCollectionFactory->create();
        $collection->resolvableOnly();
        return $collection->getItems();
    }

    /**
     * @param string $country
     * @param array $methods
     * @return DynamicCarrier[]
     */
    public function findConfigurableDynamicCarriers(string $country, array $methods): array {
        /** @var \Packetery\Checkout\Model\ResourceModel\Carrier\Collection $collection */
        $collection = $this->carrierCollectionFactory->create();
        $collection->configurableOnly();
        $collection->whereCountry($country);
        $collection->forDeliveryMethods($methods);
        $items = $collection->getItems();

        return array_map(
            function (\Packetery\Checkout\Model\Carrier $carrier) {
                return new DynamicCarrier($carrier);
            },
            $items
        );
    }

    /**
     * @param string $method
     * @param string $countryId
     * @return int|null
     * @throws \Exception
     */
    public function resolvePointId(string $method, string $countryId, ?AbstractDynamicCarrier $dynamicCarrier = null): ?int {
        if ($dynamicCarrier === null) {
            throw new \Exception('Invalid usage');
        }

        if ($dynamicCarrier->getDeleted() === true) {
            return null;
        }

        if ($dynamicCarrier->getCountryId() !== $countryId) {
            return null;
        }

        if (in_array($method, $dynamicCarrier->getMethods()) === false) {
            return null;
        }

        return $dynamicCarrier->getCarrierId();
    }

    /**
     * @param \Packetery\Checkout\Model\Carrier\Config\AbstractConfig $config
     * @param \Packetery\Checkout\Model\Carrier\AbstractDynamicCarrier|null $dynamicCarrier
     * @return \Packetery\Checkout\Model\Carrier\Config\AbstractConfig
     */
    public function createDynamicConfig(\Packetery\Checkout\Model\Carrier\Config\AbstractConfig $config, ?AbstractDynamicCarrier $dynamicCarrier = null): \Packetery\Checkout\Model\Carrier\Config\AbstractConfig {
        return new DynamicConfig(
            $config,
            $dynamicCarrier
        );
    }

    /**
     * @param array $methods
     * @return array
     */
    public function getAvailableCountries(array $methods): array {
        /** @var \Packetery\Checkout\Model\ResourceModel\Carrier\Collection $collection */
        $collection = $this->carrierCollectionFactory->create();

//        $methodSelect = $this->getMethodSelect();
//        $collection->forDeliveryMethods($methodSelect->getMethods());

        return $collection->getColumnValues('country');
    }
}
