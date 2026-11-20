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
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magmodules\Reloadify\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Reloadify\Api\Log\RepositoryInterface as LogRepository;

/**
 * Class Changelog
 *
 * AJAX controller to check latest extension version changelog
 */
class Changelog extends Action
{
    /**
     * Without this the controller falls back to Magento_Backend::admin, which every admin
     * role carries, so access could not be granted per role.
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
     * @param LogRepository $logRepository
     */
    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ConfigRepository $configRepository,
        private readonly JsonSerializer $json,
        private readonly File $file,
        private readonly LogRepository $logRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $body = $this->getVersions();
        } catch (\Throwable $exception) {
            // version.magmodules.eu sits on the far side of the merchant's firewall, and a version
            // that is not published yet answers with a 404. Neither is a defect of this module, so
            // neither belongs in the error log; the modal tells the merchant either way.
            $this->logRepository->addDebugLog('VersionCheck changelog', $exception->getMessage());

            return $resultJson->setData($this->unavailable());
        }

        try {
            $data = $this->json->unserialize($body);
        } catch (\Throwable $exception) {
            $data = null;
        }

        if (!is_array($data)) {
            // The service answered, and with something that is not a changelog. That is an anomaly
            // on our side, not a shop without outbound access.
            $this->logRepository->addErrorLog(
                'VersionCheck changelog',
                'The version service did not answer with a changelog.'
            );

            return $resultJson->setData($this->unavailable());
        }

        $current = preg_replace('/^v/', '', $this->configRepository->getExtensionVersion());
        $logs = [];
        foreach ($data as $version => $log) {
            if (version_compare((string)$current, (string)$version) === -1) {
                $logs[$version] = $log;
            }
        }

        return $resultJson->setData($logs);
    }

    /**
     * What the modal shows when there is no changelog to show.
     *
     * @return array
     */
    private function unavailable(): array
    {
        return [
            (string)__('Unavailable') => [
                'date' => '',
                'changelog' => (string)__('The changelog could not be retrieved.'),
            ],
        ];
    }

    /**
     * @return string
     */
    private function getVersions(): string
    {
        return $this->file->fileGetContents(
            sprintf('https://version.magmodules.eu/%s.json', ConfigRepository::EXTENSION_CODE)
        );
    }
}
