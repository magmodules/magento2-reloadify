<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\Test;

use Exception;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magmodules\Reloadify\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Reloadify\Api\Log\RepositoryInterface as LogRepository;

/**
 * Extension version version test class
 */
class ExtensionVersion
{
    /**
     * Test type
     */
    public const TYPE = 'extension_version';

    /**
     * Test description
     */
    public const TEST = 'Check if new extension version is available';

    /**
     * Visibility
     */
    public const VISIBLE = true;

    /**
     * Message on test success
     */
    public const SUCCESS_MSG = 'Great, you are using the latest version.';

    /**
     * Message on test failed
     */
    public const FAILED_MSG = 'Version %s is available, current version %s';

    /**
     * Expected result
     */
    public const EXPECTED = '0';

    /**
     * Link to get support
     */
    public const SUPPORT_LINK = 'https://www.magmodules.eu/help/magento2/update-extension.html';

    /**
     * ExtensionVersion constructor.
     *
     * @param ConfigRepository $configRepository
     * @param LogRepository $logRepository
     * @param JsonSerializer $json
     * @param File $file
     */
    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly LogRepository $logRepository,
        private readonly JsonSerializer $json,
        private readonly File $file
    ) {
    }

    /**
     * @return array
     */
    public function execute()
    {
        $result = [
            'type' => self::TYPE,
            'test' => self::TEST,
            'visible' => self::VISIBLE
        ];
        $extensionVersion = ltrim($this->configRepository->getExtensionVersion(), 'v');
        try {
            $data = $this->file->fileGetContents(
                sprintf('https://version.magmodules.eu/%s.json', ConfigRepository::EXTENSION_CODE)
            );
        } catch (Exception $e) {
            $this->logRepository->addDebugLog('Extension version test', $e->getMessage());
            $result['result_msg'] = self::SUCCESS_MSG;
            $result +=
                [
                    'result_code' => 'success',
                ];
            return $result;
        }
        $data = $this->json->unserialize($data);
        $versions = is_array($data) ? array_keys($data) : [];
        if (!$versions) {
            $this->logRepository->addDebugLog('Extension version test', 'No versions in the version feed.');
            $result['result_msg'] = self::SUCCESS_MSG;
            $result += ['result_code' => 'success'];
            return $result;
        }
        $latest = ltrim((string)reset($versions), 'v');

        if (version_compare($latest, $extensionVersion) == self::EXPECTED) {
            $result['result_msg'] = self::SUCCESS_MSG;
            $result +=
                [
                    'result_code' => 'success'
                ];
        } else {
            $result['result_msg'] = sprintf(
                self::FAILED_MSG,
                'v' . $latest,
                'v' . $extensionVersion
            );
            $result +=
                [
                    'result_code' => 'failed',
                    'support_link' => self::SUPPORT_LINK
                ];
        }
        return $result;
    }
}
