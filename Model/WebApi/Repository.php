<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Model\WebApi;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magmodules\Reloadify\Api\WebApi\RepositoryInterface;
use Magmodules\Reloadify\Service\WebApi\Cart;
use Magmodules\Reloadify\Service\WebApi\Category;
use Magmodules\Reloadify\Service\WebApi\Language;
use Magmodules\Reloadify\Service\WebApi\Order;
use Magmodules\Reloadify\Service\WebApi\Product;
use Magmodules\Reloadify\Service\WebApi\Profiles;
use Magmodules\Reloadify\Service\WebApi\Subscribers;
use Magmodules\Reloadify\Service\WebApi\Review;
use Magmodules\Reloadify\Service\WebApi\Settings;
use Magmodules\Reloadify\Service\WebApi\Variants;

/**
 * Web API repository class
 */
class Repository implements RepositoryInterface
{

    /**
     * @var Category
     */
    private $category;
    /**
     * @var Product
     */
    private $product;
    /**
     * @var Language
     */
    private $language;
    /**
     * @var Profiles
     */
    private $profiles;
    /**
     * @var Subscribers
     */
    private $subscribers;
    /**
     * @var Order
     */
    private $order;
    /**
     * @var Cart
     */
    private $cart;
    /**
     * @var Review
     */
    private $review;
    /**
     * @var Variants
     */
    private $variants;
    /**
     * @var Settings
     */
    private $settings;
    /**
     * @var RequestInterface
     */
    private $request;
    /**
     * @var Json
     */
    private $json;

    /**
     * Repository constructor.
     *
     * @param Category $category
     * @param Product $product
     * @param Language $language
     * @param Profiles $profiles
     * @param Order $order
     * @param Cart $cart
     * @param Review $review
     * @param Variants $variants
     * @param Settings $settings
     * @param RequestInterface $requestInterface
     * @param Json $json
     */
    public function __construct(
        Category $category,
        Product $product,
        Language $language,
        Profiles $profiles,
        Subscribers $subscribers,
        Order $order,
        Cart $cart,
        Review $review,
        Variants $variants,
        Settings $settings,
        RequestInterface $requestInterface,
        Json $json
    ) {
        $this->category = $category;
        $this->product = $product;
        $this->language = $language;
        $this->profiles = $profiles;
        $this->subscribers = $subscribers;
        $this->order = $order;
        $this->cart = $cart;
        $this->review = $review;
        $this->variants = $variants;
        $this->settings = $settings;
        $this->request = $requestInterface;
        $this->json = $json;
    }

    /**
     * @inheritDoc
     */
    public function getSettings(): array
    {
        return $this->settings->execute();
    }

    /**
     * @inheritDoc
     */
    public function getLanguage(int $entityId): array
    {
        return $this->language->execute($entityId);
    }

    /**
     * @inheritDoc
     */
    public function getLanguages(): array
    {
        return $this->language->execute();
    }

    /**
     * @inheritDoc
     */
    public function getProfile(int $entityId): array
    {
        return $this->profiles->execute(0, ['entity_id' => $entityId, 'filter' => []]);
    }

    /**
     * @inheritDoc
     */
    public function getProfiles(int $storeId, ?SearchCriteriaInterface $searchCriteria = null): array
    {
        try {
            $filter = $this->json->unserialize(urldecode((string)$this->request->getParam('filter')));
        } catch (\Exception $exception) {
            $filter = [];
        }
        return $this->profiles->execute($storeId, ['entity_id' => null, 'filter' => $filter], $searchCriteria);
    }

    /**
     * @inheritDoc
     */
    public function getSubscribers(int $storeId, ?SearchCriteriaInterface $searchCriteria = null): array
    {
        try {
            $filter = $this->json->unserialize(urldecode((string)$this->request->getParam('filter')));
        } catch (\Exception $exception) {
            $filter = [];
        }
        return $this->subscribers->execute($storeId, ['filter' => $filter], $searchCriteria);
    }

    /**
     * @inheritDoc
     */
    public function getProduct(int $storeId, int $entityId = null): array
    {
        return $this->product->execute($storeId, ['entity_id' => $entityId, 'filter' => []]);
    }

