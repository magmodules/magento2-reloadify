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
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Model\StoreManagerInterface;
use Magmodules\Reloadify\Api\Config\RepositoryInterface as ConfigRepository;
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
     * @var array
     */
    private $attributeSourceMap = [];

    /**
     * Profiles constructor.
     *
     * @param Subscriber $subscriber
     * @param StoreManagerInterface $storeManager
     * @param CustomerRepositoryInterface $customerRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CustomerResource $customerResource
     * @param AddressRepository $addressRepository
     * @param LogRepository $logRepository
     * @param GroupCollectionFactory $groupCollectionFactory
     * @param AttributeRepositoryInterface $attributeRepository
     */
    public function __construct(
        private readonly Subscriber $subscriber,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly CustomerResource $customerResource,
        private readonly AddressRepository $addressRepository,
        private readonly LogRepository $logRepository,
        private readonly GroupCollectionFactory $groupCollectionFactory,
        private readonly AttributeRepositoryInterface $attributeRepository,
        private readonly ConfigRepository $configRepository
    ) {
    }

    /**
     * @param int $storeId
     * @param array $extra
     * @param SearchCriteriaInterface|null $searchCriteria
     * @return array
     * @throws LocalizedException
     */
    public function execute(int $storeId, array $extra = [], ?SearchCriteriaInterface $searchCriteria = null): array
    {
        $data = [];
        $customers = $this->getCustomers($storeId, $extra, $searchCriteria);
        $customerGroups = $this->getCustomersGroups();
        $excludedFields = $this->configRepository->getExcludedCustomerFields($storeId);
        foreach ($customers as $customer) {
            $groupId = $customer->getGroupId();
            $mainData = [
                "id" => $customer->getId(),
                "store_id" => $customer->getStoreId(),
                "has_an_account" => true,
                "email" => $customer->getEmail(),
                "first_name" => $customer->getFirstname(),
                "middle_name" => $customer->getMiddlename(),
                "last_name" => $customer->getLastname(),
                "gender" => $this->getGender($customer),
                "active" => true,
                "subscribed_to_newsletter" => $this->isSubscribed($customer),
                "birthdate" => $customer->getDob(),
                "eav_customer_group" => $groupId !== null ? ($customerGroups[$groupId] ?? '') : ''
            ];

            $customAttributes = $customer->getCustomAttributes() ?? [];
            foreach ($customAttributes as $attributeCode => $attribute) {
                $type = $this->getAttributeType($attributeCode);
                $mainData['eav_' . $attributeCode] = $this->getAttributeValue($attribute, $type);
            }

            if ($billingId = $customer->getDefaultBilling()) {
                try {
                    $billing = $this->addressRepository->getById((int)$billingId);
                    $mainData += [
                        "city" => $billing->getCity(),
                        "province" => $billing->getRegion()?->getRegion(),
                        "street" => implode(',', $billing->getStreet() ?? []),
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

            $data[] = $this->removeExcludedFields($mainData, $excludedFields);
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
        ?SearchCriteriaInterface $searchCriteria = null
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
        if (!$customer->getGender()) {
            return 'Not Specified';
        }
        try {
            $attribute = $this->customerResource->getAttribute('gender');
            if (!$attribute) {
                return 'Not Specified';
            }
            return (string)$attribute->getSource()->getOptionText($customer->getGender());
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

    /**
     * Remove excluded fields from profile data
     *
     * @param array $data
     * @param array $excludedFields
     * @return array
     */
    private function removeExcludedFields(array $data, array $excludedFields): array
    {
        foreach ($excludedFields as $field) {
            unset($data[$field]);
        }
        return $data;
    }

    /**
     * @param $attribute
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getAttributeType($attribute): string
    {
        return $this->attributeRepository->get('customer', $attribute)
            ->getFrontendInput();
    }

    /**
     * @param $attribute
     * @return mixed|string
     */
    private function getAttributeValue($attribute, $type)
    {
        $value = '';
        if (!$attribute || !$type) {
            return $value;
        }
        if ($type == 'select' || $type == 'multiselect') {
            try {
                $attributeCode = $attribute->getAttributeCode();
                $attributeSource = $this->getAttributeSource($attributeCode);
                $attributeValue = $attribute->getValue();

                if ($type == 'multiselect') {
                    $value = [];
                    $values = explode(',', $attributeValue);
                    foreach ($values as $singleValue) {
                        $label = $attributeSource->getOptionText($singleValue);
                        if ($label) {
                            $value[] = $label;
                        }
                    }
                    $value = implode(', ', $value);
                } else {
                    $value = $attributeSource->getOptionText($attributeValue);
                }
            } catch (\Exception $e) {
                $value = 'Error retrieving value';
            }
        } else {
            $value = $attribute->getValue();
        }
        return $value;
    }

    /**
     * @param $attributeCode
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getAttributeSource($attributeCode)
    {
        if (!isset($this->attributeSourceMap[$attributeCode])) {
            $this->attributeSourceMap[$attributeCode] =
                $this->attributeRepository->get('customer', $attributeCode)->getSource();
        }
        return $this->attributeSourceMap[$attributeCode];
    }
}
