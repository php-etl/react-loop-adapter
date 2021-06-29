<?php declare(strict_types=1);

namespace Kiboko\Component\ReactLoopAdapter;

use Kiboko\Component\Promise\Promise;
use Kiboko\Contract\Promise\PromiseInterface;
use Kiboko\Contract\Promise\Resolution\FailureInterface;
use Kiboko\Contract\Promise\Resolution\SuccessInterface;
use Kiboko\Contract\Promise\ResolvablePromiseInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

/**
 * @template ExpectationType
 * @template ExceptionType of \Throwable
 *
 * @param PromiseInterface<ExpectationType, ExceptionType> $promise
 * @param LoopInterface $loop
 * @param int $timeout
 *
 * @return PromiseInterface<ExpectationType, ExceptionType|TimeoutException>|PromiseInterface<ExpectationType, ExceptionType>
 */
function timeout(PromiseInterface $promise, LoopInterface $loop, int $timeout): PromiseInterface
{
    if ($promise->isResolved()) {
        return $promise;
    }

    if (!$promise instanceof ResolvablePromiseInterface) {
        throw new TimeoutException('Could not apply timeout to this promise, the promise should be resolvable');
    }

    /** @var Promise<ExpectationType, ExceptionType|TimeoutException> $timeoutPromise */
    $timeoutPromise = new Promise();

    $timer = $loop->addTimer($timeout, function () use ($timeoutPromise) {
        $timeoutPromise->fail(new TimeoutException('The promise timed out.'));
    });

    $promise
        ->then(function ($value) use ($loop, $timer) {
            $loop->cancelTimer($timer);
        })
        ->failure(function (\Throwable $failure) use ($loop, $timer) {
            $loop->cancelTimer($timer);
        });

    $timeoutPromise
        ->failure(function ($failure) use ($promise) {
            $promise->fail($failure);
        });

    return $timeoutPromise;
}

/**
 * @template ExpectationType
 * @template ExceptionType of \Throwable
 *
 * @param PromiseInterface<ExpectationType, ExceptionType> $promise
 * @param LoopInterface $loop
 * @param int|null $timeout
 *
 * @return ExpectationType
 *
 * @throws TimeoutException
 * @throws ExceptionType
 */
function await(PromiseInterface $promise, LoopInterface $loop, ?int $timeout = null)
{
    if ($promise->isResolved()) {
        $resolution = $promise->resolution();
        if ($resolution instanceof SuccessInterface) {
            return $resolution->value();
        }
    }

    $timer = $loop->addPeriodicTimer(1, function (TimerInterface $timer) use ($promise, $loop) {
        if ($promise->isResolved()) {
            $loop->cancelTimer($timer);
        }
    });

    if ($timeout !== null) {
        $loop->addTimer($timeout, function (TimerInterface $timeoutTimer) use ($loop, $timer) {
            $loop->cancelTimer($timeoutTimer);
            $loop->cancelTimer($timer);
        });
    }

    $loop->run();

    $resolution = $promise->resolution();

    if ($resolution instanceof FailureInterface) {
        throw $resolution->error();
    }

    if ($resolution instanceof SuccessInterface) {
        return $resolution->value();
    }

    throw new TimeoutException('The promise awaiting timed out.');
}
