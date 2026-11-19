<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Model\RequestLog;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magmodules\Reloadify\Api\RequestLog\Data\DataInterface;
use Magmodules\Reloadify\Api\RequestLog\Data\DataInterfaceFactory;
use Magmodules\Reloadify\Api\RequestLog\Data\SearchResultsInterface;
use Magmodules\Reloadify\Api\RequestLog\Data\SearchResultsInterfaceFactory;
use Magmodules\Reloadify\Api\RequestLog\RepositoryInterface;
use Magmodules\Reloadify\Api\Log\RepositoryInterface as LogRepository;

/**
 * RequestLog Repository class
 */
class Repository implements RepositoryInterface
{
    /**
     * Repository constructor.
     * @param SearchResultsInterfaceFactory $searchResultFactory
     * @param CollectionFactory $collectionFactory
     * @param ResourceModel $resource
     * @param DataInterfaceFactory $dataFactory
     * @param LogRepository $logger
     */
    public function __construct(
        private readonly SearchResultsInterfaceFactory $searchResultFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly ResourceModel $resource,
        private readonly DataInterfaceFactory $dataFactory,
        private readonly LogRepository $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function get(int $entityId) : DataInterface
    {
        if (!$entityId) {
            $exceptionMsg = static::INPUT_EXCEPTION;
            throw new InputException(__($exceptionMsg));
        } elseif (!$this->resource->isExists($entityId)) {
            $exceptionMsg = self::NO_SUCH_ENTITY_EXCEPTION;
            throw new NoSuchEntityException(__($exceptionMsg, $entityId));
        }
        $entity = $this->dataFactory->create();
        if (!$entity instanceof AbstractModel) {
            throw new NoSuchEntityException(__(
                'Unsupported implementation %1.',
                get_class($entity)
            ));
        }
        $this->resource->load($entity, $entityId);
        return $entity;
    }

    /**
     * @inheritDoc
     */
    public function create()
    {
        return $this->dataFactory->create();
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria) : SearchResultsInterface
    {
        /* @var Collection $collection */
        $collection = $this->collectionFactory->create();
        return $this->searchResultFactory->create()
            ->setSearchCriteria($searchCriteria)
            ->setItems($collection->getItems())
            ->setTotalCount($collection->getSize());
    }

    /**
     * @inheritDoc
     */
    public function delete(DataInterface $entity) : bool
    {
        if (!$entity instanceof AbstractModel) {
            throw new CouldNotDeleteException(__(
                'Could not delete the RequestLog: %1',
                __('Unsupported implementation %1.', get_class($entity))
            ));
        }
        try {
            $this->resource->delete($entity);
        } catch (\Exception $exception) {
            $this->logger->addErrorLog('Delete Request Log', $exception->getMessage());
            $exceptionMsg = self::COULD_NOT_DELETE_EXCEPTION;
            throw new CouldNotDeleteException(__(
                $exceptionMsg,
                $exception->getMessage()
            ));
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $entityId): bool
    {
        $entity = $this->get((int)$entityId);
        return $this->delete($entity);
    }

    /**
     * @inheritDoc
     */
    public function save(
        DataInterface $entity
    ) : DataInterface {
        if (!$entity instanceof AbstractModel) {
            throw new CouldNotSaveException(__(
                'Could not save the RequestLog: %1',
                __('Unsupported implementation %1.', get_class($entity))
            ));
        }
        try {
            $this->resource->save($entity);
        } catch (\Exception $exception) {
            $this->logger->addErrorLog('Save Request Log', $exception->getMessage());
            $exceptionMsg = self::COULD_NOT_SAVE_EXCEPTION;
            throw new CouldNotSaveException(__(
                $exceptionMsg,
                $exception->getMessage()
            ));
        }
        return $entity;
    }

    /**
     * @inheritDoc
     */
    public function getByDataSet(array $dataSet, bool $getFirst = false)
    {
        /* @var Collection $collection */
        $collection = $this->collectionFactory->create();
        foreach ($dataSet as $attribute => $value) {
            $collection->addFieldToFilter($attribute, (string)$value);
        }
        if ($getFirst) {
            return $collection->getFirstItem();
        } else {
            return $collection;
        }
    }
}
