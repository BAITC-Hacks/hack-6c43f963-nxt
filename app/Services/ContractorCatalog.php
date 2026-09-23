<?php

namespace App\Services;

use RuntimeException;

class ContractorCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $path = storage_path('app/hackathon/contractors.csv');

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Contractor catalog CSV is missing or unreadable: {$path}");
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open contractor catalog CSV: {$path}");
        }

        try {
            $headers = fgetcsv($handle);

            if ($headers === false) {
                return [];
            }

            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
            $headers = array_map(static fn ($header) => trim((string) $header), $headers);
            $rows = [];

            while (($values = fgetcsv($handle)) !== false) {
                if ($values === [null] || $values === []) {
                    continue;
                }

                $values = array_pad($values, count($headers), null);
                $row = array_combine($headers, array_slice($values, 0, count($headers)));

                foreach (['categories', 'event_formats', 'languages', 'busy_dates'] as $field) {
                    $row[$field] = $this->splitList($row[$field] ?? null);
                }

                $row['price_from_kzt'] = (int) ($row['price_from_kzt'] ?? 0);
                $row['max_hours'] = ($row['max_hours'] ?? '') === '' ? null : (int) $row['max_hours'];

                foreach (['synthetic', 'city_imputed', 'price_imputed'] as $field) {
                    $row[$field] = filter_var($row[$field] ?? false, FILTER_VALIDATE_BOOLEAN);
                }

                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{cities: array<int, string>, categories: array<int, string>, event_formats: array<int, string>, languages: array<int, string>}
     */
    public function options(): array
    {
        $rows = $this->all();
        $options = [
            'cities' => [],
            'categories' => [],
            'event_formats' => [],
            'languages' => [],
        ];

        foreach ($rows as $row) {
            if (($row['city'] ?? '') !== '') {
                $options['cities'][] = $row['city'];
            }

            foreach (['categories', 'event_formats', 'languages'] as $field) {
                array_push($options[$field], ...$row[$field]);
            }
        }

        foreach ($options as &$values) {
            $values = array_values(array_unique($values));
            sort($values, SORT_STRING);
        }

        return $options;
    }

    /** @return array<int, string> */
    private function splitList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_map('trim', explode('|', $value)));
    }
}
