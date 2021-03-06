<?php

namespace Bolt\Tests\Logger;

use Bolt\Logger\Handler\SystemHandler;
use Bolt\Tests\BoltUnitTest;
use Bolt\Tests\Mocks\DoctrineMockBuilder;
use Monolog\Logger;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class to test src/Logger/Handler/SystemHandler.
 *
 * @author Ross Riley <riley.ross@gmail.com>
 */
class SystemHandlerTest extends BoltUnitTest
{
    public function testSetupInitialize()
    {
        $app = $this->getApp();
        $app['request'] = Request::createFromGlobals('/');
        $log = new Logger('logger.system');
        $handler = new SystemHandler($app);

        $mocker = new DoctrineMockBuilder();
        $db = $mocker->getConnectionMock();
        $this->setService('db', $db);

        $log->pushHandler($handler);
        $log->addRecord(Logger::DEBUG, 'test', ['id' => 5, 'title' => 'test']);
        $this->assertEquals('bolt_log_system', Assert::readAttribute($handler, 'tablename'));
    }

    public function testHandle()
    {
        $app = $this->getApp();
        $app['request'] = Request::createFromGlobals('/');
        $log = new Logger('logger.system');
        $log->pushHandler(new SystemHandler($app));

        $mocker = new DoctrineMockBuilder();
        $db = $mocker->getConnectionMock();
        $db->expects($this->atLeastOnce())
            ->method('insert')
            ->with($this->equalTo('bolt_log_system'));
        $this->setService('db', $db);

        $log->addRecord(Logger::DEBUG, 'test', ['id' => 5, 'title' => 'test']);

        $this->addToAssertionCount(1);
    }

    public function testHandleWithException()
    {
        $app = $this->getApp();
        $app['request'] = Request::createFromGlobals('/');
        $log = new Logger('logger.system');
        $log->pushHandler(new SystemHandler($app));

        $mocker = new DoctrineMockBuilder();
        $db = $mocker->getConnectionMock();
        $db->expects($this->atLeastOnce())
            ->method('insert')
            ->with($this->equalTo('bolt_log_system'));
        $this->setService('db', $db);

        $log->addRecord(Logger::DEBUG, 'test', ['event' => '', 'exception' => new \Exception()]);

        $this->addToAssertionCount(1);
    }

    public function testNotHandling()
    {
        $app = $this->getApp();
        $handler = new SystemHandler($app, Logger::WARNING);
        $this->assertFalse($handler->handle(['level' => 100]));
    }
}
