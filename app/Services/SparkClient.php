<?php

namespace App\Services;

use App\Exceptions\SparkApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Server-side wrapper around the Spark Platform (Flexmls) MLS/IDX API.
 *
 * Holds the access token and never runs anywhere but here — Spark's docs
 * explicitly forbid sending it to a browser, and the API has no CORS support
 * anyway. Controllers call this and return plain JSON; the frontend never
 * sees the token. Mirrors the shape of the `spark-mls-client` npm package's
 * `SparkClient` (search/get/media/openHouses/accounts/agents/offices) so the
 * two stay conceptually interchangeable.
 */
class SparkClient
{
    public function __construct(
        protected string $baseUrl,
        protected string $accessToken,
        protected string $userAgent,
        protected int $cacheTtl = 60,
    ) {}

    /**
     * @param  array<string, mixed>  $params  filter/select/expand/orderBy/limit/page
     * @return array{results: array<int, array<string, mixed>>, pagination: ?array<string, mixed>, requestId: ?string}
     */
    public function listings(array $params = []): array
    {
        return $this->getList('listings', $this->buildListQuery($params));
    }

    /**
     * @param  array<string, mixed>  $params  select/expand
     * @return array<string, mixed>
     */
    public function listing(string $id, array $params = []): array
    {
        $result = $this->getList('listings/'.rawurlencode($id), array_filter([
            '_select' => $this->delimited($params['select'] ?? null),
            '_expand' => $this->delimited($params['expand'] ?? null),
        ], fn ($value) => $value !== null));

        return $this->firstOrFail($result, "Spark API returned no listing for id \"{$id}\".");
    }

    /** @return array{results: array<int, array<string, mixed>>, pagination: ?array<string, mixed>, requestId: ?string} */
    public function media(string $id): array
    {
        return $this->getList('listings/'.rawurlencode($id).'/photos');
    }

    /** @return array{results: array<int, array<string, mixed>>, pagination: ?array<string, mixed>, requestId: ?string} */
    public function openHouses(string $id): array
    {
        return $this->getList('listings/'.rawurlencode($id).'/openhouses');
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: array<int, array<string, mixed>>, pagination: ?array<string, mixed>, requestId: ?string}
     */
    public function accounts(array $params = []): array
    {
        return $this->getList('accounts', $this->buildListQuery($params));
    }

    /** @return array<string, mixed> */
    public function account(string $id): array
    {
        $result = $this->getList('accounts/'.rawurlencode($id));

        return $this->firstOrFail($result, "Spark API returned no account for id \"{$id}\".");
    }

    /**
     * Agents and offices are both `/accounts` records distinguished by
     * `UserType` — same convenience split as the npm package's `client.agents`.
     *
     * @param  array<string, mixed>  $params
     * @return array{results: array<int, array<string, mixed>>, pagination: ?array<string, mixed>, requestId: ?string}
     */
    public function agents(array $params = []): array
    {
        return $this->accounts($this->withUserTypeFilter($params, 'Member'));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: array<int, array<string, mixed>>, pagination: ?array<string, mixed>, requestId: ?string}
     */
    public function offices(array $params = []): array
    {
        return $this->accounts($this->withUserTypeFilter($params, 'Office'));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function withUserTypeFilter(array $params, string $userType): array
    {
        $existing = $params['filter'] ?? null;
        $clause = "UserType Eq '{$userType}'";

        $params['filter'] = match (true) {
            $existing === null => $clause,
            is_array($existing) => [$clause, ...$existing],
            default => "({$clause}) And ({$existing})",
        };

        return $params;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, string>
     */
    protected function buildListQuery(array $params): array
    {
        return array_filter([
            '_filter' => $this->filterExpression($params['filter'] ?? null),
            '_select' => $this->delimited($params['select'] ?? null),
            '_expand' => $this->delimited($params['expand'] ?? null),
            '_orderby' => $params['orderBy'] ?? null,
            '_limit' => $params['limit'] ?? null,
            '_page' => $params['page'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /** A raw OData clause, or an array of clauses joined with `And` (mirrors the npm package's `SparkListParams.filter`). */
    protected function filterExpression(mixed $filter): ?string
    {
        if ($filter === null) {
            return null;
        }

        if (is_array($filter)) {
            return implode(' And ', array_map(fn ($clause) => "({$clause})", $filter));
        }

        return (string) $filter;
    }

    protected function delimited(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_array($value) ? implode(',', $value) : (string) $value;
    }

    /**
     * @param  array<string, string>  $query
     * @return array{results: array<int, array<string, mixed>>, pagination: ?array<string, mixed>, requestId: ?string}
     */
    protected function getList(string $path, array $query = []): array
    {
        $cacheKey = 'spark:'.md5($path.'?'.http_build_query($query));

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($path, $query) {
            $response = $this->request()->get($path, $query);

            $envelope = $response->json('D');

            if (! $response->successful() || ! ($envelope['Success'] ?? false)) {
                throw new SparkApiException(
                    $envelope['Message'] ?? "Spark API request to \"{$path}\" failed.",
                    status: $response->status() >= 400 ? $response->status() : 502,
                    errorCode: $envelope['Code'] ?? null,
                    details: $envelope['Errors'] ?? [],
                );
            }

            return [
                'results' => $envelope['Results'] ?? [],
                'pagination' => $envelope['Pagination'] ?? null,
                'requestId' => $envelope['RequestId'] ?? null,
            ];
        });
    }

    /**
     * @param  array{results: array<int, array<string, mixed>>}  $list
     * @return array<string, mixed>
     */
    protected function firstOrFail(array $list, string $message): array
    {
        if (empty($list['results'])) {
            throw new SparkApiException($message, status: 404);
        }

        return $list['results'][0];
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->accessToken)
            ->withHeaders(['X-SparkApi-User-Agent' => $this->userAgent])
            ->acceptJson()
            ->timeout(15);
    }
}
