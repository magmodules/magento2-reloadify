<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Review\Model\ResourceModel\Review\Collection;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory;
use Magento\Review\Model\Review as ReviewModel;

/**
 * Review web API service class
 */
class Review
{

    /**
     * Default attribute map output
     */
    public const DEFAULT_MAP = [
        "id"         => 'review_id',
        "name"       => 'title',
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
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * Product constructor.
     *
     * @param CollectionFactory  $reviewCollectionFactory
     * @param ResourceConnection $resourceConnection
     * @param CustomerRepository $customerRepository
     */
    public function __construct(
        CollectionFactory $reviewCollectionFactory,
        ResourceConnection $resourceConnection,
        CustomerRepository $customerRepository,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->reviewCollectionFactory = $reviewCollectionFactory;
        $this->resourceConnection = $resourceConnection;
        $this->customerRepository = $customerRepository;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @param int   $storeId
     * @param array $extra
     *
     * @return array
     */
    public function execute(int $storeId, array $extra = [], SearchCriteriaInterface $searchCriteria = null)
    {
        $data = [];
        $collection = $this->getCollection($storeId, $extra, $searchCriteria);

        foreach ($collection as $review) {
            $data[] = [
                "id"         => $review->getReviewId(),
                "name"       => $review->getTitle(),
                "score"      => $this->getScore($review),
                "visible"    => $review->getStatusId() == 1,
                "product_id" => $review->getEntityPkValue(),
                "profile"    => $this->getProfileData($review),
                "created_at" => $review->getCreatedAt(),
                "updated_at" => $review->getCreatedAt()
            ];
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
    private function getCollection($storeId, $extra, $searchCriteria): Collection
    {
        $collection = $this->reviewCollectionFactory->create();
        if ($extra['entity_id']) {
            $collection->addFieldToFilter('detail.review_id', $extra['entity_id']);
        } else {
            $collection->addFieldToFilter('detail.store_id', $storeId);
            $collection = $this->applyFilter($collection, $extra['filter']);
        }

        if ($searchCriteria !== null) {
            $this->collectionProcessor->process($searchCriteria, $collection);
        }

        return $collection;
    }

    /**
     * @param Collection $reviews
     * @param array      $filters
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

    /**
     * @param ReviewModel $review
     *
     * @return float|int
     */
    private function getScore(ReviewModel $review)
    {
        $resourceConnection = $this->resourceConnection->getConnection();
        $selectRating = $resourceConnection->select()->from(
            ['rating' => $this->resourceConnection->getTableName('rating_option_vote')],
            ['percent']
        )->where('review_id = ?', $review->getReviewId());
        $rating = $resourceConnection->fetchCol($selectRating);

        return !count($rating) ? 0 : array_sum($rating) / count($rating);
    }

    /**
     * @param $review
     *
     * @return array|null
     */
    private function getProfileData($review): ?array
    {
        try {
            if ($review->getCustomerId()) {
                $customer = $this->customerRepository->getById((int)$review->getCustomerId());
                return [
                    'id'    => $review->getCustomerId(),
                    'email' => $customer->getEmail()
                ];
            }
        } catch (\Exception $exception) {
            return null;
        }

        return null;
    }
}
