# Pipeline CI на PHP в GitHub Actions

Десятая практическая работа. Цель — настроить CI для консольного приложения PHP 8.2: проверить исходники и тесты, собрать Docker-образ и сохранить его как артефакт. Программа выводит `Hello from PHP in Docker! 🐳`. [Методичка преподавателя](https://github.com/rurewa/imfua/blob/main/content/DevOps/CI_CD/Pipelines/PHP.md)

Локальные проверки и первый запуск GitHub Actions прошли. Docker-образ из артефакта восстановлен и проверен локально. [Первый запуск](https://github.com/aleks-maker-maker/my-php-app/actions/runs/44564921824)

## Структура проекта

| Файл или каталог | Назначение |
| --- | --- |
| `src/greeting.php` | Функция `getGreeting()` возвращает строку в UTF‑8. |
| `src/index.php` | PHP CLI подключает функцию и выводит результат с переводом строки. |
| `tests/GreetingTest.php`, `phpunit.xml` | Четыре теста, настройки запуска PHPUnit и обязательная непустая коллекция тестов. |
| `composer.json`, `composer.lock` | Требования проекта и зафиксированные зависимости для тестов. |
| `Dockerfile`, `.dockerignore` | Две стадии на PHP CLI и минимальный контекст приложения. |
| `.github/workflows/ci.yml` | Три последовательных задания GitHub Actions. |
| `.gitignore`, `screenshots/` | Исключения Git и фактические снимки работы. |

## PHP, Composer и PHPUnit

PHP CLI выполняет `src/index.php`. Первые три теста PHPUnit вызывают рабочую функцию и проверяют точную строку и наличие слов PHP и Docker. Четвёртый тест запускает точку входа отдельным процессом и сравнивает stdout, stderr и код завершения. Composer устанавливает тестовые зависимости из lock-файла. [Совместимость версий PHPUnit](https://phpunit.de/supported-versions.html)

## Локальные проверки в Windows PowerShell

Откройте PowerShell в корне проекта. Команды используют Docker Desktop в режиме Linux-контейнеров и не требуют установленного PHP на Windows.

Для Composer соберите локальный образ проверки. В нём PHP 8.2 предоставляет `mbstring`, Composer берётся из официального образа, а `unzip` распаковывает пакеты.

~~~powershell
$recipe = @(
    'FROM composer:2 AS composer'
    'FROM php:8.2-cli-bookworm'
    'RUN sed -i ''s|http://deb.debian.org|https://deb.debian.org|g'' /etc/apt/sources.list.d/debian.sources \'
    '    && apt-get update \'
    '    && apt-get install -y --no-install-recommends unzip \'
    '    && rm -rf /var/lib/apt/lists/*'
    'COPY --from=composer /usr/bin/composer /usr/local/bin/composer'
) -join [char]10
$recipe | docker build -t my-php-app-check:local -
~~~

~~~powershell
$source = (Resolve-Path -LiteralPath .).Path
$bind = "type=bind,source=$source,target=/app"
docker run --rm --mount $bind -w /app my-php-app-check:local composer validate --strict
docker run --rm --mount $bind -w /app my-php-app-check:local composer install --no-interaction --no-progress --prefer-dist
docker run --rm --network none --mount "$bind,readonly" -w /app php:8.2-cli-bookworm bash -eo pipefail -c 'find src tests -type f -name "*.php" -print0 | xargs -0 -r -n 1 php -l'
docker run --rm --network none --mount "$bind,readonly" -w /app php:8.2-cli-bookworm php vendor/bin/phpunit --configuration phpunit.xml --testdox
~~~

## Docker

Стадия `builder` проверяет синтаксис приложения перед копированием. Стадия запуска использует PHP CLI, содержит только исходники и запускает PHP от `appuser`.

~~~powershell
docker build -t my-php-app:latest .
docker run --rm --network none my-php-app:latest
$LASTEXITCODE
docker run --rm --network none my-php-app:latest id
~~~

Ожидаемая строка — `Hello from PHP in Docker! 🐳`. Код выхода должен быть `0`, а `id` должен показать `appuser` с UID больше нуля.

## GitHub Actions

Workflow реагирует на push и pull request для `main` и `master`. На вкладке Actions также доступен ручной запуск **PHP CI Pipeline**.

1. **Syntax Check** проверяет Composer и синтаксис всех PHP-файлов в Git.
2. **Run Tests** устанавливает зависимости из `composer.lock` и выполняет PHPUnit.
3. **Build Docker Image** собирает образ через Buildx, сохраняет `docker-image.tar.gz` как артефакт **docker-image** на семь дней и проверяет запуск. Образ остаётся в артефакте GitHub Actions.

## Локальные результаты

| Проверка | Результат |
| --- | --- |
| PHP 8.2 CLI | Синтаксис трёх PHP-файлов корректен. |
| Composer | `validate --strict` и установка из `composer.lock` прошли. |
| PHPUnit | 4 теста, 7 проверок. |
| Workflow | `actionlint` 1.7.12 прошёл. |
| Docker | Сборка и `docker save` прошли. Контейнер вывел точное UTF‑8-приветствие, завершился с кодом `0`, работал от `appuser` с UID `1000`. |

## Технические отличия от методички

- `getGreeting()` вынесена из точки входа. Тесты функции могут загрузить её без вывода в stdout. Отдельный тест проверяет запуск `index.php`.
- Добавлены Composer и PHPUnit 11, совместимый с PHP 8.2. CI устанавливает зафиксированные зависимости. Проверка синтаксиса передаёт ошибки PHP через код выхода `xargs`, а ошибки Composer и PHPUnit останавливают задания.
- Для обеих стадий Docker выбран официальный `php:8.2-cli-bookworm`. Образ запуска содержит исходники приложения. PHPUnit и зависимости остаются в среде тестирования.
- Обновлены `checkout@v7`, `setup-buildx-action@v4`, `build-push-action@v7` и `upload-artifact@v7`. Для PHP используется `shivammathur/setup-php@v2`. Артефакт формируется в `RUNNER_TEMP`, а проверка образа сравнивает точный stdout, код выхода, имя пользователя и UID.

## Скриншоты локальных проверок

### Структура проекта и исходники

![Структура проекта и исходники](screenshots/01-project-and-source.png)

### Проверка синтаксиса трёх PHP-файлов

![Проверка синтаксиса трёх PHP-файлов](screenshots/02-php-syntax.png)

### Четыре теста PHPUnit

![Четыре теста PHPUnit](screenshots/03-phpunit.png)

### Сборка Docker-образа

![Сборка Docker-образа](screenshots/04-docker-build.png)

### Приветствие, код выхода и пользователь контейнера

![Приветствие, код выхода и пользователь контейнера](screenshots/05-docker-run.png)

## Результаты GitHub Actions и Docker-артефакта

Первый запуск для коммита `04b9c9a` завершился со статусом **Success**. [Запуск № 1](https://github.com/aleks-maker-maker/my-php-app/actions/runs/44564921824) · [Запуски для ветки main](https://github.com/aleks-maker-maker/my-php-app/actions/workflows/ci.yml?query=branch%3Amain)

| Задание | Статус | Что подтверждено в логах |
| --- | --- | --- |
| [Syntax Check](https://github.com/aleks-maker-maker/my-php-app/actions/runs/44564921824/job/135703938427) | Success | `composer validate --strict` и синтаксис трёх PHP-файлов. |
| [Run Tests](https://github.com/aleks-maker-maker/my-php-app/actions/runs/44564921824/job/135704059960) | Success | Установка из `composer.lock`, PHPUnit 11.5.58, 4 теста и 7 проверок. |
| [Build Docker Image](https://github.com/aleks-maker-maker/my-php-app/actions/runs/44564921824/job/135704204732) | Success | Сборка, загрузка артефакта, точное приветствие, код выхода 0 и `appuser` с UID 1000. |

Артефакт **docker-image**, ID `14550589509`. Размер ZIP — **179 130 287 байт**. SHA‑256 ZIP — `38612ab95560f84fa65084d35738176dd2aec4ece4f9b0ad7fadd9d85eeaf3af`. Внутри ZIP находится `docker-image.tar.gz`. [Артефакт первого запуска](https://github.com/aleks-maker-maker/my-php-app/actions/runs/44564921824/artifacts/14550589509)

Размер и SHA‑256 скачанного ZIP совпали с данными GitHub. Команда `docker load` восстановила `my-php-app:latest`. Образ вывел `Hello from PHP in Docker! 🐳` с переводом строки, завершился с кодом `0` и запустился от `appuser` с UID `1000`.

### Восстановление образа в PowerShell

Войдите на GitHub, скачайте ZIP со страницы артефакта и сохраните его как `docker-image.zip` в отдельной временной папке. Выполните команды из этой папки.

~~~powershell
$expectedSha256 = '38612ab95560f84fa65084d35738176dd2aec4ece4f9b0ad7fadd9d85eeaf3af'
$actualSha256 = (Get-FileHash -LiteralPath .\docker-image.zip -Algorithm SHA256).Hash
if ($actualSha256 -ine $expectedSha256) { throw 'SHA-256 mismatch' }
Expand-Archive -LiteralPath .\docker-image.zip -DestinationPath .\expanded
docker load -i .\expanded\docker-image.tar.gz
if ($LASTEXITCODE -ne 0) { throw 'docker load failed' }
docker run --rm --network none my-php-app:latest
if ($LASTEXITCODE -ne 0) { throw 'Application failed' }
docker run --rm --network none my-php-app:latest id
~~~

Для другого запуска используйте его ZIP и значение `digest` из GitHub. Артефакт хранится семь дней.

### Сводка первого запуска

![Статус Success и последовательность трёх заданий](screenshots/06-actions-workflow.png)

### Syntax Check

![Проверки Composer и синтаксиса PHP в GitHub Actions](screenshots/07-syntax-job.png)

### Run Tests

![Установка зависимостей и запуск PHPUnit](screenshots/08-phpunit-job.png)

### Build Docker Image

![Docker build, публикация артефакта и проверка контейнера](screenshots/09-docker-job.png)

### Артефакт docker-image

![Артефакт с размером и контрольной суммой на странице GitHub Actions](screenshots/10-actions-artifact.png)

### Восстановление и запуск образа

![Проверка SHA‑256 ZIP, docker load, приветствие, код выхода и UID](screenshots/11-artifact-docker-run.png)
