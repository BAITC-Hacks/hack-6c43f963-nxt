<?php

use App\Services\ContractorCatalog;
use App\Services\ContractorMatcher;

function matcherProfile(string $id, array $overrides = []): array
{
    return array_merge([
        'id' => $id, 'anon_name' => 'Подрядчик '.$id, 'city' => 'Алматы', 'categories' => ['Ведущий'],
        'price_from_kzt' => 100000, 'event_formats' => ['корпоратив'], 'languages' => ['русский'],
        'max_hours' => 6, 'busy_dates' => [], 'description' => 'Ведущий с опытом проведения форумов.', 'synthetic' => false,
    ], $overrides);
}

function matcherFor(array $profiles): ContractorMatcher
{
    $catalog = Mockery::mock(ContractorCatalog::class);
    $catalog->shouldReceive('all')->once()->andReturn($profiles);
    return new ContractorMatcher($catalog);
}

function matcherFilters(array $overrides = []): array
{
    return array_merge(['city' => 'Алматы', 'category' => 'Ведущий', 'date' => '2026-10-01', 'event_format' => 'корпоратив', 'budget_kzt' => 100000, 'duration_hours' => 4, 'language' => 'русский'], $overrides);
}

it('excludes contractors busy on the requested date', function () {
    $result = matcherFor([matcherProfile('A', ['busy_dates' => ['2026-10-01']])])->recommend(matcherFilters());

    expect($result['status'])->toBe('no_eligible')
        ->and($result['excluded']['busy'])->toBe(1)
        ->and($result['results'])->toBeEmpty();
});

it('returns a deterministic order for repeated requests', function () {
    $profiles = [matcherProfile('B'), matcherProfile('A')];
    $first = (new ContractorMatcher(new class($profiles) extends ContractorCatalog {
        public function __construct(private array $rows) {}
        public function all(): array { return $this->rows; }
    }))->recommend(matcherFilters());
    $second = (new ContractorMatcher(new class($profiles) extends ContractorCatalog {
        public function __construct(private array $rows) {}
        public function all(): array { return $this->rows; }
    }))->recommend(matcherFilters());

    expect(array_column($first['results'], 'id'))->toBe(['A', 'B'])
        ->and(array_column($second['results'], 'id'))->toBe(['A', 'B']);
});

it('does not substitute another city or category', function () {
    $result = matcherFor([matcherProfile('A', ['city' => 'Астана'])])->recommend(matcherFilters());

    expect($result['status'])->toBe('no_category_in_city')
        ->and($result['total_in_city_category'])->toBe(0);
});

it('reports actual reasons when every candidate is excluded', function () {
    $profiles = [matcherProfile('A', ['busy_dates' => ['2026-10-01']]), matcherProfile('B', ['price_from_kzt' => 200000])];
    $result = matcherFor($profiles)->recommend(matcherFilters());

    expect($result['status'])->toBe('no_eligible')
        ->and($result['excluded']['busy'])->toBe(1)
        ->and($result['excluded']['over_budget'])->toBe(1)
        ->and($result['message'])->toContain('занят', 'цена выше бюджета');
});

it('accepts a price equal to the budget', function () {
    $result = matcherFor([matcherProfile('A')])->recommend(matcherFilters());

    expect($result['eligible_count'])->toBe(1)
        ->and($result['results'][0]['explanation'])->toContain('ровно в пределах бюджета');
});

it('does not exclude profiles with no maximum hours', function () {
    $result = matcherFor([matcherProfile('A', ['max_hours' => null])])->recommend(matcherFilters(['duration_hours' => 99]));

    expect($result['eligible_count'])->toBe(1)
        ->and($result['results'][0]['synthetic'])->toBeBool();
});
