# Сервис импорта и агрегации отзывов из Яндекс.Карт

Веб-приложение для автоматического сбора, кэширования, фоновой догрузки и постраничного отображения информации об организациях и их отзывах. Развернуто на базе стека **Laravel 11 (Sanctum SPA)**, **Inertia.js**, **Vue 3 (Composition API)**, **Tailwind CSS** и **PostgreSQL**.

---

## 🚀 Быстрый локальный запуск (Docker Compose)

Проект полностью контейнеризирован и готов к запуску в любой ОС с установленным Docker Desktop / WSL2.

### 1. Клонирование репозитория
```bash
git clone <ссылка_на_ваш_репозиторий>
cd <папка_проекта>
```

### 2. Подготовка конфигурации окружения
Создайте файл `.env` в корневой директории проекта:
```bash
cp project/.env.example .env
cp project/.env.example project/.env
```
*Для корректной работы Docker Compose снаружи и Laravel внутри контейнеров, параметры СУБД в обоих файлах должны совпадать (см. раздел переменных окружения).*

### 3. Сборка и запуск контейнеров
```bash
docker compose up -d --build
```

### 4. Инициализация приложения и базы данных
Выполните поочередно команды для настройки зависимостей, генерации таблиц PostgreSQL и создания тестового пользователя:
```bash
# Установка PHP-пакетов
docker compose exec app composer install

# Генерация ключа безопасности
docker compose exec app php artisan key:generate

# Создание системных таблиц очередей и сессий
docker compose exec app php artisan queue:table
docker compose exec app php artisan session:table

# Накатывание миграций и запуск сидера пользователей
docker compose exec app php artisan migrate:fresh --seed --force

# Очистка конфигурационного кэша
docker compose exec app php artisan config:clear
```

### 5. Сборка фронтенда (Vue 3)
Скомпилируйте фронтенд-ресурсы в статические файлы:
```bash
# Установка Node-зависимостей на хост-машине (или внутри контейнера при наличии Node)
cd project && npm install && npm run build
```

### 6. Доступ к интерфейсам
* **Основной сайт:** `http://localhost:8080/login`
* **Панель управления БД Adminer:** `http://localhost:8081` (Система: *PostgreSQL*, Сервер: *db*, Логин/Пароль: *postgres*)
* **Данные тестового аккаунта:** `someemail@gmail.com` / `password`

---

## ⚙️ Переменные окружения (.env)

Пример оптимальной конфигурации для файла `.env` в корне и внутри папки `project/`:

```env
APP_NAME="Yandex Review Aggregator"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8080

# Подключение к СУБД внутри Docker-сети
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=postgres
DB_PASSWORD=postgres

# Настройка безопасной SPA-авторизации Sanctum/Inertia
SANCTUM_STATEFUL_DOMAINS=localhost:8080,127.0.0.1:8080,localhost:5173,127.0.0.1:5173
SESSION_DOMAIN=localhost

# Хранение сессий, кэша и фоновых задач в БД для разгрузки RAM
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

