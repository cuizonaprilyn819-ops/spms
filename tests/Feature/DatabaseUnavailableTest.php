<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use PDOException;
use Tests\TestCase;

class DatabaseUnavailableTest extends TestCase
{
    public function test_connection_failure_returns_safe_html_without_a_session(): void
    {
        $cause = new PDOException('Private database hostname', 2002);
        $exception = new QueryException('mysql', 'select * from sessions', [], $cause);
        $response = app(ExceptionHandler::class)->render(Request::create('/login', 'POST'), $exception);
        $this->assertSame(503, $response->getStatusCode());
        $this->assertStringContainsString('temporarily unreachable', $response->getContent());
        $this->assertStringNotContainsString('Private database hostname', $response->getContent());
        $this->assertSame('30', $response->headers->get('Retry-After'));
    }

    public function test_api_connection_failure_returns_json(): void
    {
        $response = app(ExceptionHandler::class)->render(Request::create('/api/offices'), new PDOException('Private host', 2003));
        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertArrayHasKey('message', json_decode($response->getContent(), true));
    }

    public function test_other_database_errors_are_not_misreported_as_connection_failures(): void
    {
        $response = app(ExceptionHandler::class)->render(Request::create('/api/offices'), new PDOException('Invalid SQL', 1064));
        $this->assertSame(500, $response->getStatusCode());
    }

    public function test_postgresql_connection_failure_returns_safe_json(): void
    {
        $cause = new PDOException('Private PostgreSQL hostname');
        $cause->errorInfo = ['08006', 7, 'Connection refused'];
        $exception = new QueryException('pgsql', 'select * from sessions', [], $cause);
        $response = app(ExceptionHandler::class)->render(Request::create('/api/offices'), $exception);

        $this->assertSame(503, $response->getStatusCode());
        $this->assertStringNotContainsString('Private PostgreSQL hostname', $response->getContent());
        $this->assertSame('30', $response->headers->get('Retry-After'));
    }

    public function test_postgresql_query_error_is_not_misreported_as_connection_failure(): void
    {
        $cause = new PDOException('Invalid PostgreSQL query');
        $cause->errorInfo = ['42703', 7, 'Undefined column'];
        $exception = new QueryException('pgsql', 'select missing from sessions', [], $cause);
        $response = app(ExceptionHandler::class)->render(Request::create('/api/offices'), $exception);

        $this->assertSame(500, $response->getStatusCode());
    }
}
