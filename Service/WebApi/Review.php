<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Framework\App\ResourceConnection;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory;
use Magento\Review\Model\ResourceModel\Review\Collection;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;

/**
 * Review web API service class
 */
class Review
{

    /**
     *
     */
    public const DEFAULT_MAP = [
        "id" => 'review_id',
        "name" => 'title',
        "product_id" => 'entity_pk_value',
        "profile_id" => 'customer_id',
        "created_at" => 'created_at',
        "updated_at" => 'created_at'
    ];

    /**
     * @var CollectionFactory
     */
    private $reviewCollectionFactory;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * Product constructor.
     * @param CollectionFactory $reviewCollectionFactory
     * @param ResourceConnection $resourceConnection
     * @param CustomerRepository $customerRepository
     */
    public function __construct(
        CollectionFactory $reviewCollectionFactory,
        ResourceConnection $resourceConnection,
        CustomerRepository $customerRepository
    ) {
        $this->reviewCollectionFactory = $reviewCollectionFactory;
        $this->resourceConnection = $resourceConnection;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @param int $storeId
     * @param array $extra
     * @return array
     */
    public function execute(int $storeId, array $extra = [])
    {
        $reviewCollection = $this->reviewCollectionFactory->create();
        $data = [];
        if ($extra['entity_id']) {
            $reviewCollection->addFieldToFilter('detail.review_id', $extra['entity_id']);
        } else {
            $reviewCollection->addFieldToFilter('detail.store_id', $storeId);
            $reviewCollection = $this->applyFilter($reviewCollection, $extra['filter']);
        }
        /* @var \Magento\Review\Model\Review $review */
        foreach ($reviewCollection as $review) {
            $profile = null;
            if ($review->getCustomerId()) {
                $customer = $this->customerRepository->getById((int)$review->getCustomerId());
                $profile = [
                    'id' => $review->getCustomerId(),
                    'email' => $customer->getEmail()
                ];
            }
            $data[] = [
                "id" => $review->getReviewId(),
                "name" => $review->getTitle(),
                "score" => $this->getScore($review),
                "visible" => ($review->getStatusId() == 1) ? true : false,
                "product_id" => $review->getEntityPkValue(),
                "profile" => $profile,
                "created_at" => $review->getCreatedAt(),
                "updated_at" => $review->getCreatedAt()
            ];
        }
        return $data;
    }

    private function getScore(\Magento\Review\Model\Review $review)
    {
        $resourceConnection = $this->resourceConnection->getConnection();
        $selectRating = $resourceConnection->select()->from(
            ['rating' => $this->resourceConnection->getTableName('rating_option_vote')],
            ['percent']
        )->where('review_id = ?', $review->getReviewId());
        $rating = $resourceConnection->fetchCol($selectRating);
        if (!count($rating)) {
            return 0;
        }
        return array_sum($rating)/count($rating);
    }

    /**
     * @param Collection $reviews
     * @param array $filters
     *
     * @return Collection
     */
    private function applyFilter(Collection $reviews, array $filters)
    {
        foreach ($filters as $field => $filter) {
            $reviews->addFieldToFilter(self::DEFAULT_MAP[$field], $filter);
        }
        return $reviews;
    }
}
