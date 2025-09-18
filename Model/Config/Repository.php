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
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magmodules\Reloadify\Api\Config\RepositoryInterface as ConfigRepositoryInterface;
use Magmodules\Reloadify\Model\Config\Source\BaseUrl;

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
     * @var Json
     */
    private $serializer;

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
        ProductMetadataInterface $metadata,
        Json $serializer
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->metadata = $metadata;
        $this->serializer = $serializer;
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
    public function getPwaBaseUrl(int $storeId = null): string
    {
        return $this->getStoreValue(self::PWA_BASE_URL, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getPwaCustomUrl(int $storeId = null): string
    {
        return rtrim($this->getStoreValue(self::PWA_CUSTOM_URL, $storeId), '/') . '/';
    }

    /**
     * @inheritDoc
     */
    public function getBaseUrl(int $storeId = null): string
    {
        if ($this->getPwaBaseUrl($storeId) == BaseUrl::PWA) {
            return $this->getPwaCustomUrl($storeId);
        } else {
            return $this->getBaseUrlStore($storeId);
        }
    }

    /**
     * @inheritDoc
     */
    public function isAddStoreCodeToUrl(int $storeId = null): string
    {
        return $this->getStoreValue(self::ADD_STORE_CODE_TO_URL, $storeId);
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

    /**
     * @inheritDoc
     */
    public function getMainImage(int $storeId = null): string
    {
        return (string)$this->getStoreValue(
            self::XML_PATH_MAIN_IMAGE,
            $storeId,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getExtraImage(int $storeId = null): string
    {
        return (string)$this->getStoreValue(
            self::XML_PATH_EXTRA_IMAGE,
            $storeId,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getExtraFields(): array
    {
        $extraFields = [];
        if ($attributes = $this->getStoreValueArray(self::XPATH_EXTRA_FIELDS)) {
            foreach ($attributes as $attribute) {
                $label = 'eav_' . strtolower(str_replace(' ', '_', $attribute['name']));
                if (preg_match('/^rendered_price__/', $attribute['attribute'])) {
                    $extraFields['rendered_price__' . $label] = [
                        'label'  => $label,
                        'price_source' => explode('__', $attribute['attribute'])[1],
                        'actions' => !empty($attribute['actions']) ? [$attribute['actions']] : null,
                    ];
                } else {
                    $extraFields[$label] = [
                        'label'  => $label,
                        'source' => $attribute['attribute'],
                        'actions' => !empty($attribute['actions']) ? [$attribute['actions']] : null,
                    ];
                }
            }
        }

        return $extraFields;
    }

    /**
     * Get Configuration Array data.
     *
     * @param      $path
     * @param null $storeId
     * @param null $scope
     *
     * @return array
     */
    public function getStoreValueArray($path, $storeId = null, $scope = null)
    {
        $value = $this->getStoreValue($path, $storeId, $scope);
        return $this->getValueArray($value);
    }

    /**
     * @param $value
     *
     * @return array
     */
    public function getValueArray($value)
    {
        if (empty($value)) {
            return [];
        }
        return $this->serializer->unserialize($value);
    }

    public function getBaseUrlStore(int $storeId = null): string
    {
        return $this->storeManager->getStore($storeId)->getBaseUrl();
    }
}
