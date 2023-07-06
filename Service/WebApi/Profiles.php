<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Customer\Api\AddressRepositoryInterface as AddressRepository;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollection;

/**
 * Profiles web API service class
 */
class Profiles
{

    /**
     * Default attribute map output
     */
    public const DEFAULT_MAP = [
        "id" => 'entity_id',
        "email" => 'email',
        "gender" => 'gender',
    ];
    /**
     * @var Subscriber
     */
    private $subscriber;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;
    /**
     * @var CustomerResource
     */
    private $customerResource;
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var AddressRepository
     */
    private $addressRepository;

    private $subscriberCollection;

    /**
     * Profiles constructor.
     *
     * @param Subscriber $subscriber
     * @param StoreManagerInterface $storeManager
     * @param CustomerRepositoryInterface $customerRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CustomerResource $customerResource
     * @param AddressRepository $addressRepository
     */
    public function __construct(
        Subscriber $subscriber,
        StoreManagerInterface $storeManager,
        CustomerRepositoryInterface $customerRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CustomerResource $customerResource,
        AddressRepository $addressRepository,
        SubscriberCollection $subscriberCollection
    ) {
        $this->subscriber = $subscriber;
        $this->storeManager = $storeManager;
        $this->customerRepository = $customerRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->customerResource = $customerResource;
        $this->addressRepository = $addressRepository;
        $this->subscriberCollection = $subscriberCollection;
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
        $customers = $this->getCustomers($storeId, $extra, $searchCriteria);
        foreach ($customers as $customer) {
            $mainData = [
                "id" => $customer->getId(),
                "email" => $customer->getEmail(),
                "first_name" => $customer->getFirstname(),
                "middle_name" => $customer->getMiddlename(),
                "last_name" => $customer->getLastname(),
                "gender" => $this->getGender($customer),
                "active" => true,
                "subscribed_to_newsletter" => $this->isSubscribed($customer),
                "birthdate" => $customer->getDob()
            ];

            if ($billingId = $customer->getDefaultBilling()) {
                $billing = $this->addressRepository->getById((int)$billingId);
                $mainData += [
                    "city" => $billing->getCity(),
                    "province" => $billing->getRegion()->getRegion(),
                    "street" => implode(',', $billing->getStreet()),
                    "zipcode" => $billing->getPostcode(),
                    "country_code" => $billing->getCountryId(),
                    "first_name" => $billing->getFirstname(),
                    "middle_name" => $billing->getMiddlename(),
                    "last_name" => $billing->getLastname(),
                    "telephone" => $billing->getTelephone(),
                    "company_name" => $billing->getCompany()
                ];
            }

            $data[] = $mainData;
        }

        $subscribers = $this->subscriberCollection->create()
            ->addFieldToFilter('customer_id', ['eq' => 0]);
        foreach ($subscribers as $subscriber) {
            $data[] = [
                'id' => null,
                'email' => $subscriber->getSubscriberEmail(),
                'subscribed_to_newsletter' => $subscriber->getSubscriberStatus()
            ];
        }

        return $data;
    }

    /**
     * @param int $storeId
     * @param array $extra
     * @param SearchCriteriaInterface|null $searchCriteria
     *
     * @return CustomerInterface[]
     * @throws LocalizedException
     */
    private function getCustomers(
        int $storeId,
        array $extra = [],
        SearchCriteriaInterface $searchCriteria = null
    ): array {
        try {
            $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();
        } catch (\Exception $e) {
            $websiteId = null;
        }
        if ($extra['entity_id']) {
            $this->searchCriteriaBuilder->addFilter('entity_id', $extra['entity_id']);
        } elseif ($websiteId) {
            foreach ($extra['filter'] as $field => $filter) {
                $this->searchCriteriaBuilder->addFilter(self::DEFAULT_MAP[$field], $filter);
            }
            $this->searchCriteriaBuilder->addFilter('website_id', $websiteId);
        }
        $extraSearchCriteria = $this->searchCriteriaBuilder->create();

        if ($searchCriteria !== null) {
            $searchCriteria->setFilterGroups(
                array_merge(
                    $searchCriteria->getFilterGroups(),
                    $extraSearchCriteria->getFilterGroups()
                )
            );
        } else {
            $searchCriteria = $extraSearchCriteria;
        }

        return $this->customerRepository->getList($searchCriteria)->getItems();
    }

    /**
     * @param $customer
     *
     * @return string
     */
    private function getGender($customer): string
    {
        try {
            return $customer->getGender()
                ? (string)$this->customerResource->getAttribute('gender')
                    ->getSource()
                    ->getOptionText($customer->getGender())
                : 'Not Specified';
        } catch (\Exception $exception) {
            return 'Not Specified';
        }
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