    /**
     * @inheritDoc
     */
    public function getProducts(int $storeId, ?SearchCriteriaInterface $searchCriteria = null): array
    {
        try {
            $filter = $this->json->unserialize(urldecode((string)$this->request->getParam('filter')));
        } catch (\Exception $exception) {
            $filter = [];
        }
        return $this->product->execute($storeId, ['entity_id' => null, 'filter' => $filter], $searchCriteria);
    }

    /**
     * @inheritDoc
     */
    public function getProductsDelta(int $storeId, ?SearchCriteriaInterface $searchCriteria = null): array
    {
        return $this->product->execute($storeId, ['entity_id' => null, 'filter' => ['delta']], $searchCriteria);
    }

    /**
     * @inheritDoc
     */
    public function getVariant(int $storeId, int $entityId = null): array
    {
        return $this->variants->execute($storeId, ['entity_id' => $entityId, 'filter' => []]);
    }

    /**
     * @inheritDoc
     */
    public function getVariants(int $storeId, ?SearchCriteriaInterface $searchCriteria = null): array
    {
        try {
            $filter = $this->json->unserialize(urldecode((string)$this->request->getParam('filter')));
        } catch (\Exception $exception) {
            $filter = [];
        }
        return $this->variants->execute($storeId, ['entity_id' => null, 'filter' => $filter], $searchCriteria);
    }

    /**
     * @inheritDoc
     */
    public function getReview(int $entityId): array
    {
        return $this->review->execute(0, ['entity_id' => $entityId, 'filter' => []]);
    }

    /**
     * @inheritDoc
     */
    public function getReviews(int $storeId, ?SearchCriteriaInterface $searchCriteria = null): array
    {
        try {
            $filter = $this->json->unserialize(urldecode((string)$this->request->getParam('filter')));
        } catch (\Exception $exception) {
            $filter = [];
        }
        return $this->review->execute($storeId, ['entity_id' => null, 'filter' => $filter], $searchCriteria);
    }

    /**
     * @inheritDoc
     */
    public function getCategory(int $storeId, int $entityId = null): array
    {
        return $this->category->execute($storeId, ['entity_id' => $entityId, 'filter' => []]);
    }

    /**
     * @inheritDoc
     */
    public function getCategories(int $storeId, ?SearchCriteriaInterface $searchCriteria = null): array
    {
        try {
            $filter = $this->json->unserialize(urldecode((string)$this->request->getParam('filter')));
        } catch (\Exception $exception) {
            $filter = [];
        }
        return $this->category->execute($storeId, ['entity_id' => null, 'filter' => $filter], $searchCriteria);
    }

    /**
     * @inheritDoc
     */
    public function getOrder(int $entityId): array
    {
        return $this->order->execute(0, ['entity_id' => $entityId, 'filter' => null]);
    }

    /**
     * @inheritDoc
     */
    public function getOrders(int $storeId, ?SearchCriteriaInterface $searchCriteria = null): array
    {
        try {
            $filter = $this->json->unserialize(urldecode((string)$this->request->getParam('filter')));
        } catch (\Exception $exception) {
            $filter = [];
        }
        return $this->order->execute($storeId, ['entity_id' => null, 'filter' => $filter], $searchCriteria);
    }

    /**
     * @inheritDoc
     */
    public function getCart(int $entityId): array
    {
        return $this->cart->execute(0, ['entity_id' => $entityId]);
    }

    /**
     * @inheritDoc
     */
    public function getCarts(int $storeId, ?SearchCriteriaInterface $searchCriteria = null): array
    {
        try {
            $filter = $this->json->unserialize(urldecode((string)$this->request->getParam('filter')));
        } catch (\Exception $exception) {
            $filter = [];
        }
        return $this->cart->execute($storeId, ['entity_id' => null, 'filter' => $filter], $searchCriteria);
    }

    /**
     * @inheritDoc
     */
    public function restore(string $encryptedId): CartInterface
    {
        $quoteId = $this->encryptor->decrypt($encryptedId);
        $quote = $this->quoteRepository->get($quoteId);
        $this->checkoutSession->replaceQuote($quote);

        return $quote;
    }
}
