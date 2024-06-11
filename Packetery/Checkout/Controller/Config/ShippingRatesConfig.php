<?php

declare(strict_types=1);

namespace Packetery\Checkout\Controller\Config;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Packetery\Checkout\Model\AddressValidationSelect;
use Packetery\Checkout\Model\Carrier\AbstractCarrier;
use Packetery\Checkout\Model\Carrier\AbstractDynamicCarrier;
use Packetery\Checkout\Model\Carrier\MethodCode;
use Packetery\Checkout\Model\Carrier\VendorGroups;
use Packetery\Checkout\Model\Pricingrule;

class ShippingRatesConfig implements HttpPostActionInterface
{
    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    protected $resultJsonFactory;

    /** @var RequestInterface */
    private $request;

    /** @var \Magento\Shipping\Model\CarrierFactory */
    private $carrierFactory;

    /** @var \Packetery\Checkout\Model\Pricing\Service */
    private $pricingService;

    /** @var \Packetery\Checkout\Model\FeatureFlag\Manager */
    private $featureFlagManager;

    /**
     * ShippingRateConfig constructor.
     *
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Shipping\Model\CarrierFactory $carrierFactory
     * @param \Packetery\Checkout\Model\Pricing\Service $pricingService
     * @param \Packetery\Checkout\Model\FeatureFlag\Manager $featureFlagManager
     */
    public function __construct(
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Shipping\Model\CarrierFactory $carrierFactory,
        \Packetery\Checkout\Model\Pricing\Service $pricingService,
        \Packetery\Checkout\Model\FeatureFlag\Manager $featureFlagManager
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
        $this->carrierFactory = $carrierFactory;
        $this->pricingService = $pricingService;
        $this->featureFlagManager = $featureFlagManager;
    }

    /**
     * @param string $countryId
     * @param string $carrierCode
     * @param string $methodCode
     * @return array
     */
    private function getRateConfig(string $countryId, string $carrierCode, string $methodCode): array {
        $config = [
            'isPacketaRate' => false
        ];

        /** @var \Packetery\Checkout\Model\Carrier\AbstractCarrier $carrier */
        $carrier = $this->carrierFactory->create($carrierCode);

        if (!$carrier instanceof AbstractCarrier) {
            return $config; // rate is not from Packeta
        }

        $methodCodeObject = MethodCode::fromString($methodCode);
        $directionId = $carrier->getPacketeryBrain()->resolvePointId(
            $methodCodeObject->getMethod(),
            $countryId,
            $carrier->getPacketeryBrain()->getDynamicCarrierById($methodCodeObject->getDynamicCarrierId())
        );

        $relatedPricingRule = $this->pricingService->resolvePricingRule(
            $methodCodeObject->getMethod(),
            $countryId,
            $carrierCode,
            $methodCodeObject->getDynamicCarrierId()
        );

        $config['isPacketaRate'] = true;
        $config['directionId'] = $directionId; // for Packeta PP it returns null because it is provided by widget
        $config['addressValidation'] = $relatedPricingRule ? $relatedPricingRule->getAddressValidation() : AddressValidationSelect::NONE;
        $config['isAnyAddressDelivery'] = \Packetery\Checkout\Model\Carrier\Methods::isAnyAddressDelivery($methodCodeObject->getMethod());
        $config['isPickupPointDelivery'] = \Packetery\Checkout\Model\Carrier\Methods::isPickupPointDelivery($methodCodeObject->getMethod());
        $config['widgetVendors'] = [];

        if ($this->featureFlagManager->isSplitActive()) {
            $dynamicCarrier = $carrier->getPacketeryBrain()->getDynamicCarrierById($methodCodeObject->getDynamicCarrierId());
            $config['widgetVendors'] = self::createWidgetVendors([$dynamicCarrier], $relatedPricingRule);
        }

        return $config;
    }

    /**
     * @param AbstractDynamicCarrier[] $dynamicCarriers
     * @return array<int, array>
     */
    public static function createWidgetVendors(array $dynamicCarriers, ?Pricingrule $relatedPricingRule): array {
        $widgetVendors = [];

        if ($relatedPricingRule !== null) {
            $vendorGroups = $relatedPricingRule->getVendorGroups() ?? [];

            foreach ($vendorGroups as $vendorGroup) {
                $widgetVendors[] = self::createWidgetVendor($relatedPricingRule->getCountryId(), $vendorGroup);
            }
        }

        foreach ($dynamicCarriers as $dynamicCarrier) {
            if ($dynamicCarrier instanceof \Packetery\Checkout\Model\Carrier\Imp\Packetery\VendorCarrier) {
                $widgetVendors[] = self::createWidgetVendor($dynamicCarrier->getCountryId(), $dynamicCarrier->getGroup());
            }
        }

        return $widgetVendors;
    }

    /**
     * @return array<string, bool|string>
     */
    private static function createWidgetVendor(string $countryId, string $group): array {
        $widgetVendor = [
            'country' => strtolower($countryId),
            'selected' => true,
        ];

        if ($group !== VendorGroups::ZPOINT) {
            $widgetVendor['group'] = $group;
        }

        return $widgetVendor;
    }

    public function execute() {
        $config = [];
        $ratesConfig = [];
        $postData = json_decode($this->request->getContent(), true);
        $shippingRates = $postData['rates'];

        foreach ($shippingRates as $shippingRate) {
            if (!$this->validateShippingRate($shippingRate)) {
                continue;
            }
            $ratesConfig[$shippingRate['rateCode']] = $this->getRateConfig($shippingRate['countryId'], $shippingRate['carrierCode'], $shippingRate['methodCode']);
        }

        $config['rates'] = $ratesConfig;
        $response = [
            'success' => true,
            'value' => $config,
        ];

        return $this->resultJsonFactory->create()->setData($response);
    }

    private function validateShippingRate(array $shippingRate): bool
    {
        return !empty($shippingRate['countryId']) &&
            !empty($shippingRate['carrierCode']) &&
            !empty($shippingRate['methodCode']);
    }
}
