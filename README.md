# Русская рулетка

Простой развлекательный бот для ТГ группы

## Правила

- Если на слот-машине (🎰) выпало 3 одинаковых символа — у человека есть 10 минут, чтобы два раза подряд бросить 6 на кубике (🎲)
- Не успел — бот мутит пользователя (по умолчанию 1-24 часа)
- Время на выполнение и время мута можно менять в config.php
- В админке можно посмотреть статистику игроков, удалить записи и т.д.

## Установка

1. Закинуть файлы на сервер c поддержкой PHP и SQLite
2. В `/core/config.example.php` заполнить данные, переименовать в `/core/config.php`
3. В `/core/` создать `db.sqlite`
4. Зайти в браузере в админку, авторизоваться
5. Перейти на `/admin/setup.php`, создаст базу, зарегистрирует вебхук
6. Настроить cron — запускать `cron.php` каждую минуту, чтобы бот сам занимался мутом