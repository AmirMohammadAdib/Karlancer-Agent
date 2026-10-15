# Playwright collector

این collector فقط با ورود دستی کاربر اجرا می‌شود؛ CAPTCHA، stealth، endpoint خصوصی و زمان‌بندی unattended ندارد. selectorهای واقعی صفحه احراز‌شده هنوز در محیط توسعه بررسی نشده‌اند؛ تست‌ها fixture-based هستند.

```bash
python3 -m venv .venv && source .venv/bin/activate
pip install -r collector/requirements.txt
playwright install chromium
python -m collector.main --fixture collector/fixtures/sample.json
export LARAVEL_BASE_URL=http://127.0.0.1:8000
export COLLECTOR_TOKEN='توکن محلی خارج از Git'
python -m collector.main --fixture collector/fixtures/sample.json --send
python -m collector.main
```
