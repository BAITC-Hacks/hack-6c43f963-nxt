<?php

namespace App\Services;

use RuntimeException;

/**
 * @phpstan-type Contractor array{id: string, anon_name: string, city: string, description: string, categories: list<string>, event_formats: list<string>, languages: list<string>, busy_dates: list<string>, price_from_kzt: int, max_hours: ?int, synthetic: bool, city_imputed: bool, price_imputed: bool}
 */
class ContractorCatalog
{
    /**
     * @return list<Contractor>
     */
    public function all(): array
    {
        $path = storage_path('app/private/contractors.csv');

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Contractor catalog CSV is missing or unreadable: {$path}");
        }

        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Unable to open contractor catalog CSV: {$path}");
        }

        try {
            if (fread($stream, 3) !== "\xEF\xBB\xBF") {
                rewind($stream);
            }

            $header = fgetcsv($stream, null, ',', '"', '');
            $required = ['id', 'anon_name', 'city', 'description', 'categories', 'event_formats', 'languages', 'busy_dates', 'price_from_kzt', 'max_hours', 'synthetic', 'city_imputed', 'price_imputed'];

            if ($header === false || array_diff($required, $header) !== [] || count(array_unique($header)) !== count($header)) {
                throw new RuntimeException("Invalid contractor catalog CSV header: {$path}");
            }

            $contractors = [];

            while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
                if ($values === [null]) {
                    continue;
                }

                if (count($values) !== count($header)) {
                    throw new RuntimeException("Invalid contractor catalog CSV row: {$path}");
                }

                $raw = array_combine($header, array_map(fn (?string $value): string => $value ?? '', $values));
                $contractor = $raw;

                foreach (['categories', 'event_formats', 'languages', 'busy_dates'] as $field) {
                    $contractor[$field] = array_values(array_filter(
                        array_map('trim', explode('|', $raw[$field])),
                        fn (string $value): bool => $value !== '',
                    ));
                }

                $contractor['price_from_kzt'] = (int) $raw['price_from_kzt'];
                $contractor['max_hours'] = trim($raw['max_hours']) === '' ? null : (int) $raw['max_hours'];

                foreach (['synthetic', 'city_imputed', 'price_imputed'] as $field) {
                    $contractor[$field] = filter_var($raw[$field], FILTER_VALIDATE_BOOLEAN);
                }

                /** @var Contractor $contractor */
                $contractors[] = $contractor;
            }

            return $contractors;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return array{cities: list<string>, categories: list<string>, event_formats: list<string>, languages: list<string>}
     */
    public function options(): array
    {
        $options = ['cities' => [], 'categories' => [], 'event_formats' => [], 'languages' => []];

        foreach ($this->all() as $contractor) {
            $options['cities'][] = $contractor['city'];

            foreach (['categories', 'event_formats', 'languages'] as $field) {
                array_push($options[$field], ...$contractor[$field]);
            }
        }

        foreach ($options as $field => $values) {
            $values = array_values(array_unique($values));
            sort($values, SORT_STRING);
            $options[$field] = $values;
        }

        return $options;
    }
}
