<?php declare(strict_types=1);

namespace Kiboko\Component\ReactLoopAdapter;

final class CountException extends \RuntimeException
{
    /** @param array<int, mixed> $resolutions */
    public function __construct(
        private int $count,
        private array $resolutions,
        string $message = "",
        \Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
