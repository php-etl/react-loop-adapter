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

final class AwaitTest extends TestCase
{
    public function testSucceededPromise()
    {
        $loop = Factory::create();

        $promise = new SucceededPromise('Resolved Value');

        ReactLoopAdapter\await($promise, $loop, 1);

        $this->assertTrue($promise->isResolved());
        $this->assertInstanceOf(SuccessInterface::class, $promise->resolution());
        $this->assertEquals('Resolved Value', $promise->resolution()->value());
    }

    public function testFailedPromise()
    {
        $loop = Factory::create();

        $promise = new FailedPromise(new \Exception());

        ReactLoopAdapter\await($promise, $loop, 1);

        $this->assertTrue($promise->isResolved());
        $this->assertInstanceOf(FailureInterface::class, $promise->resolution());
    }

    public function testAwaitingPromiseWillTimeout()
    {
        $loop = Factory::create();

        $promise = new Promise();

        $this->assertFalse($promise->isResolved());
        $this->assertInstanceOf(Pending::class, $promise->resolution());

        ReactLoopAdapter\await($promise, $loop, 1);

        $this->assertFalse($promise->isResolved());
        $this->assertInstanceOf(Pending::class, $promise->resolution());
    }

    public function testAwaitingPromiseWillNotTimeoutAndSucceed()
    {
        $loop = Factory::create();

        $promise = new Promise();

        $this->assertFalse($promise->isResolved());
        $this->assertInstanceOf(Pending::class, $promise->resolution());

        $loop->addTimer(1, function () use ($promise) {
            $promise->resolve('Resolved Value');
        });

        ReactLoopAdapter\await($promise, $loop, 5);

        $this->assertTrue($promise->isResolved());
        $this->assertInstanceOf(SuccessInterface::class, $promise->resolution());
        $this->assertEquals('Resolved Value', $promise->resolution()->value());
    }

    public function testAwaitingPromiseWillNotTimeoutAndFail()
    {
        $loop = Factory::create();

        $promise = new Promise();

        $this->assertFalse($promise->isResolved());
        $this->assertInstanceOf(Pending::class, $promise->resolution());

        $loop->addTimer(1, function () use ($promise) {
            $promise->fail(new \Exception());
        });

        ReactLoopAdapter\await($promise, $loop, 5);

        $this->assertTrue($promise->isResolved());
        $this->assertInstanceOf(FailureInterface::class, $promise->resolution());
        $this->assertInstanceOf(\Exception::class, $promise->resolution()->error());
        $this->assertNotInstanceOf(ReactLoopAdapter\TimeoutException::class, $promise->resolution()->error());
    }
}
