<?php

declare(strict_types=1);

namespace Packetery\Checkout\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
use Packetery\Checkout\Model\Carrier\Config\AbstractConfig;
use Packetery\Checkout\Model\Carrier\Config\AbstractDynamicConfig;
use Packetery\Checkout\Model\Carrier\Config\AbstractMethodSelect;

/**
 * Use this service to extend custom carriers with new logic that is using dependencies. Good for avoiding constructor hell.
 */
abstract class AbstractBrain
{
    public const PREFIX = 'packetery';
    public const MULTI_SHIPPING_MODULE_NAME = 'multishipping';

    /** @var \Magento\Framework\App\Request\Http */
    protected $httpRequest;

    /** @var \Packetery\Checkout\Model\Pricing\Service */
    protected $pricingService;

    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    private $scopeConfig;

    /**
     * AbstractBrain constructor.
     *
     * @param \Magento\Framework\App\Request\Http $httpRequest
     * @param \Packetery\Checkout\Model\Pricing\Service $pricingService
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Request\Http $httpRequest,
        \Packetery\Checkout\Model\Pricing\Service $pricingService,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->httpRequest = $httpRequest;
        $this->pricingService = $pricingService;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param \Packetery\Checkout\Model\Carrier\AbstractCarrier $carrier
     * @return \Packetery\Checkout\Model\Carrier\Config\AbstractConfig
     */
    abstract public function createConfig(AbstractCarrier $carrier): \Packetery\Checkout\Model\Carrier\Config\AbstractConfig;

    /**
     * @param \Packetery\Checkout\Model\Carrier\Config\AbstractConfig $config
     * @param \Packetery\Checkout\Model\Carrier|null $dynamicCarrier
     * @return \Packetery\Checkout\Model\Carrier\Config\AbstractConfig
     */
    public function createDynamicConfig(AbstractConfig $config, ?AbstractDynamicCarrier $dynamicCarrier = null): AbstractConfig {
        return $config;
    }

    /**
     * @param string $carrierCode
     * @param mixed $scope
     * @return mixed
     */
    protected function getConfigData(string $carrierCode, $scope) {
        $path = 'carriers/' . $carrierCode;

        return $this->scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $scope
        );
    }

    /** Returns unique carrier identified in packetery context
     *
     * @return string
     */
    public function getCarrierCode(): string {
        return static::getCarrierCodeStatic();
    }

    /** Returns unique carrier identified in packetery context
     *
     * @return string
     */
    public static function getCarrierCodeStatic(): string {
        $reflection = new \ReflectionClass(static::class);
        $fileName = $reflection->getFileName();
        $carrierDir = basename(dirname($fileName));
        return lcfirst($carrierDir);
    }

    /**
     * @return \Packetery\Checkout\Model\Carrier\Config\AbstractMethodSelect
     */
    abstract public function getMethodSelect(): AbstractMethodSelect;

    /** Returns data that are used to figure out destination point id
     *
     * @return array
     */
    abstract protected function getResolvableDestinationData(): array;

    /**
     * @param string $method
     * @param string $countryId
     * @param \Packetery\Checkout\Model\Carrier\AbstractDynamicCarrier|null $dynamicCarrier
     * @return int|null
     */
    public function resolvePointId(string $method, string $countryId, ?AbstractDynamicCarrier $dynamicCarrier = null): ?int {
        $data = $this->getResolvableDestinationData();
        return ($data[$method]['countryBranchIds'][$countryId] ?? null);
    }

    /** Used only by Packeta Dynamic
     *
     * @param int $id
     * @return \Packetery\Checkout\Model\Carrier\AbstractDynamicCarrier|null
     */
    public function getDynamicCarrierById(?int $id): ?AbstractDynamicCarrier {
        return null; // majority of Magento carriers do not have dynamic carriers
    }

    /**
     * @param string $method
     * @param string $countryId
     * @param \Packetery\Checkout\Model\Carrier\AbstractDynamicCarrier|null $dynamicCarrier
     * @return bool
     */
    protected function availableForCollection(string $method, string $countryId, ?AbstractDynamicCarrier $dynamicCarrier = null): bool {
        if ($method !== Methods::PICKUP_POINT_DELIVERY) {
            if ($this->resolvePointId($method, $countryId, $dynamicCarrier) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param \Packetery\Checkout\Model\Carrier\AbstractCarrier $carrier
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @param \Packetery\Checkout\Model\Carrier\AbstractDynamicCarrier|null $dynamicCarrier
     * @return \Magento\Shipping\Model\Rate\Result|null
     */
    public function collectRates(AbstractCarrier $carrier, RateRequest $request, ?AbstractDynamicCarrier $dynamicCarrier = null): ?Result {
        $brain = $carrier->getPacketeryBrain();

        $config = $this->createDynamicConfig(
            $carrier->getPacketeryConfig(),
            $dynamicCarrier
        );

        if (!$brain->isCollectionPossible($config)) {
            return null;
        }

        $methods = [];
        foreach ($this->getFinalAllowedMethods($config, $brain->getMethodSelect()) as $selectedMethod) {
            if ($this->availableForCollection($selectedMethod, $request->getDestCountryId(), $dynamicCarrier) === false) {
                continue;
            }

            $methods[$selectedMethod] = $brain->getMethodSelect()->getLabelByValue($selectedMethod);
        }

        return $this->pricingService->collectRates($request, $carrier->getCarrierCode(), $config, $methods, ($dynamicCarrier ? $dynamicCarrier->getCarrierId() : null));
    }

    /**
     * @param \Packetery\Checkout\Model\Carrier\Config\AbstractConfig $config
     * @return bool
     */
    public function isCollectionPossible(AbstractConfig $config): bool {
        if ($this->httpRequest->getModuleName() == self::MULTI_SHIPPING_MODULE_NAME) {
            return false;
        }

        if (!$config->isActive()) {
            return false;
        }

        return true;
    }

    /** dynamic carriers visible in configuration
     * @param bool $configurable
     * @param string $country
     * @param array $methods
     * @return \Packetery\Checkout\Model\Carrier\AbstractDynamicCarrier[]
     */
    public function findConfigurableDynamicCarriers(string $country, array $methods): array {
        return [];
    }

    /** Static + dynamic countries
     * @param array $methods
     * @return array
     */
    public function getAvailableCountries(array $methods): array {
        return [];
    }

    /**
     * @param \Packetery\Checkout\Model\Carrier\Config\AbstractConfig $config
     * @param \Packetery\Checkout\Model\Carrier\Config\AbstractMethodSelect $methodSelect
     * @return array
     */
    public function getFinalAllowedMethods(AbstractConfig $config, AbstractMethodSelect $methodSelect): array {
        if ($config instanceof AbstractDynamicConfig) {
            $final = $this->getFinalAllowedMethods($config->getConfig(), $methodSelect);
            return array_intersect($config->getAllowedMethods(), $final);
        }

        $allowedMethods = $config->getAllowedMethods();
        if (empty($allowedMethods)) {
            return $methodSelect->getMethods();
        }

        return $allowedMethods;
    }
}
