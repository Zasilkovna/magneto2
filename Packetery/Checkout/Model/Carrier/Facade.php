<?php

declare(strict_types=1);

namespace Packetery\Checkout\Model\Carrier;

use Packetery\Checkout\Model\HybridCarrier;

class Facade
{
    /** @var \Packetery\Checkout\Model\ResourceModel\Carrier\CollectionFactory */
    private $dynamicCarrierCollectionFactory;

    /** @var \Magento\Shipping\Model\CarrierFactory */
    private $carrierFactory;

    /**
     * @param \Packetery\Checkout\Model\ResourceModel\Carrier\CollectionFactory $dynamicCarrierCollectionFactory
     * @param \Magento\Shipping\Model\CarrierFactory $carrierFactory
     */
    public function __construct(\Packetery\Checkout\Model\ResourceModel\Carrier\CollectionFactory $dynamicCarrierCollectionFactory, \Magento\Shipping\Model\CarrierFactory $carrierFactory) {
        $this->dynamicCarrierCollectionFactory = $dynamicCarrierCollectionFactory;
        $this->carrierFactory = $carrierFactory;
    }

    /**
     * @param string $carrierName
     * @param string $carrierCode
     * @param int|null $carrierId
     * @throws \Exception
     */
    public function updateCarrierName(string $carrierName, string $carrierCode, ?int $carrierId = null): void {
        $this->assertValidIdentifiers($carrierCode, $carrierId);

        if ($this->isDynamicCarrier($carrierCode, $carrierId)) {
            $dynamicCarrier = $this->getDynamicCarrier($carrierId);
            $dynamicCarrier->setData('carrier_name', $carrierName);
            $dynamicCarrier->save();
            return;
        }

        throw new \InvalidArgumentException('Not implemented');
    }

    /**
     * @param string $carrierCode
     * @param $carrierId
     * @throws \Exception
     */
    private function assertValidIdentifiers(string $carrierCode, $carrierId): void {
        $isDynamicWrapper = $carrierCode === \Packetery\Checkout\Model\Carrier\Imp\PacketeryPacketaDynamic\Brain::getCarrierCodeStatic();
        if ($isDynamicWrapper && is_numeric($carrierId)) {
            return;
        }

        if (is_numeric($carrierId)) {
            throw new \Exception('Invalid identifiers. Missing or unexpected code.');
        }
    }

    /**
     * @param string $carrierCode
     * @param int|null $carrierId
     * @param string $method
     * @param string $country
     * @return \Packetery\Checkout\Model\HybridCarrier
     */
    public function createHybridCarrier(string $carrierCode, ?int $carrierId, string $method, string $country): HybridCarrier {
        if ($this->isDynamicCarrier($carrierCode, $carrierId)) {
            return HybridCarrier::fromDynamic($this->getDynamicCarrier($carrierId));
        }

        return HybridCarrier::fromAbstract($this->getMagentoCarrier($carrierCode), $method, $country);
    }

    /**
     * @param string $carrierCode
     * @param $carrierId
     * @return bool
     */
    public function isDynamicCarrier(string $carrierCode, $carrierId): bool {
        $isDynamicWrapper = $carrierCode === \Packetery\Checkout\Model\Carrier\Imp\PacketeryPacketaDynamic\Brain::getCarrierCodeStatic();
        if ($isDynamicWrapper && is_numeric($carrierId)) {
            return true;
        }

        return false;
    }

    /**
     * @param int $carrierId
     * @return \Packetery\Checkout\Model\Carrier
     */
    private function getDynamicCarrier(int $carrierId): \Packetery\Checkout\Model\Carrier {
        return $this->dynamicCarrierCollectionFactory->create()->getItemByColumnValue('carrier_id', $carrierId);
    }

    /**
     * @param string $carrierCode
     * @return \Packetery\Checkout\Model\Carrier\AbstractCarrier
     */
    private function getMagentoCarrier(string $carrierCode): \Packetery\Checkout\Model\Carrier\AbstractCarrier {
        return $this->carrierFactory->get($carrierCode);
    }
}
