<?php

declare(strict_types=1);

namespace MaxMind\WebService\Http;

use MaxMind\Exception\HttpException;

/**
 * Interface Request.
 *
 * @internal
 */
interface Request
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(string $url, array $options);

    /**
     * @throws HttpException when the request fails
     *
     * @return array{0:int, 1:string|null, 2:string|null}
     */
    public function post(string $body): array;

    /**
     * @throws HttpException when the request fails
     *
     * @return array{0:int, 1:string|null, 2:string|null}
     */
    public function get(): array;
}
