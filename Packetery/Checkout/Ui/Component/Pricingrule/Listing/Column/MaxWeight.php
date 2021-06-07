<?php

declare(strict_types=1);

namespace Packetery\Checkout\Ui\Component\Pricingrule\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class MaxWeight extends Column
{
    /** @var \Packetery\Checkout\Model\ResourceModel\Weightrule\CollectionFactory  */
    protected $weightRuleCollectionFactory;

    /**
     * MaxWeight constructor.
     *
     * @param \Magento\Framework\View\Element\UiComponent\ContextInterface $context
     * @param \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory
     * @param \Packetery\Checkout\Model\ResourceModel\Weightrule\CollectionFactory $weightRuleCollectionFactory
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        \Packetery\Checkout\Model\ResourceModel\Weightrule\CollectionFactory $weightRuleCollectionFactory,
        array $components = [],
        array $data = []
    ) {
        $this->weightRuleCollectionFactory = $weightRuleCollectionFactory;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {

                /** @var \Packetery\Checkout\Model\ResourceModel\Weightrule\Collection $collection */
                $collection = $this->weightRuleCollectionFactory->create();
                $collection->addFilter('packetery_pricing_rule_id', $item["id"]);
                $collection->addExpressionFieldToSelect('minMaxWeight', 'MIN(max_weight)', []);
                $collection->addExpressionFieldToSelect('maxMaxWeight', 'MAX(max_weight)', []);
                $collection->load();
                $data = $collection->toArray();
                $row = (isset($data['items']) ? array_shift($data['items']) : null);

                if (!$row || empty($row['maxMaxWeight'])) {
                    $item[$this->getData('name')] = '';
                } else {
                    if ($row['minMaxWeight'] === $row['maxMaxWeight']) {
                        $item[$this->getData('name')] = $row['minMaxWeight'];
                    } else {
                        $item[$this->getData('name')] = "{$row['minMaxWeight']} - {$row['maxMaxWeight']}";
                    }
                }
            }
        }

        return $dataSource;
    }
}