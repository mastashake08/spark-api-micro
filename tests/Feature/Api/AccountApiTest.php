<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeEnvelope(array $results): array
    {
        return [
            'D' => [
                'Success' => true,
                'Results' => $results,
                'Pagination' => ['currentPage' => 1, 'totalPages' => 1, 'totalRows' => count($results)],
                'RequestId' => 'test-request-id',
            ],
        ];
    }

    public function test_agents_filters_accounts_by_user_type(): void
    {
        Http::fake([
            'replication.sparkapi.com/*' => Http::response($this->fakeEnvelope([
                ['Id' => '1', 'UserType' => 'Member', 'Name' => 'Jane Agent'],
            ])),
        ]);

        $this->getJson('/api/agents')
            ->assertOk()
            ->assertJsonPath('results.0.Name', 'Jane Agent');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'accounts')
            && str_contains($request->url(), rawurlencode("UserType Eq 'Member'")));
    }

    public function test_offices_filters_accounts_by_user_type(): void
    {
        Http::fake([
            'replication.sparkapi.com/*' => Http::response($this->fakeEnvelope([
                ['Id' => '2', 'UserType' => 'Office', 'Name' => 'Acme Realty'],
            ])),
        ]);

        $this->getJson('/api/offices')
            ->assertOk()
            ->assertJsonPath('results.0.Name', 'Acme Realty');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'accounts')
            && str_contains($request->url(), rawurlencode("UserType Eq 'Office'")));
    }

    public function test_account_show_returns_404_when_not_found(): void
    {
        Http::fake([
            'replication.sparkapi.com/*' => Http::response($this->fakeEnvelope([])),
        ]);

        $this->getJson('/api/accounts/does-not-exist')->assertNotFound();
    }
}
