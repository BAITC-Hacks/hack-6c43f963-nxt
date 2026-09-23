<?php

namespace App\Support;

class ShowcaseCatalog
{
    /**
     * @return list<array{image: string, title: string, caption: string, category: string, event_format: string, city: string}>
     */
    public static function items(): array
    {
        return [
            [
                'image' => 'images/showcase/showcase-wedding.jpg',
                'title' => 'Свадебный вечер',
                'caption' => 'Ведущие и атмосфера большого дня',
                'category' => 'Ведущий',
                'event_format' => 'свадьба',
                'city' => 'Алматы',
            ],
            [
                'image' => 'images/showcase/showcase-florist.jpg',
                'title' => 'Флористика',
                'caption' => 'Авторские композиции для зала и фотозоны',
                'category' => 'Флорист',
                'event_format' => 'свадьба',
                'city' => 'Алматы',
            ],
            [
                'image' => 'images/showcase/showcase-photo.jpg',
                'title' => 'Фотография',
                'caption' => 'Живые кадры без постановки напоказ',
                'category' => 'Фотограф',
                'event_format' => 'свадьба',
                'city' => 'Алматы',
            ],
            [
                'image' => 'images/showcase/showcase-hall.jpg',
                'title' => 'Банкетный зал',
                'caption' => 'Свет, стол и пространство под гостей',
                'category' => 'Банкетный зал',
                'event_format' => 'той',
                'city' => 'Астана',
            ],
            [
                'image' => 'images/showcase/showcase-host.jpg',
                'title' => 'Корпоратив',
                'caption' => 'Ведущие для делового и праздничного вечера',
                'category' => 'Ведущий',
                'event_format' => 'корпоратив',
                'city' => 'Алматы',
            ],
            [
                'image' => 'images/showcase/showcase-band.jpg',
                'title' => 'Лайв-бэнд',
                'caption' => 'Живой звук для танцпола и ужина',
                'category' => 'Лайв-бэнд',
                'event_format' => 'юбилей',
                'city' => 'Алматы',
            ],
        ];
    }
}
