<?php declare(strict_types=1);

namespace Kiboko\Component\ReactLoopAdapter;

use Kiboko\Component\Promise\Promise;
use Kiboko\Contract\Promise\PromiseInterface;
use Kiboko\Contract\Promise\ResolvablePromiseInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

function timeout(PromiseInterface $promise, LoopInterface $loop, int $timeout): PromiseInterface
{
    if ($promise->isResolved()) {
        return $promise;
    }

    if (!$promise instanceof ResolvablePromiseInterface) {
        throw new TimeoutException('Could not apply timeout to this promise, the promise should be resolvable');
    }

    $timeoutPromise = new Promise();

    $timer = $loop->addTimer($timeout, function () use ($timeoutPromise) {
        $timeoutPromise->fail(new TimeoutException('The promise timed out.'));
    });

    $promise
        ->then(function () use ($loop, $timer) {
            $loop->cancelTimer($timer);
        })
        ->failure(function () use ($promise, $loop, $timer) {
            $loop->cancelTimer($timer);
        });

    $timeoutPromise
        ->failure(function ($failure) use ($promise, $loop, $timer) {
            $promise->fail($failure);
        });

    return $timeoutPromise;
}

function await(PromiseInterface $promise, LoopInterface $loop, ?int $timeout = null): PromiseInterface
{
    if ($promise->isResolved()) {
        return $promise;
    }

    $timer = $loop->addPeriodicTimer(1, function (TimerInterface $timer) use ($promise, $loop) {
        if ($promise->isResolved()) {
            $loop->cancelTimer($timer);
        }
    });

    $loop->addTimer($timeout, function (TimerInterface $timeoutTimer) use ($loop, $timer) {
        $loop->cancelTimer($timeoutTimer);
        $loop->cancelTimer($timer);
    });

    $loop->run();

    return $promise;
}
