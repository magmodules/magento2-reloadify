<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Customer\Collection;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Profiles web API service class
 */
class Profiles
{

    /**
     * Default attribute map output
     */
    public const DEFAULT_MAP = [
        "id"     => 'entity_id',
        "email"  => 'email',
        "gender" => 'gender',
    ];

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;
    /**
     * @var Subscriber
     */
    private $subscriber;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * Profiles constructor.
     *
     * @param CollectionFactory     $collectionFactory
     * @param Subscriber            $subscriber
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        Subscriber $subscriber,
        StoreManagerInterface $storeManager,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->subscriber = $subscriber;
        $this->storeManager = $storeManager;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @param int   $storeId
     * @param array $extra
     *
     * @return array
     */
    public function execute(int $storeId, array $extra = [], SearchCriteriaInterface $searchCriteria = null): array
    {
        $data = [];
        $collection = $this->getCollection($storeId, $extra, $searchCriteria);

        /* @var Customer $customer */
        foreach ($collection as $customer) {
            $mainData = [
                "id"                       => $customer->getId(),
                "email"                    => $customer->getEmail(),
                "gender"                   => $this->getGender($customer),
                "active"                   => true,
                "subscribed_to_newsletter" => $this->isSubscribed($customer),
                "birthdate"                => $customer->getDob()
            ];

            if ($billing = $customer->getDefaultBillingAddress()) {
                $mainData += [
                    "city"         => $billing->getCity(),
                    "province"     => $billing->getRegion(),
                    "street"       => implode(',', $billing->getStreet()),
                    "zipcode"      => $billing->getPostcode(),
                    "country_code" => $billing->getCountryId(),
                    "first_name"   => $billing->getFirstname(),
                    "middle_name"  => $billing->getMiddlename(),
                    "last_name"    => $billing->getLastname(),
                    "telephone"    => $billing->getTelephone()
                ];
            }

            $data[] = $mainData;
        }

        return $data;
    }

    /**
     * @param int                          $storeId
     * @param array                        $extra
     * @param SearchCriteriaInterface|null $searchCriteria
     *
     * @return Collection
     */
    private function getCollection(
        int $storeId,
        array $extra = [],
        SearchCriteriaInterface $searchCriteria = null
    ): Collection {
        try {
            $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();
        } catch (\Exception $e) {
            $websiteId = null;
        }

        $collection = $this->collectionFactory->create();
        if ($extra['entity_id']) {
            $collection->addFieldToFilter('entity_id', $extra['entity_id']);
        } elseif ($websiteId) {
            $collection->addFieldToFilter('website_id', $websiteId);
            $collection = $this->applyFilter($collection, $extra['filter']);
        }

        if ($searchCriteria !== null) {
            $this->collectionProcessor->process($searchCriteria, $collection);
        }

        return $collection;
    }

    /**
     * @param Collection $customers
     * @param array      $filters
     *
     * @return Collection
     */
    private function applyFilter(Collection $customers, array $filters): Collection
    {
        foreach ($filters as $field => $filter) {
            $customers->addFieldToFilter(self::DEFAULT_MAP[$field], $filter);
        }

        return $customers;
    }

    /**
     * @param $customer
     *
     * @return string
     */
    private function getGender($customer): string
    {
        return $customer->getGender()
            ? (string)$customer->getAttribute('gender')->getSource()->getOptionText($customer->getData('gender'))
            : 'Not Specified';
    }

    /**
     * @param $customer
     *
     * @return bool
     */
    private function isSubscribed($customer): bool
    {
        $subscription = $this->subscriber->loadByCustomerId($customer->getId());
        return $subscription->isSubscribed();
    }
}
