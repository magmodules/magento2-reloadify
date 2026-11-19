<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Controller\Adminhtml\Selftest;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magmodules\Reloadify\Api\Selftest\RepositoryInterface as SelftestRepository;

/**
 * Class index
 *
 * AJAX controller to is provided check result
 */
class Index extends Action
{
    /**
     * Check constructor.
     *
     * @param Action\Context $context
     * @param JsonFactory $resultJsonFactory
     * @param SelftestRepository $selftestRepository
     */
    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly SelftestRepository $selftestRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @return Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        $result = $this->selftestRepository->test();
        return $resultJson->setData(['result' => $result]);
    }
}
