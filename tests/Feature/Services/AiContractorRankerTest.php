<?php

use App\Services\AiContractorRanker;
use App\Services\ContractorCatalog;
use App\Services\ContractorMatcher;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\StructuredAnonymousAgent;

beforeEach(function () {
    config([
        'contractors.ai_enabled' => true, 'contractors.ai_provider' => 'openai',
        'contractors.ai_model' => null, 'contractors.cache_store' => 'array', 'ai.providers.openai.key' => 'test-key',
    ]);
});

function floristCriteria(): array
{
    return ['city' => 'Алматы', 'date' => '2026-09-23', 'category' => 'Флорист', 'event_format' => 'свадьба', 'budget' => 500000, 'hours' => null, 'language' => null];
}

function floristSelections(): array
{
    return [
        ['id' => 'HK-90001', 'explanation' => 'White Sakura Studio — авторская флористика для свадеб и юбилеев.'],
        ['id' => 'HK-39372', 'explanation' => 'Мы специализируемся на авторском цветочном оформлении и флористике для мероприятий в Алматы.'],
    ];
}

it('uses verified AI selections and caches the same request without sending excluded profiles', function () {
    StructuredAnonymousAgent::fake([['selections' => floristSelections()]]);
    $matcher = app(ContractorMatcher::class);

    $first = $matcher->match(floristCriteria());
    $repeat = $matcher->match(floristCriteria());

    expect($first['ranking'])->toBe('ai');
    expect(array_column(array_column($first['contractors'], 'profile'), 'id'))->toBe(['HK-90001', 'HK-39372']);
    expect($first['contractors'][0]['explanation'])->toContain(floristSelections()[0]['explanation']);
    expect($repeat)->toBe($first);
    StructuredAnonymousAgent::assertPromptedTimes(1);
    StructuredAnonymousAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        $payload = json_decode($prompt->prompt, true);

        return array_column($payload['candidates'], 'id') === ['HK-39372', 'HK-90001'] && $prompt->timeout === 8;
    });
});

it('falls back when AI returns unsupported or malformed selections', function (string $defect) {
    $selections = floristSelections();
    match ($defect) {
        'unknown' => $selections[0]['id'] = 'unknown-id',
        'excluded' => $selections[0]['id'] = 'HK-44733',
        'duplicate' => $selections[1] = $selections[0],
        'empty' => $selections[0]['explanation'] = '',
        'invented' => $selections[0]['explanation'] = 'Скидка 50%, бронирование подтверждено.',
        'too_many' => $selections = [...$selections, ...$selections],
        'too_few' => $selections = [],
        'shape' => $selections = 'not an array',
    };
    StructuredAnonymousAgent::fake([['selections' => $selections]]);

    $result = app(ContractorMatcher::class)->match(floristCriteria());

    expect($result['ranking'])->toBe('rules');
    expect(array_column(array_column($result['contractors'], 'profile'), 'id'))->toBe(['HK-39372', 'HK-90001']);
})->with(['unknown', 'excluded', 'duplicate', 'empty', 'invented', 'too_many', 'too_few', 'shape']);

it('falls back after a provider timeout without caching the failure', function () {
    StructuredAnonymousAgent::fake(fn () => throw new RuntimeException('Provider timeout'));
    $matcher = app(ContractorMatcher::class);

    expect($matcher->match(floristCriteria())['ranking'])->toBe('rules');

    StructuredAnonymousAgent::fake([['selections' => floristSelections()]]);
    expect($matcher->match(floristCriteria())['ranking'])->toBe('ai');
});

it('does not contact a provider without credentials', function () {
    config(['ai.providers.openai.key' => null]);
    StructuredAnonymousAgent::fake();

    expect(app(ContractorMatcher::class)->match(floristCriteria())['ranking'])->toBe('rules');
    StructuredAnonymousAgent::assertNeverPrompted();
});

it('invalidates AI cache when the date or catalog content changes', function () {
    StructuredAnonymousAgent::fake([['selections' => floristSelections()]]);
    $all = array_column((new ContractorCatalog)->all(), null, 'id');
    $profiles = [$all['HK-39372'], $all['HK-90001']];
    $ranker = app(AiContractorRanker::class);
    $ranker->rank(floristCriteria(), $profiles);

    $ranker->rank(array_replace(floristCriteria(), ['date' => '2026-09-24']), $profiles);
    $profiles[0]['description'] .= ' ';
    $ranker->rank(floristCriteria(), $profiles);

    StructuredAnonymousAgent::assertPromptedTimes(3);
});
