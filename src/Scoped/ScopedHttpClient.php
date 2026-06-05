<?php

declare(strict_types=1);

namespace Phalanx\Bia\Scoped;

use Phalanx\HttpClient\HttpClient;
use Phalanx\HttpClient\HttpRequest;
use Phalanx\HttpClient\HttpResponse;
use Phalanx\HttpClient\HttpStream;
use Phalanx\Scope\ExecutionScope;

class ScopedHttpClient
{
    public function __construct(private ExecutionScope $ctx)
    {
    }

    /** @param array<string, list<string>> $headers */
    public function get(string $url, array $headers = []): HttpResponse
    {
        return $this->ctx->execute(
            static fn(ExecutionScope $scope): HttpResponse => $scope->service(HttpClient::class)->get($scope, $url, $headers),
        );
    }

    /** @param array<string, list<string>> $headers */
    public function post(string $url, string $body, array $headers = []): HttpResponse
    {
        return $this->ctx->execute(
            static fn(ExecutionScope $scope): HttpResponse => $scope->service(HttpClient::class)->post($scope, $url, $body, $headers),
        );
    }

    public function request(HttpRequest $request): HttpResponse
    {
        return $this->ctx->execute(
            static fn(ExecutionScope $scope): HttpResponse => $scope->service(HttpClient::class)->request($scope, $request),
        );
    }

    public function stream(HttpRequest $request): HttpStream
    {
        return $this->ctx->execute(
            static fn(ExecutionScope $scope): HttpStream => $scope->service(HttpClient::class)->stream($scope, $request),
        );
    }
}
