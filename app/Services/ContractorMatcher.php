<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * @phpstan-import-type Contractor from ContractorCatalog
 *
 * @phpstan-type Criteria array{city: string, date: string, category: string, event_format: string, budget: int, hours: ?int, language: ?string}
 * @phpstan-type MatchResult array{status: string, total: int, eligible: int, reasons: array<string, int>, contractors: list<array{profile: Contractor, explanation: string}>, ranking: string}
 */
class ContractorMatcher
{
    public function __construct(private ContractorCatalog $catalog, private AiContractorRanker $ranker) {}

    /**
     * @param  Criteria  $criteria  Validated form values.
     * @return MatchResult
     */
    public function match(array $criteria): array
    {
        $candidates = array_values(array_filter($this->catalog->all(), fn (array $profile): bool => $profile['city'] === $criteria['city'] && in_array($criteria['category'], $profile['categories'], true)
        ));
        $reasons = ['busy' => 0, 'budget' => 0, 'format' => 0, 'language' => 0, 'hours' => 0];
        $eligible = [];

        foreach ($candidates as $profile) {
            $failures = [
                'busy' => in_array($criteria['date'], $profile['busy_dates'], true),
                'budget' => $profile['price_from_kzt'] > $criteria['budget'],
                'format' => ! in_array($criteria['event_format'], $profile['event_formats'], true),
                'language' => $criteria['language'] !== null && ! in_array($criteria['language'], $profile['languages'], true),
                'hours' => $criteria['hours'] !== null && $profile['max_hours'] !== null && $criteria['hours'] > $profile['max_hours'],
            ];

            foreach ($failures as $reason => $failed) {
                $reasons[$reason] += (int) $failed;
            }

            if (! in_array(true, $failures, true)) {
                $eligible[] = $profile;
            }
        }

        usort($eligible, fn (array $left, array $right): int => ($left['price_from_kzt'] <=> $right['price_from_kzt']) ?: strcmp($left['id'], $right['id'])
        );

        $ranked = $eligible === [] ? null : $this->ranker->rank($criteria, $eligible);
        $byId = array_column($eligible, null, 'id');
        $cards = [];

        foreach ($ranked ?? array_map(fn (array $profile): array => [
            'id' => $profile['id'],
            'explanation' => Str::limit($profile['description'], 220),
        ], array_slice($eligible, 0, 3)) as $selection) {
            $profile = $byId[$selection['id']];
            $cards[] = [
                'profile' => $profile,
                'explanation' => $this->explain($criteria, $profile, $selection['explanation']),
            ];
        }

        return [
            'status' => $candidates === [] ? 'no_category' : ($eligible === [] ? 'no_matches' : 'matched'),
            'total' => count($candidates),
            'eligible' => count($eligible),
            'reasons' => $reasons,
            'contractors' => $cards,
            'ranking' => $ranked === null ? 'rules' : 'ai',
        ];
    }

    /**
     * @param  Criteria  $criteria
     * @param  Contractor  $profile
     */
    private function explain(array $criteria, array $profile, string $excerpt): string
    {
        $price = number_format($profile['price_from_kzt'], 0, '.', ' ');
        $budget = number_format($criteria['budget'], 0, '.', ' ');
        $details = "Формат «{$criteria['event_format']}» указан в профиле; цена от {$price} ₸ укладывается в бюджет {$budget} ₸";

        if ($criteria['language'] !== null) {
            $details .= "; язык работы — {$criteria['language']}";
        }

        if ($criteria['hours'] !== null) {
            $details .= $profile['max_hours'] === null
                ? '; длительность нужно уточнить — лимит часов не указан'
                : "; длительность {$criteria['hours']} ч укладывается в лимит {$profile['max_hours']} ч";
        }

        return $details.'. Из описания: «'.$excerpt.'»';
    }
}
