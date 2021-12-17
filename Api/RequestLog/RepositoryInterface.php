<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Api\RequestLog;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magmodules\Reloadify\Api\RequestLog\Data\DataInterface;
use Magmodules\Reloadify\Api\RequestLog\Data\SearchResultsInterface;
use Magmodules\Reloadify\Model\RequestLog\Collection;
use Magmodules\Reloadify\Model\RequestLog\DataModel;

/**
 * Interface Repository
 */
interface RepositoryInterface
{
    /**
     * Exception text
     */
    const INPUT_EXCEPTION = 'An ID is needed. Set the ID and try again.';

    /**
     * Exception text
     */
    const NO_SUCH_ENTITY_EXCEPTION = 'The RequestLog with id "%1" does not exist.';

    /**
     * Exception text
     */
    const COULD_NOT_DELETE_EXCEPTION = 'Could not delete the RequestLog: %1';

    /**
     * Exception text
     */
    const COULD_NOT_SAVE_EXCEPTION = 'Could not save the RequestLog: %1';

    /**
     * Loads a specified RequestLog
     *
     * @param int $entityId
     *
     * @return DataInterface
     * @throws LocalizedException
     */
    public function get(int $entityId) : DataInterface;

    /**
     * Return RequestLog object
     *
     * @return DataInterface
     */
    public function create();

    /**
     * Retrieves an RequestLog matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return SearchResultsInterface
     * @throws LocalizedException
     */
    public function getList(SearchCriteriaInterface $searchCriteria) : SearchResultsInterface;

    /**
     * Register entity to delete
     *
     * @param DataInterface $entity
     *
     * @return bool true on success
     * @throws LocalizedException
     */
    public function delete(DataInterface $entity) : bool;

    /**
     * Deletes an RequestLog entity by ID
     *
     * @param int $entityId
     *
     * @return bool true on success
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function deleteById(int $entityId): bool;

    /**
     * Perform persist operations for one entity
     *
     * @param DataInterface $entity
     *
     * @return DataInterface
     * @throws LocalizedException
     */
    public function save(
        DataInterface $entity
    ) : DataInterface;

    /**
     * Get data collection by set of attribute values
     *
     * @param array $dataSet
     * @param bool $getFirst
     *
     * @return Collection|DataModel
     */
    public function getByDataSet(array $dataSet, bool $getFirst = false);
}
