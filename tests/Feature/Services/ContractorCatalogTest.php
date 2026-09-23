<?php

use App\Services\ContractorCatalog;

it('reads all 66 profiles and normalizes the supplied values without losing text', function () {
    $contractors = (new ContractorCatalog)->all();
    $byId = array_column($contractors, null, 'id');

    expect($contractors)->toHaveCount(66);
    expect($byId['HK-39372']['anon_name'])->toBe('Тони Тони Чоппер');
    expect($byId['HK-39372']['city'])->toBe('Алматы');
    expect($byId['HK-39372']['categories'])->toBe(['Флорист']);
    expect($byId['HK-39372']['event_formats'])->toBe(['свадьба', 'корпоратив', 'конференция', 'юбилей']);
    expect($byId['HK-39372']['price_from_kzt'])->toBe(200000);
    expect($byId['HK-39372']['max_hours'])->toBeNull();
    expect($byId['HK-39372']['synthetic'])->toBeFalse();
    expect($byId['HK-39372']['city_imputed'])->toBeFalse();
    expect($byId['HK-39372']['price_imputed'])->toBeTrue();
    expect($byId['HK-39372']['photo'])->toBe('images/contractors/portrait-'.str_pad((string) ((abs(crc32('HK-39372')) % 10) + 1), 2, '0', STR_PAD_LEFT).'.jpg');
    expect($byId['HK-39372']['busy_dates'])->toHaveCount(52);
    expect($byId['HK-39372']['busy_dates'][0])->toBe('2026-09-25');
    expect($byId['HK-39372']['busy_dates'][51])->toBe('2026-12-31');
    expect($byId['HK-39372']['description'])->toBe('Мы специализируемся на авторском цветочном оформлении и флористике для мероприятий в Алматы. Ежемесячно реализуем более 1000 заказов. Среди наших клиентов и партнёров: SkyLumen, CoffeeNoir, Trickster Café, Suisei Water, Curly Sensei, Hoshizora, Банк «Sakura Credit». Также выполняли оформление для Hotel Akatsuki, Fuyuki Grand и Atelier Sakura.');
    expect($byId['HK-44733']['max_hours'])->toBe(6);
    expect($byId['HK-44733']['languages'])->toBe(['русский', 'английский']);
    expect($byId['HK-44733']['price_imputed'])->toBeFalse();
    expect($byId['HK-90001']['synthetic'])->toBeTrue();
    expect($byId['HK-35846']['city_imputed'])->toBeTrue();
});

it('returns unique sorted options from the supplied catalog', function () {
    expect((new ContractorCatalog)->options())->toBe([
        'cities' => ['Алматы', 'Астана', 'Зарубежье'],
        'categories' => ['Банкетный зал', 'Ведущий', 'Ведущий церемонии', 'Видеограф', 'Декоратор', 'Загородная площадка', 'Инструменталист', 'Лайв-бэнд', 'Национальный ансамбль', 'Отель', 'Подарки и сувениры', 'Ресторан', 'Танцевальный коллектив', 'Флорист', 'Фото и видеобудки', 'Фотограф', 'Шоу-программа'],
        'event_formats' => ['день рождения', 'конференция', 'корпоратив', 'свадьба', 'той', 'юбилей'],
        'languages' => ['английский', 'казахский', 'русский'],
    ]);
});

it('reads a UTF-8 BOM and quoted header independently of the working directory', function () {
    $catalog = new ContractorCatalog;
    $expected = $catalog->all();
    $contents = file_get_contents(storage_path('app/private/contractors.csv'));
    $originalStorage = storage_path();
    $originalDirectory = getcwd();
    $temporaryStorage = sys_get_temp_dir().'/contractor-catalog-'.bin2hex(random_bytes(8));
    mkdir($temporaryStorage.'/app/private', 0777, true);
    $temporaryFile = $temporaryStorage.'/app/private/contractors.csv';

    try {
        file_put_contents($temporaryFile, "\xEF\xBB\xBF".'"id"'.substr($contents, 2)."\n");
        $this->app->useStoragePath($temporaryStorage);
        chdir(sys_get_temp_dir());

        expect($catalog->all())->toBe($expected);
    } finally {
        chdir($originalDirectory);
        $this->app->useStoragePath($originalStorage);
        unlink($temporaryFile);
        rmdir($temporaryStorage.'/app/private');
        rmdir($temporaryStorage.'/app');
        rmdir($temporaryStorage);
    }
});

it('reports the absolute path when the catalog is missing', function () {
    $originalStorage = storage_path();
    $this->app->useStoragePath(sys_get_temp_dir().'/missing-catalog-'.bin2hex(random_bytes(8)));

    try {
        expect(fn () => (new ContractorCatalog)->all())->toThrow(
            RuntimeException::class,
            'Contractor catalog CSV is missing or unreadable: '.storage_path('app/private/contractors.csv'),
        );
    } finally {
        $this->app->useStoragePath($originalStorage);
    }
});
