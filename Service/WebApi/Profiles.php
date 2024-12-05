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
use Magmodules\Reloadify\Api\Log\RepositoryInterface as LogRepository;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;

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
    /**
     * @var LogRepository
     */
    private $logRepository;

    private $groupCollectionFactory;

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
        LogRepository $logRepository,
        GroupCollectionFactory $groupCollectionFactory
    ) {
        $this->subscriber = $subscriber;
        $this->storeManager = $storeManager;
        $this->customerRepository = $customerRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->customerResource = $customerResource;
        $this->addressRepository = $addressRepository;
        $this->logRepository = $logRepository;
        $this->groupCollectionFactory = $groupCollectionFactory;
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
        $customerGroups = $this->getCustomersGroups();
        foreach ($customers as $customer) {
            $mainData = [
                "id" => $customer->getId(),
                "store_id" => $customer->getStoreId(),
                "email" => $customer->getEmail(),
                "first_name" => $customer->getFirstname(),
                "middle_name" => $customer->getMiddlename(),
                "last_name" => $customer->getLastname(),
                "gender" => $this->getGender($customer),
                "active" => true,
                "subscribed_to_newsletter" => $this->isSubscribed($customer),
                "birthdate" => $customer->getDob(),
                "eav_customer_group" => $customerGroups[$customer->getGroupId()] ?? ''
            ];

            if ($billingId = $customer->getDefaultBilling()) {
                try {
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
                } catch (\Exception $e) {
                    $this->logRepository->addErrorLog('get profiles', $e->getMessage());
                }
            }

            $data[] = $mainData;
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

    /**
     * Get all customer groups as array ['group_id' => 'code']
     *
     * @return array
     */
    private function getCustomersGroups(): array
    {
        $customerGroups = [];
        $groupCollection = $this->groupCollectionFactory->create();
        foreach ($groupCollection as $group) {
            $customerGroups[$group->getId()] = $group->getCustomerGroupCode();
        }
        return $customerGroups;
    }
}
