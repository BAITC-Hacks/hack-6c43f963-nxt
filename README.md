# seelect — подбор event-подрядчиков

Laravel 13 + Livewire 4. Один публичный экран: параметры мероприятия → до трёх подходящих профилей с фото → подробности и заявка на запись. Данные — 66 исходных профилей в `storage/app/private/contractors.csv`.

## Запуск

Требования: PHP 8.4+, Composer, Node.js с npm.

```sh
composer install
```

Скопируйте `.env.example` в `.env`, если файла ещё нет. Для существующего `.env` задайте:

```dotenv
APP_NAME=seelect
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

```sh
php artisan key:generate
npm install
npm run build
php artisan config:clear
php artisan serve
```

Для этого сценария база данных, миграции и очередь не нужны. `key:generate` нужен только при первом запуске без ключа. Не используйте `composer run setup` для запуска без БД: унаследованный скрипт стартового шаблона запускает миграции. Авторизация и dashboard из стартового шаблона не входят в сценарий MVP.

## Правила подбора

- `App\Services\ContractorCatalog::all(): array` читает CSV через `storage_path()`, обрабатывает BOM и возвращает нормализованные профили (включая стабильный `photo`).
- `ContractorCatalog::options(): array` возвращает `cities`, `categories`, `event_formats`, `languages` — уникальные отсортированные списки.
- `ContractorMatcher::match(array $criteria): array` получает `city`, `date`, `category`, `event_format`, целый `budget`, nullable `hours` и `language`. Ввод проверяется Livewire-компонентом до вызова сервиса.
- PHP строго проверяет город, категорию, отсутствие даты в `busy_dates`, цену не выше бюджета, формат, необязательные язык и часы. Пустой `max_hours` означает неизвестный лимит: такой профиль не исключается, но карточка предлагает уточнить длительность.
- Даты ограничены интервалом 23.09.2026–31.12.2026. Бюджет: целое число 1–1 000 000 000 ₸; длительность: целое число 1–24 часа.
- Статусы результата: `matched`, `no_category`, `no_matches`. Причины исключения считаются независимо и могут пересекаться.
- Без AI кандидаты сортируются по цене, затем по строковому ID. Выбираются первые три. Цена всегда показывается как «от».
- `synthetic` явно маркируется. Восстановленные город и цена тоже отмечены.

## AI и резервный алгоритм

Установлен официальный `laravel/ai`. По умолчанию AI выключен, приложение полностью работает без внешнего API.

Для включения настройте `.env`:

```dotenv
CONTRACTOR_AI_ENABLED=true
CONTRACTOR_AI_PROVIDER=openai-compatible
CONTRACTOR_AI_MODEL=qwen3-8
OPENAI_COMPATIBLE_URL=https://llm.alem.ai/v1
OPENAI_COMPATIBLE_API_KEY=ваш_ключ
CONTRACTOR_CACHE_STORE=file
```

Затем выполните `php artisan config:clear`. Ключи не коммитятся.

## Витрина и ИИ-чат

На главной — витрина с кадрами форматов (`public/images/showcase/`) и боковой NLP-чат на модели Alem `qwen3-8`. В карточках результатов — фото, «Подробнее» и «Записаться».

## Сценарий демо на исходном CSV

Для воспроизводимости этих ID оставьте `CONTRACTOR_AI_ENABLED=false`, язык и часы пустыми.

| Действие | Параметры | Результат |
| --- | --- | --- |
| Три варианта | Алматы, Ведущий, свадьба, 23.09.2026, 1 500 000 ₸ | Мицури Канроджи (`HK-44923`), Эмилия (`HK-42352`), Сон Гоку (`HK-27222`) |
| Повтор | Тот же запрос | Те же профили в том же порядке |
| Смена даты | Тот же запрос, 24.09.2026 | Кики (`HK-35215`), один вариант |
| Редкая категория | Алматы, Флорист, свадьба, 23.09.2026, 500 000 ₸ | Тони Тони Чоппер (`HK-39372`), Тихиро Огино (`HK-90001`); второй синтетический |
| Нет категории | Зарубежье, Флорист, свадьба, 23.09.2026, 500 000 ₸ | В городе нет такой категории |
| Есть, но не подходят | Алматы, Флорист, свадьба, 23.09.2026, 1 ₸ | Нет подходящих: цена выше бюджета |

## Проверки

```sh
php artisan test --compact tests/Feature/Services tests/Feature/Livewire/ContractorFinderTest.php
php vendor/bin/phpstan analyse --memory-limit=512M
npm run build
```

Полный набор тестов стартового приложения: `php artisan test --compact`.
