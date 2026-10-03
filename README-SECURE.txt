MAXIM PORTFOLIO — SERVER SETUP

Залить содержимое ZIP на PHP-сервер:
  index.html
  api.php
  favicon.svg
  data/projects.php
  data/.htaccess (если Apache)

Вход разработчика по умолчанию:
  Логин: maxim
  Пароль: MaximDev!2026#Secure

Что защищено:
  - добавить/удалить проект можно только после серверного входа;
  - данные проектов лежат на сервере;
  - сервер повторно проверяет текст на запрещённый контент;
  - projects.php не отдаёт содержимое как JSON при прямом открытии;
  - dev-панель не является доступной гостям.

ВАЖНО:
Для постоянного использования лучше задать переменные окружения:
  PORTFOLIO_ADMIN_USER
  PORTFOLIO_ADMIN_HASH

Хэш нового пароля:
  php -r "echo password_hash('НОВЫЙ_ПАРОЛЬ', PASSWORD_DEFAULT), PHP_EOL;"

Папка data должна быть доступна PHP на запись.
Нужен PHP 7.4+ (лучше 8.x).
