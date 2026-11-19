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
 * Class index
 *
 * AJAX controller to check latest extension version
 */
class Index extends Action
{
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
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        $result = $this->getVersions();
        $current = $latest = preg_replace('/^v/', '', $this->configRepository->getExtensionVersion());
        if ($result) {
            $data = $this->json->unserialize($result);
            $versions = is_array($data) ? array_keys($data) : [];
            if ($versions) {
                $latest = preg_replace('/^v/', '', (string)reset($versions));
            }
        }
        $data = [
            'current_verion' => 'v' . $current,
            'last_version' => 'v' . $latest
        ];
        return $resultJson->setData(['result' => $data]);
    }

    /**
     * @return string
     */
    private function getVersions(): string
    {
        try {
            return $this->file->fileGetContents(
                sprintf('https://version.magmodules.eu/%s.json', ConfigRepository::EXTENSION_CODE)
            );
        } catch (\Exception $exception) {
            return '';
        }
    }
}
