# KarLancer Smart Assistant

پنل آزمایشی تک‌مدیره با Laravel 13، Blade/Tailwind و MySQL. اتصال واقعی کارلنسر، LLM و ارسال پیشنهاد عمداً غیرفعال است.

## Docker

```bash
cp .env.example .env
docker compose build
docker compose up -d
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan admin:create admin@example.test
```

پنل در `http://127.0.0.1:8000` است؛ MySQL و Python پورت میزبان ندارند.

## تست محلی

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan test
git diff --check
```

رمز مدیر فقط تعاملی با `admin:create` وارد می‌شود.
