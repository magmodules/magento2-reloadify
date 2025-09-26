<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Api\WebApi;

use Magento\Framework\Api\SearchCriteriaInterface;

/**
 * Feed repository interface
 */
interface RepositoryInterface
{

    /**
     * @return mixed
     */
    public function getSettings(): array;

    /**
     * @param int $entityId
     * @return mixed
     */
    public function getLanguage(int $entityId): array;

    /**
     * @return mixed
     */
    public function getLanguages(): array;

    /**
     * @param int $entityId
     * @return mixed
     */
    public function getProfile(int $entityId): array;

    /**
     * @param int $storeId
     * @param SearchCriteriaInterface|null $searchCriteria
     *
     * @return mixed
     */
    public function getProfiles(int $storeId, ?SearchCriteriaInterface $searchCriteria = null): array;

    /**
     * @param int $storeId
     * @param SearchCriteriaInterface|null $searchCriteria
     *
     * @return mixed
     */
    public function getSubscribers(int $storeId, ?SearchCriteriaInterface $searchCriteria = null): array;

    /**
     * @param int $storeId
     * @param int|null $entityId
     *
     * @return mixed
     */
    public function getProduct(int $storeId, ?int $entityId): array;

    /**
     * @param int $storeId
     * @param SearchCriteriaInterface|null $searchCriteria
     *
     * @return mixed
     */
    public function getProducts(int $storeId, ?SearchCriteriaInterface $searchCriteria = null): array;

    /**
     * @param int $storeId
     * @param SearchCriteriaInterface|null $searchCriteria
     *
     * @return mixed
     */
    public function getProductsDelta(int $storeId, ?SearchCriteriaInterface $searchCriteria = null): array;

    /**
     * @param int $storeId
     * @param int|null $entityId
     * @return mixed
     */
    public function getVariant(int $storeId, ?int $entityId): array;

    /**
     * @param int $storeId
     * @param SearchCriteriaInterface|null $searchCriteria
     *
     * @return mixed
     */
    public function getVariants(int $storeId, ?SearchCriteriaInterface $searchCriteria = null): array;

    /**
     * @param int $entityId
     * @return mixed
     */
    public function getReview(int $entityId): array;

    /**
     * @param int $storeId
     * @param SearchCriteriaInterface|null $searchCriteria
     *
     * @return mixed
     */
    public function getReviews(int $storeId, ?SearchCriteriaInterface $searchCriteria = null): array;

    /**
     * @param int $storeId
     * @param int|null $entityId
     * @return mixed
     */
    public function getCategory(int $storeId, ?int $entityId): array;

    /**
     * @param int $storeId
     * @param SearchCriteriaInterface|null $searchCriteria
     *
     * @return mixed
     */
    public function getCategories(int $storeId, ?SearchCriteriaInterface $searchCriteria = null): array;

    /**
     * @param int $entityId
     * @return mixed
     */
    public function getOrder(int $entityId): array;

    /**
     * @param int $storeId
     * @param SearchCriteriaInterface|null $searchCriteria
     *
     * @return mixed
     */
    public function getOrders(int $storeId, ?SearchCriteriaInterface $searchCriteria = null): array;

    /**
     * @param int $entityId
     * @return mixed
     */
    public function getCart(int $entityId): array;

    /**
     * @param int $storeId
     * @param SearchCriteriaInterface|null $searchCriteria
     * @return mixed
     */
    public function getCarts(int $storeId, ?SearchCriteriaInterface $searchCriteria = null): array;
}
