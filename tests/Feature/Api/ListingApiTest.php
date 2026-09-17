<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ListingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeEnvelope(array $results, array $overrides = []): array
    {
        return array_merge([
            'D' => array_merge([
                'Success' => true,
                'Results' => $results,
                'Pagination' => ['currentPage' => 1, 'totalPages' => 1, 'totalRows' => count($results)],
                'RequestId' => 'test-request-id',
            ], $overrides),
        ]);
    }

    public function test_index_proxies_search_and_never_exposes_the_token(): void
    {
        config(['services.spark.access_token' => 'super-secret-token']);

        Http::fake([
            'replication.sparkapi.com/*' => Http::response($this->fakeEnvelope([
                ['Id' => '1', 'StandardFields' => ['ListPrice' => 100000, 'UnparsedAddress' => '1 Main St']],
            ])),
        ]);

        $response = $this->getJson('/api/listings?filter=StandardStatus Eq \'Active\'&limit=5');

        $response->assertOk()->assertJsonPath('results.0.StandardFields.ListPrice', 100000);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'listings')
            && $request->header('Authorization') === ['Bearer super-secret-token']);

        // The token must never appear in what's sent back to the browser.
        $this->assertStringNotContainsString('super-secret-token', $response->getContent());
    }

    public function test_show_returns_the_first_result(): void
    {
        Http::fake([
            'replication.sparkapi.com/*' => Http::response($this->fakeEnvelope([
                ['Id' => '123', 'StandardFields' => ['ListPrice' => 250000]],
            ])),
        ]);

        $this->getJson('/api/listings/123')
            ->assertOk()
            ->assertJsonPath('StandardFields.ListPrice', 250000);
    }

    public function test_show_returns_404_when_spark_returns_no_results(): void
    {
        Http::fake([
            'replication.sparkapi.com/*' => Http::response($this->fakeEnvelope([])),
        ]);

        $this->getJson('/api/listings/does-not-exist')->assertNotFound();
    }

    public function test_spark_failure_envelope_bubbles_up_as_json_error(): void
    {
        Http::fake([
            'replication.sparkapi.com/*' => Http::response([
                'D' => [
                    'Success' => false,
                    'Message' => 'Invalid filter syntax.',
                    'Code' => '1053',
                ],
            ], 400),
        ]);

        $this->getJson('/api/listings')
            ->assertStatus(400)
            ->assertJson(['error' => 'Invalid filter syntax.', 'code' => '1053']);
    }

    public function test_repeated_requests_are_served_from_cache(): void
    {
        Http::fake([
            'replication.sparkapi.com/*' => Http::response($this->fakeEnvelope([
                ['Id' => '1', 'StandardFields' => ['ListPrice' => 100000]],
            ])),
        ]);

        $this->getJson('/api/listings?limit=5')->assertOk();
        $this->getJson('/api/listings?limit=5')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_photos_and_open_houses_are_listing_scoped(): void
    {
        Http::fake([
            'replication.sparkapi.com/v1/listings/123/photos*' => Http::response($this->fakeEnvelope([
                ['Id' => 'photo-1', 'UriLarge' => 'https://example.com/photo.jpg'],
            ])),
            'replication.sparkapi.com/v1/listings/123/openhouses*' => Http::response($this->fakeEnvelope([])),
        ]);

        $this->getJson('/api/listings/123/photos')
            ->assertOk()
            ->assertJsonPath('results.0.UriLarge', 'https://example.com/photo.jpg');

        $this->getJson('/api/listings/123/openhouses')
            ->assertOk()
            ->assertJsonPath('results', []);
    }
}
