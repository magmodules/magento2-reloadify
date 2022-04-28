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
    public function isEnabled(int $storeId = null): bool;

    /**
     * Check if debug mode is enabled
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isDebugMode(int $storeId = null): bool;

    /**
     * Get EAN attribute
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getEan(int $storeId = null): string;

    /**
     * Get name attribute
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getName(int $storeId = null): string;

    /**
     * Get SKU attribute
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getSku(int $storeId = null): string;

    /**
     * Get brand attribute
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getBrand(int $storeId = null): string;

    /**
     * Get current store
     *
     * @param int|null $storeId
     * @return StoreInterface
     */
    public function getStore(int $storeId = null): StoreInterface;

    /**
     * Support link for extension.
     *
     * @return string
     */
    public function getSupportLink(): string;
}
