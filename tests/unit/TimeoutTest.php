<?php declare(strict_types=1);

namespace unit\Kiboko\Component\ReactLoopAdapter;

use Kiboko\Component\Promise\FailedPromise;
use Kiboko\Component\Promise\Promise;
use Kiboko\Component\Promise\Resolution\Pending;
use Kiboko\Component\Promise\SucceededPromise;
use Kiboko\Contract\Promise\Resolution\FailureInterface;
use Kiboko\Contract\Promise\Resolution\SuccessInterface;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Factory;
use Kiboko\Component\ReactLoopAdapter;

final class TimeoutTest extends TestCase
{
    public function testSucceededPromise()
    {
        $loop = Factory::create();

        $promise = new SucceededPromise('Resolved Value');

        ReactLoopAdapter\timeout($promise, $loop, 1);

        $this->assertTrue($promise->isResolved());
        $this->assertInstanceOf(SuccessInterface::class, $promise->resolution());
        $this->assertEquals('Resolved Value', $promise->resolution()->value());
    }

    public function testFailedPromise()
    {
        $loop = Factory::create();

        $promise = new FailedPromise(new \Exception());

        ReactLoopAdapter\timeout($promise, $loop, 1);

        $this->assertTrue($promise->isResolved());
        $this->assertInstanceOf(FailureInterface::class, $promise->resolution());
    }

    public function testPendingPromiseWillTimeout()
    {
        $loop = Factory::create();

        $promise = new Promise();

        $this->assertFalse($promise->isResolved());
        $this->assertInstanceOf(Pending::class, $promise->resolution());

        ReactLoopAdapter\timeout($promise, $loop, 1);

        $this->assertFalse($promise->isResolved());
        $this->assertInstanceOf(Pending::class, $promise->resolution());

        $loop->run();

        $this->assertTrue($promise->isResolved());
        $this->assertInstanceOf(FailureInterface::class, $promise->resolution());
        $this->assertInstanceOf(ReactLoopAdapter\TimeoutException::class, $promise->resolution()->error());
    }

    public function testPendingPromiseWillNotTimeoutAndSucceed()
    {
        $loop = Factory::create();

        $promise = new Promise();

        $this->assertFalse($promise->isResolved());
        $this->assertInstanceOf(Pending::class, $promise->resolution());

        $loop->addTimer(1, function () use ($promise) {
            $promise->resolve('Resolved Value');
        });

        ReactLoopAdapter\timeout($promise, $loop, 5);

        $this->assertFalse($promise->isResolved());
        $this->assertInstanceOf(Pending::class, $promise->resolution());

        $loop->run();

        $this->assertTrue($promise->isResolved());
        $this->assertInstanceOf(SuccessInterface::class, $promise->resolution());
        $this->assertEquals('Resolved Value', $promise->resolution()->value());
    }

    public function testPendingPromiseWillNotTimeoutAndFail()
    {
        $loop = Factory::create();

        $promise = new Promise();

        ReactLoopAdapter\timeout($promise, $loop, 5);

        $this->assertFalse($promise->isResolved());
        $this->assertInstanceOf(Pending::class, $promise->resolution());

        $loop->addTimer(1, function () use ($promise) {
            $promise->fail(new \Exception());
        });

        $loop->run();

        $this->assertTrue($promise->isResolved());
        $this->assertInstanceOf(FailureInterface::class, $promise->resolution());
        $this->assertInstanceOf(\Exception::class, $promise->resolution()->error());
        $this->assertNotInstanceOf(ReactLoopAdapter\TimeoutException::class, $promise->resolution()->error());
    }
}
