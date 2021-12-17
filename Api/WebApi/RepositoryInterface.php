<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Api\WebApi;

/**
 * Feed repository interface
 */
interface RepositoryInterface
{

    /**
     * @return array
     */
    public function getLanguages(): array;

    /**
     * @param int $entityId
     * @return array
     */
    public function getLanguage(int $entityId): array;

    /**
     * @param int $storeId
     * @return array
     */
    public function getProfiles(int $storeId): array;

    /**
     * @param int $entityId
     * @return array
     */
    public function getProfile(int $entityId): array;

    /**
     * @param int $storeId
     * @return array
     */
    public function getProductsDelta(int $storeId): array;

    /**
     * @param int $storeId
     * @return array
     */
    public function getProducts(int $storeId): array;

    /**
     * @param int $storeId
     * @param int $entityId
     * @return array
     */
    public function getProduct(int $storeId, int $entityId = null): array;

    /**
     * @param int $storeId
     * @return array
     */
    public function getVariants(int $storeId): array;

    /**
     * @param int $storeId
     * @param int $entityId
     * @return array
     */
    public function getVariant(int $storeId, int $entityId = null): array;

    /**
     * @param int $storeId
     * @return array
     */
    public function getReviews(int $storeId): array;

    /**
     * @param int $entityId
     * @return array
     */
    public function getReview(int $entityId): array;

    /**
     * @param int $storeId
     * @return array
     */
    public function getCategories(int $storeId): array;

    /**
     * @param int $storeId
     * @param int $entityId
     * @return array
     */
    public function getCategory(int $storeId, int $entityId = null): array;

    /**
     * @param int $storeId
     * @return array
     */
    public function getOrders(int $storeId): array;

    /**
     * @param int $entityId
     * @return array
     */
    public function getOrder(int $entityId): array;

    /**
     * @param int $storeId
     * @return array
     */
    public function getCarts(int $storeId): array;

    /**
     * @param int $entityId
     * @return array
     */
    public function getCart(int $entityId): array;
}
