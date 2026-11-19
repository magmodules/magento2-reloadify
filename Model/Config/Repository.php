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
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
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
     * @var array|null
     */
    private ?array $composerData = null;

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ProductMetadataInterface $metadata,
        private readonly Json $serializer,
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly FileDriver $fileDriver
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getExtensionVersion(): string
    {
        return 'v' . (string)($this->getComposerData()['version'] ?? '0.0.0');
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
        ?int $storeId = null,
        ?string $scope = null
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
    public function getStore(?int $storeId = null): StoreInterface
    {
        try {
            return $this->storeManager->getStore($storeId);
        } catch (Exception $e) {
            if ($store = $this->storeManager->getDefaultStoreView()) {
                return $store;
            }
        }
        $stores = $this->storeManager->getStores();
        $store = reset($stores);
        if ($store === false) {
            throw new NoSuchEntityException(__('No store view is available.'));
        }
        return $store;
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
    public function isDebugMode(?int $storeId = null): bool
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
    private function getFlag(string $path, ?int $storeId = null, ?string $scope = null): bool
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
    public function isEnabled(?int $storeId = null): bool
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
        $links = $this->getComposerData()['extra']['magmodules']['links']['docs'] ?? [];
        return (string)($links['en'] ?? '');
    }

    /**
     * @inheritDoc
     */
    public function getPwaBaseUrl(?int $storeId = null): string
    {
        return $this->getStoreValue(self::PWA_BASE_URL, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getPwaCustomUrl(?int $storeId = null): string
    {
        return rtrim($this->getStoreValue(self::PWA_CUSTOM_URL, $storeId), '/') . '/';
    }

    /**
     * @inheritDoc
     */
    public function getBaseUrl(?int $storeId = null): string
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
    public function isAddStoreCodeToUrl(?int $storeId = null): string
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
    public function getEan(?int $storeId = null): string
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
    public function getName(?int $storeId = null): string
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
    public function getSku(?int $storeId = null): string
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
    public function getBrand(?int $storeId = null): string
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
    public function getDescription(?int $storeId = null): string
    {
        return (string)$this->getStoreValue(
            self::XML_PATH_DESCRIPTION,
            $storeId,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getImageVariant(?int $storeId = null): string
    {
        return (string)$this->getStoreValue(
            self::XML_PATH_IMAGE_VARIANT,
            $storeId,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getMainImage(?int $storeId = null): string
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
    public function getExtraImage(?int $storeId = null): string
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
        $result = $this->serializer->unserialize($value);
        return is_array($result) ? $result : [];
    }

    public function getBaseUrlStore(?int $storeId = null): string
    {
        return $this->storeManager->getStore($storeId)->getBaseUrl();
    }

    /**
     * @inheritDoc
     */
    public function getExcludedCustomerFields(?int $storeId = null): array
    {
        $value = $this->getStoreValue(self::XML_PATH_EXCLUDE_CUSTOMER_FIELDS, $storeId);
        if (empty($value)) {
            return [];
        }
        return explode(',', $value);
    }

    /**
     * Read and cache composer.json data for this module.
     *
     * @return array
     */
    private function getComposerData(): array
    {
        if ($this->composerData !== null) {
            return $this->composerData;
        }

        $data = [];
        $path = $this->componentRegistrar->getPath('module', ConfigRepositoryInterface::EXTENSION_CODE);
        if ($path) {
            try {
                $filePath = $path . '/composer.json';
                if ($this->fileDriver->isExists($filePath)) {
                    $data = $this->serializer->unserialize($this->fileDriver->fileGetContents($filePath));
                }
            } catch (\Throwable $e) {
                $data = [];
            }
        }

        $this->composerData = is_array($data) ? $data : [];
        return $this->composerData;
    }
}
