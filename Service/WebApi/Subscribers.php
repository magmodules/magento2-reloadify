<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollection;

/**
 * Subscribers web API service class
 */
class Subscribers
{
    /**
     * @var SubscriberCollection
     */
    private $subscriberCollection;

    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * Subscribers constructor.
     * @param SubscriberCollection $subscriberCollection
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        SubscriberCollection $subscriberCollection,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->subscriberCollection = $subscriberCollection;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @param int $storeId
     * @param array $extra
     * @param SearchCriteriaInterface|null $searchCriteria
     * @return array
     * @throws LocalizedException
     */
    public function execute(int $storeId, array $extra = [], SearchCriteriaInterface $searchCriteria = null): array
    {
        $data = [];
        $subscribers = $this->subscriberCollection->create();

        if (!empty($extra['entity_id'])) {
            $subscribers->addFieldToFilter('subscriber_id', $extra['entity_id']);
        } else {
            $subscribers->addFieldToFilter('store_id', ['eq' => $storeId]);
        }

        if ($searchCriteria !== null) {
            $this->collectionProcessor->process($searchCriteria, $subscribers);
        }

        foreach ($subscribers as $subscriber) {
            $data[] = [
                'id' => $subscriber->getId(),
                'email' => $subscriber->getSubscriberEmail(),
                'profile_id' => $subscriber->getCustomerId() ? $subscriber->getCustomerId() : null,
                'subscribed_to_newsletter' => $subscriber->getSubscriberStatus(),
                'change_status_at' => $subscriber->getChangeStatusAt()
            ];
        }

        return $data;
    }
}
