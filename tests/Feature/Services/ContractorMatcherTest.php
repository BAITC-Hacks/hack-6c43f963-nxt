<?php

use App\Services\ContractorCatalog;
use App\Services\ContractorMatcher;
use Laravel\Ai\StructuredAnonymousAgent;

beforeEach(function () {
    config(['contractors.ai_enabled' => false]);
});

function contractorCriteria(array $overrides = []): array
{
    return array_replace([
        'city' => 'Алматы', 'date' => '2026-09-23', 'category' => 'Ведущий',
        'event_format' => 'свадьба', 'budget' => 1500000, 'hours' => null, 'language' => null,
    ], $overrides);
}

it('returns up to three actual profiles in a repeatable order and changes availability with the date', function () {
    $matcher = app(ContractorMatcher::class);

    $first = $matcher->match(contractorCriteria());
    $repeat = $matcher->match(contractorCriteria());
    $nextDay = $matcher->match(contractorCriteria(['date' => '2026-09-24']));

    expect($first['status'])->toBe('matched');
    expect($first['contractors'])->toHaveCount(3);
    expect($repeat)->toBe($first);
    expect(array_column(array_column($nextDay['contractors'], 'profile'), 'id'))
        ->not->toBe(array_column(array_column($first['contractors'], 'profile'), 'id'));
    foreach ($first['contractors'] as $card) {
        expect($card['explanation'])->toContain('укладывается в бюджет 1 500 000 ₸', 'Из описания:');
    }
});

it('excludes a real profile for each mandatory condition', function (array $overrides, string $reason) {
    $profiles = array_column((new ContractorCatalog)->all(), null, 'id');
    $this->mock(ContractorCatalog::class)->shouldReceive('all')->once()->andReturn([$profiles['HK-44733']]);
    $criteria = contractorCriteria(array_replace([
        'date' => '2026-09-24', 'event_format' => 'корпоратив', 'budget' => 1000000, 'hours' => 6, 'language' => 'русский',
    ], $overrides));

    $result = app(ContractorMatcher::class)->match($criteria);

    expect($result['status'])->toBe('no_matches');
    expect($result['contractors'])->toBe([]);
    expect($result['reasons'][$reason])->toBe(1);
    expect(array_sum($result['reasons']))->toBe(1);
})->with([
    'busy date' => [['date' => '2026-09-23'], 'busy'],
    'over budget' => [['budget' => 999999], 'budget'],
    'unsupported format' => [['event_format' => 'свадьба'], 'format'],
    'unsupported language' => [['language' => 'казахский'], 'language'],
    'too many hours' => [['hours' => 7], 'hours'],
]);

it('accepts exact price and hour limits and counts overlapping rejection reasons', function () {
    $profiles = array_column((new ContractorCatalog)->all(), null, 'id');
    $this->mock(ContractorCatalog::class)->shouldReceive('all')->twice()->andReturn([$profiles['HK-44733']]);
    $matcher = app(ContractorMatcher::class);

    $accepted = $matcher->match(contractorCriteria(['date' => '2026-09-24', 'event_format' => 'корпоратив', 'budget' => 1000000, 'hours' => 6]));
    $rejected = $matcher->match(contractorCriteria(['budget' => 1, 'hours' => 7, 'language' => 'казахский']));

    expect($accepted['eligible'])->toBe(1);
    expect($rejected['total'])->toBe(1);
    expect($rejected['reasons'])->toBe(['busy' => 1, 'budget' => 1, 'format' => 1, 'language' => 1, 'hours' => 1]);
});

it('keeps one or two real matches and treats unspecified hours as unknown', function () {
    $result = app(ContractorMatcher::class)->match(contractorCriteria(['category' => 'Флорист', 'budget' => 500000, 'hours' => 24]));

    expect($result['eligible'])->toBe(2);
    expect(array_column(array_column($result['contractors'], 'profile'), 'id'))->toBe(['HK-39372', 'HK-90001']);
    expect($result['contractors'][0]['explanation'])->toContain('лимит часов не указан');
});

it('distinguishes an absent city category and never sends empty candidates to AI', function () {
    config(['contractors.ai_enabled' => true, 'ai.providers.openai.key' => 'test-key']);
    StructuredAnonymousAgent::fake();

    $result = app(ContractorMatcher::class)->match(contractorCriteria(['city' => 'Зарубежье', 'category' => 'Флорист']));

    expect($result['status'])->toBe('no_category');
    expect($result['total'])->toBe(0);
    expect($result['contractors'])->toBe([]);
    StructuredAnonymousAgent::assertNeverPrompted();
});
