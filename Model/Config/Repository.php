<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Model\Config;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magmodules\Reloadify\Api\Config\RepositoryInterface as ConfigRepositoryInterface;

/**
 * Config repository class
 */
class Repository implements ConfigRepositoryInterface
{

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var ProductMetadataInterface
     */
    private $metadata;

    /**
     * Repository constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param ProductMetadataInterface $metadata
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        ProductMetadataInterface $metadata
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->metadata = $metadata;
    }

    /**
     * {@inheritDoc}
     */
    public function getExtensionVersion(): string
    {
        return $this->getStoreValue(self::XML_PATH_EXTENSION_VERSION);
    }

    /**
     * Get Configuration data
     *
     * @param string $path
     * @param int|null $storeId
     * @param string|null $scope
     *
     * @return string
     */
    private function getStoreValue(
        string $path,
        int $storeId = null,
        string $scope = null
    ): string {
        if (!$storeId) {
            $storeId = (int)$this->getStore()->getId();
        }
        return (string)$this->scopeConfig->getValue(
            sprintf($path, self::XML_PATH_PREFIX),
            $scope ?? ScopeInterface::SCOPE_STORE,
            (int)$storeId
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getStore(int $storeId = null): StoreInterface
    {
        try {
            return $this->storeManager->getStore($storeId);
        } catch (Exception $e) {
            if ($store = $this->storeManager->getDefaultStoreView()) {
                return $store;
            }
        }
        $stores = $this->storeManager->getStores();
        return reset($stores);
    }

    /**
     * {@inheritDoc}
     */

    public function getMagentoVersion(): string
    {
        return $this->metadata->getVersion();
    }

    /**
     * @inheritDoc
     */
    public function isDebugMode(int $storeId = null): bool
    {
        return $this->getFlag(
            self::XML_PATH_DEBUG,
            $storeId,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get config value flag
     *
     * @param string $path
     * @param int|null $storeId
     * @param string|null $scope
     *
     * @return bool
     */
    private function getFlag(string $path, int $storeId = null, string $scope = null): bool
    {
        if (!$storeId) {
            $storeId = (int)$this->getStore()->getId();
        }
        return $this->scopeConfig->isSetFlag(
            sprintf($path, self::XML_PATH_PREFIX),
            $scope ?? ScopeInterface::SCOPE_STORE,
            (int)$storeId
        );
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_EXTENSION_ENABLE, $storeId);
    }

    /**
     * Support link for extension.
     *
     * @return string
     */
    public function getSupportLink(): string
    {
        return sprintf(
            self::MODULE_SUPPORT_LINK,
            $this->getExtensionCode()
        );
    }

    /**
     * @inheritDoc
     */
    public function getExtensionCode(): string
    {
        return self::EXTENSION_CODE;
    }

    /**
     * @inheritDoc
     */
    public function getEan(int $storeId = null): string
    {
        return (string)$this->getStoreValue(
            self::XML_PATH_EAN,
            $storeId,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getName(int $storeId = null): string
    {
        return (string)$this->getStoreValue(
            self::XML_PATH_NAME,
            $storeId,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getSku(int $storeId = null): string
    {
        return (string)$this->getStoreValue(
            self::XML_PATH_SKU,
            $storeId,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getBrand(int $storeId = null): string
    {
        return (string)$this->getStoreValue(
            self::XML_PATH_BRAND,
            $storeId,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getDescription(int $storeId = null): string
    {
        return (string)$this->getStoreValue(
            self::XML_PATH_DESCRIPTION,
            $storeId,
            ScopeInterface::SCOPE_STORE
        );
    }
}
