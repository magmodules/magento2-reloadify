<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 *
 * E2E fixture helper for what Magento's REST API cannot do: reviews with a rating vote, a guest
 * newsletter subscriber, an integration without the Reloadify resource, quote flags, configuration
 * writes and the request log row count.
 *
 * Copied into the Magento root's var/ directory by global-setup.ts and run from the CLI only:
 *   php var/reloadify-e2e-fixtures.php <command> <base64 encoded JSON payload>
 * The last line of the output is the JSON result.
 */
declare(strict_types=1);

// CLI-only test fixture, never shipped: it reads its arguments from argv and exits with a status.
// phpcs:disable

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Integration\Api\AuthorizationServiceInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Api\OauthServiceInterface;
use Magento\Newsletter\Model\SubscriptionManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Review\Model\Rating;
use Magento\Review\Model\RatingFactory;
use Magento\Review\Model\ResourceModel\Rating\Collection as RatingCollection;
use Magento\Review\Model\ResourceModel\Rating\Option\CollectionFactory as RatingOptionCollectionFactory;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;

require getcwd() . '/app/bootstrap.php';

$command = $argv[1] ?? '';
$payload = json_decode(base64_decode($argv[2] ?? '') ?: '{}', true) ?: [];

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$objectManager->get(State::class)->setAreaCode('adminhtml');

$cleanConfigCache = static function () use ($objectManager): void {
    $objectManager->get(TypeListInterface::class)->cleanType('config');
    $objectManager->get(\Magento\Framework\App\Config\ReinitableConfigInterface::class)->reinit();
};

try {
    switch ($command) {
        case 'integration-without-access':
            // An integration that exists and has a valid token, but not Magmodules_Reloadify::webapi
            $integrationService = $objectManager->get(IntegrationServiceInterface::class);
            $oauthService = $objectManager->get(OauthServiceInterface::class);
            $integration = $integrationService->findByName($payload['name']);
            if (!$integration->getId()) {
                $integration = $integrationService->create([
                    'name' => $payload['name'],
                    'status' => '1',
                    'setup_type' => '0',
                ]);
            }
            $objectManager->get(AuthorizationServiceInterface::class)
                ->grantPermissions((int)$integration->getId(), ['Magento_Catalog::products']);
            $consumerId = (int)$integration->getConsumerId();
            $oauthService->createAccessToken($consumerId, true);
            $token = $oauthService->getAccessToken($consumerId);
            $result = ['token' => $token ? $token->getToken() : null];
            break;

        case 'review':
            $storeId = (int)$payload['storeId'];
            /** @var Rating $rating */
            $rating = $objectManager->create(RatingCollection::class)
                ->addEntityFilter('product')
                ->setPositionOrder()
                ->getFirstItem();
            if (!$rating->getId()) {
                throw new \RuntimeException('No product rating exists to vote with.');
            }
            $rating->setStores([0, $storeId])->setIsActive(1)->save();
            $option = $objectManager->get(RatingOptionCollectionFactory::class)->create()
                ->addRatingFilter($rating->getId())
                ->addFieldToFilter('value', (int)$payload['ratingValue'])
                ->getFirstItem();

            /** @var Review $review */
            $review = $objectManager->get(ReviewFactory::class)->create();
            $review->setEntityId($review->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE))
                ->setEntityPkValue((int)$payload['productId'])
                ->setStatusId((int)$payload['statusId'])
                ->setTitle($payload['title'])
                ->setDetail($payload['detail'])
                ->setNickname($payload['nickname'])
                ->setCustomerId($payload['customerId'] ?? null)
                ->setStoreId($storeId)
                ->setStores([$storeId])
                ->save();
            $objectManager->get(RatingFactory::class)->create()
                ->setRatingId($rating->getId())
                ->setReviewId($review->getId())
                ->setCustomerId($payload['customerId'] ?? null)
                ->addOptionVote((int)$option->getId(), (int)$payload['productId']);
            $review->aggregate();
            $result = ['reviewId' => (int)$review->getId(), 'percent' => (int)$payload['ratingValue'] * 20];
            break;

        case 'guest-subscriber':
            $subscriber = $objectManager->get(SubscriptionManagerInterface::class)
                ->subscribe($payload['email'], (int)$payload['storeId']);
            $result = ['subscriberId' => (int)$subscriber->getId()];
            break;

        case 'quote-set':
            $quote = $objectManager->get(CartRepositoryInterface::class)->get((int)$payload['id']);
            foreach ($payload['data'] as $key => $value) {
                $quote->setData($key, $value);
            }
            $objectManager->get(CartRepositoryInterface::class)->save($quote);
            $result = ['id' => (int)$quote->getId()];
            break;

        case 'quote-get':
            // Straight from the table: the repository would refuse an inactive quote for some reads.
            $connection = $objectManager->get(ResourceConnection::class)->getConnection();
            $row = $connection->fetchRow(
                $connection->select()
                    ->from($connection->getTableName('quote'), ['entity_id', 'is_active', 'reserved_order_id'])
                    ->where('entity_id = ?', (int)$payload['id'])
            );
            $result = $row ?: null;
            break;

        case 'request-log-count':
            $connection = $objectManager->get(ResourceConnection::class)->getConnection();
            $result = ['count' => (int)$connection->fetchOne(
                $connection->select()->from($connection->getTableName('reloadify_request_log'), ['COUNT(*)'])
            )];
            break;

        case 'config-set':
            $objectManager->get(WriterInterface::class)->save(
                $payload['path'],
                (string)$payload['value'],
                $payload['scope'] ?? 'default',
                (int)($payload['scopeId'] ?? 0)
            );
            $cleanConfigCache();
            $result = ['ok' => true];
            break;

        case 'config-delete':
            $objectManager->get(WriterInterface::class)->delete(
                $payload['path'],
                $payload['scope'] ?? 'default',
                (int)($payload['scopeId'] ?? 0)
            );
            $cleanConfigCache();
            $result = ['ok' => true];
            break;

        default:
            throw new \InvalidArgumentException('Unknown command: ' . $command);
    }

    echo PHP_EOL . json_encode($result) . PHP_EOL;
    exit(0);
} catch (\Throwable $exception) {
    fwrite(STDERR, get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
