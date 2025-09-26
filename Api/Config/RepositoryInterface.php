<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Api\Config;

use Magento\Store\Api\Data\StoreInterface;

/**
 * Config repository interface
 */
interface RepositoryInterface
{

    public const EXTENSION_CODE = 'Magmodules_Reloadify';
    public const XML_PATH_PREFIX = 'magmodules_reloadify';
    public const MODULE_SUPPORT_LINK = 'https://www.magmodules.eu/help/%s';

    /* General */
    public const XML_PATH_EXTENSION_VERSION = '%s/general/version';
    public const XML_PATH_EXTENSION_ENABLE = '%s/general/enable';
    public const XML_PATH_DEBUG = '%s/general/debug';
    public const XML_PATH_EAN = '%s/attributes/ean';
    public const XML_PATH_NAME = '%s/attributes/name';
    public const XML_PATH_SKU = '%s/attributes/sku';
    public const XML_PATH_BRAND = '%s/attributes/brand';
    public const XML_PATH_DESCRIPTION = '%s/attributes/description';
    public const XML_PATH_MAIN_IMAGE = '%s/attributes/main_image';
    public const XML_PATH_EXTRA_IMAGE = '%s/attributes/extra_image';
    public const XPATH_EXTRA_FIELDS = '%s/attributes/extra_fields';
    public const XML_PATH_IMAGE_VARIANT = '%s/attributes/image';

    public const PWA_BASE_URL = '%s/pwa/base_url';
    public const PWA_CUSTOM_URL = '%s/pwa/custom_url';

    public const ADD_STORE_CODE_TO_URL = 'web/url/use_store';

    /**
     * Get extension version
     *
     * @return string
     */
    public function getExtensionVersion(): string;

    /**
     * Get extension code
     *
     * @return string
     */
    public function getExtensionCode(): string;

    /**
     * Get Magento Version
     *
     * @return string
     */
    public function getMagentoVersion(): string;

    /**
     * Check if module is enabled
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool;

    /**
     * Check if debug mode is enabled
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isDebugMode(?int $storeId = null): bool;

    /**
     * Get EAN attribute
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getEan(?int $storeId = null): string;

    /**
     * Get name attribute
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getName(?int $storeId = null): string;

    /**
     * Get SKU attribute
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getSku(?int $storeId = null): string;

    /**
     * Get brand attribute
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getBrand(?int $storeId = null): string;

    /**
     * Get description attribute
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getDescription(?int $storeId = null): string;

    /**
     * Get main image attribute
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getMainImage(?int $storeId = null): string;

    /**
     * Get extra image attribute
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getExtraImage(?int $storeId = null): string;

    /**
     * Get image variant attribute
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getImageVariant(?int $storeId = null): string;

    /**
     * Get extra fields
     *
     * @return array
     */
    public function getExtraFields(): array;

    /**
     * Get current store
     *
     * @param int|null $storeId
     * @return StoreInterface
     */
    public function getStore(?int $storeId = null): StoreInterface;

    /**
     * Support link for extension.
     *
     * @return string
     */
    public function getSupportLink(): string;

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getPwaBaseUrl(?int $storeId = null): string;

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getPwaCustomUrl(?int $storeId = null): string;

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getBaseUrl(?int $storeId = null): string;

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getBaseUrlStore(?int $storeId = null): string;

    /**
     * @param int|null $storeId
     * @return string
     */
    public function isAddStoreCodeToUrl(?int $storeId = null): string;
}
