<?php

namespace App\Livewire;

use App\Services\ContractorCatalog;
use App\Services\ContractorMatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ContractorFinder extends Component
{
    public string $city = '';

    public string $date = '';

    public string $event_format = '';

    public string $category = '';

    public string $budget_kzt = '';

    public string $duration_hours = '';

    public string $language = '';

    #[Locked]
    public ?array $result = null;

    public function updated(): void
    {
        $this->result = null;
    }

    public function search(): void
    {
        $this->result = null;
        $options = app(ContractorCatalog::class)->options();

        $filters = $this->validate([
            'city' => ['required', 'string', Rule::in($options['cities'])],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2026-09-23', 'before_or_equal:2026-12-31'],
            'event_format' => ['required', 'string', Rule::in($options['event_formats'])],
            'category' => ['required', 'string', Rule::in($options['categories'])],
            'budget_kzt' => ['required', 'numeric', 'gt:0'],
            'duration_hours' => ['nullable', 'numeric', 'gt:0'],
            'language' => ['nullable', 'string', Rule::in($options['languages'])],
        ], [
            'required' => 'Заполните поле «:attribute».',
            'in' => 'Выберите значение из списка.',
            'numeric' => 'Введите число.',
            'gt' => 'Значение должно быть больше нуля.',
            'date_format' => 'Укажите корректную дату.',
            'after_or_equal' => 'Выберите дату не раньше 23 сентября 2026.',
            'before_or_equal' => 'Выберите дату не позже 31 декабря 2026.',
        ], [
            'city' => 'Город', 'date' => 'Дата', 'event_format' => 'Формат события',
            'category' => 'Категория', 'budget_kzt' => 'Бюджет',
            'duration_hours' => 'Длительность', 'language' => 'Язык',
        ]);

        $filters['budget_kzt'] = (float) $filters['budget_kzt'];
        $filters['duration_hours'] = filled($filters['duration_hours']) ? (float) $filters['duration_hours'] : null;
        $filters['language'] = filled($filters['language']) ? $filters['language'] : null;

        $this->result = app(ContractorMatcher::class)->recommend($filters);
    }

    public function render(): View
    {
        return view('livewire.contractor-finder', [
            'options' => app(ContractorCatalog::class)->options(),
        ])->layout('layouts.contractor-finder');
    }
}
