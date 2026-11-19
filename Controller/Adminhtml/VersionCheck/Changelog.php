<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Controller\Adminhtml\VersionCheck;

use Magento\Backend\App\Action;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magmodules\Reloadify\Api\Config\RepositoryInterface as ConfigRepository;

/**
 * Class Changelog
 *
 * AJAX controller to check latest extension version changelog
 */
class Changelog extends Action
{
    /**
     * Authorization level of a basic admin session
     */
    public const ADMIN_RESOURCE = 'Magmodules_Reloadify::config';

    /**
     * Check constructor.
     *
     * @param Action\Context $context
     * @param JsonFactory $resultJsonFactory
     * @param ConfigRepository $configRepository
     * @param JsonSerializer $json
     * @param File $file
     */
    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ConfigRepository $configRepository,
        private readonly JsonSerializer $json,
        private readonly File $file
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     * @throws FileSystemException
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        $result = $this->getVersions();
        $current = $latest = preg_replace('/^v/', '', $this->configRepository->getExtensionVersion());
        $data = $this->json->unserialize($result);
        $logs = [];
        foreach (is_array($data) ? $data : [] as $version => $log) {
            if (version_compare((string)$current, (string)$version) == -1) {
                $logs[$version] = $log;
            }
        }
        return $resultJson->setData($logs);
    }

    /**
     * @return string
     * @throws FileSystemException
     */
    private function getVersions(): string
    {
        return $this->file->fileGetContents(
            sprintf('https://version.magmodules.eu/%s.json', ConfigRepository::EXTENSION_CODE)
        );
    }
}
