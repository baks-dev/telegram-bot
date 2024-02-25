# BaksDev Telegram Bot

[![Version](https://img.shields.io/badge/version-7.0.12-blue)](https://github.com/baks-dev/telegram-bot/releases)
![php 8.2+](https://img.shields.io/badge/php-min%208.1-red.svg)

Модуль Telegram бота

## Установка

``` bash
$ composer require baks-dev/telegram-bot
```

## Дополнительно

Изменения в схеме базы данных с помощью миграции

``` bash
$ php bin/console doctrine:migrations:diff

$ php bin/console doctrine:migrations:migrate
```

Установка файловых ресурсов в публичную директорию (javascript, css, image ...):

``` bash
$ php bin/console baks:assets:install
```

Тесты

``` bash
$ php bin/phpunit --group=telegram-bot
```

## Лицензия ![License](https://img.shields.io/badge/MIT-green)

The MIT License (MIT). Обратитесь к [Файлу лицензии](LICENSE.md) за дополнительной информацией.
