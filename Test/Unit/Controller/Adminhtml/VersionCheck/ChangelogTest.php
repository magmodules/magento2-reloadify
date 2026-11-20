<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Test\Unit\Controller\Adminhtml\VersionCheck;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magmodules\Reloadify\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Reloadify\Api\Log\RepositoryInterface as LogRepository;
use Magmodules\Reloadify\Controller\Adminhtml\VersionCheck\Changelog;
use PHPUnit\Framework\TestCase;

/**
 * version.magmodules.eu is a third party behind the merchant's firewall, so a shop that cannot
 * reach it is the normal case. Before this guard the controller let the FileSystemException out,
 * the AJAX call answered 500 and the changelog modal sat on its spinner with nothing to read.
 */
class ChangelogTest extends TestCase
{
    public function testAnUnreachableVersionHostIsAnsweredAndLoggedAsDebug(): void
    {
        $result = $this->createMock(Json::class);
        $captured = null;
        $result->method('setData')->willReturnCallback(static function ($data) use ($result, &$captured) {
            $captured = $data;
            return $result;
        });

        $file = $this->createMock(File::class);
        $file->method('fileGetContents')->willThrowException(new FileSystemException(new Phrase('unreachable')));

        $log = $this->createMock(LogRepository::class);
        // A shop that cannot reach the service, or a version the service does not know yet, is a
        // normal state and not a defect of this module: it does not belong in the error log.
        $log->expects($this->once())->method('addDebugLog')->with('VersionCheck changelog', 'unreachable');
        $log->expects($this->never())->method('addErrorLog');

        $this->controller($result, $file, $log)->execute();

        self::assertIsArray($captured, 'the controller answered with data instead of throwing');
        self::assertNotSame([], $captured, 'the answer names what went wrong');
    }

    public function testAnAnswerThatIsNotAChangelogIsLoggedAsAnError(): void
    {
        $result = $this->createMock(Json::class);
        $captured = null;
        $result->method('setData')->willReturnCallback(static function ($data) use ($result, &$captured) {
            $captured = $data;
            return $result;
        });

        $file = $this->createMock(File::class);
        $file->method('fileGetContents')->willReturn('"not a changelog"');

        $log = $this->createMock(LogRepository::class);
        $log->expects($this->once())->method('addErrorLog');
        $log->expects($this->never())->method('addDebugLog');

        $this->controller($result, $file, $log)->execute();

        self::assertIsArray($captured);
        self::assertNotSame([], $captured);
    }

    public function testAReachableVersionHostIsPassedThrough(): void
    {
        $result = $this->createMock(Json::class);
        $captured = null;
        $result->method('setData')->willReturnCallback(static function ($data) use ($result, &$captured) {
            $captured = $data;
            return $result;
        });

        $file = $this->createMock(File::class);
        $file->method('fileGetContents')->willReturn(
            '{"1.0.0":{"date":"2026-11-16","changelog":"<p>First</p>"},'
            . '"1.1.0":{"date":"2026-12-01","changelog":"<p>Second</p>"}}'
        );

        $log = $this->createMock(LogRepository::class);
        $log->expects($this->never())->method('addErrorLog');
        $log->expects($this->never())->method('addDebugLog');

        $this->controller($result, $file, $log)->execute();

        // Only what the merchant has not installed yet: the modal is there to show what an update
        // brings, and the version they are running is not news.
        self::assertSame(
            ['1.1.0' => ['date' => '2026-12-01', 'changelog' => '<p>Second</p>']],
            $captured
        );
    }

    private function controller(Json $result, File $file, LogRepository $log): Changelog
    {
        $factory = $this->createMock(JsonFactory::class);
        $factory->method('create')->willReturn($result);

        $config = $this->createMock(ConfigRepository::class);
        $config->method('getExtensionVersion')->willReturn('v1.0.0');

        $config = $this->createMock(ConfigRepository::class);
        $config->method('getExtensionVersion')->willReturn('v1.0.0');

        return new Changelog(
            $this->createStub(Context::class),
            $factory,
            $config,
            new JsonSerializer(),
            $file,
            $log
        );
    }
}
