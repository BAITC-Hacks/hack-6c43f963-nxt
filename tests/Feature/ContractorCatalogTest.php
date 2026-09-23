<?php

use App\Services\ContractorCatalog;

it('loads and normalizes the contractor catalog', function () {
    $catalog = new ContractorCatalog;
    $rows = $catalog->all();

    expect($rows)->toHaveCount(66);

    foreach ($rows as $row) {
        expect($row['categories'])->toBeArray()
            ->and($row['event_formats'])->toBeArray()
            ->and($row['languages'])->toBeArray()
            ->and($row['busy_dates'])->toBeArray()
            ->and($row['price_from_kzt'])->toBeInt()
            ->and($row['synthetic'])->toBeBool()
            ->and($row['city_imputed'])->toBeBool()
            ->and($row['price_imputed'])->toBeBool();

        expect($row['max_hours'] === null || is_int($row['max_hours']))->toBeTrue();
    }

    expect($rows[0]['id'])->toBe('HK-39372')
        ->and($rows[0]['busy_dates'])->toContain('2026-09-25', '2026-12-31')
        ->and($rows[0]['synthetic'])->toBeFalse()
        ->and($rows[65]['id'])->toBe('HK-90013')
        ->and($rows[65]['busy_dates'])->toContain('2026-09-25', '2026-12-30')
        ->and($rows[65]['synthetic'])->toBeTrue();

    $options = $catalog->options();

    foreach (['cities', 'categories', 'event_formats', 'languages'] as $field) {
        expect($options[$field])->toBeArray()
            ->and($options[$field])->toBe(array_values(array_unique($options[$field])));
        $sorted = $options[$field];
        sort($sorted, SORT_STRING);
        expect($options[$field])->toBe($sorted);
    }
});
