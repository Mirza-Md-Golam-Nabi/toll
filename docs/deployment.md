# ডিপ্লয়মেন্ট গাইড — টোল ম্যানেজমেন্ট সিস্টেম

একই কোড দুইভাবে চলে:

| মোড | কোথায় | কাজ |
|---|---|---|
| `booth` | টোল বুথের Windows PC | অপারেটর এখানে টিকিট কাটেন; ইন্টারনেট ছাড়াই চলে |
| `central` | অনলাইন সার্ভার (VPS) | সব বুথের ডাটা জমা হয়; ম্যানেজার যেকোনো জায়গা থেকে রিপোর্ট দেখেন |

সেন্ট্রাল সার্ভার ঐচ্ছিক। না থাকলে বুথ **স্বতন্ত্রভাবে (stand-alone)** চলে এবং সব সেটিংস বুথেই করা যায়।

---

## ১. বুথ PC সেটআপ (Windows)

### প্রয়োজনীয় জিনিস
- Windows 10/11, অন্তত 4 GB RAM
- [Laragon](https://laragon.org) (PHP 8.3 + MySQL 8 সহ)
- Google Chrome
- 58mm থার্মাল প্রিন্টার (USB), ড্রাইভারসহ ইনস্টল করা

### ধাপসমূহ
1. প্রজেক্ট ফোল্ডারটি `C:\laragon\www\toll-management` এ কপি করুন। Laragon চালু করলে `http://toll-management.test` ঠিকানা স্বয়ংক্রিয়ভাবে তৈরি হবে।
2. Laragon-এর **Menu → PHP → php.ini** খুলে নিচের লাইনগুলো যোগ/চালু করুন (সফটওয়্যার অনেক দ্রুত হবে):
   ```ini
   zend_extension=opcache
   opcache.enable=1
   opcache.memory_consumption=192
   opcache.max_accelerated_files=20000
   ```
   তারপর Laragon **Stop → Start** করুন।
3. `deploy\booth\.env.booth.example` ফাইলটি প্রজেক্ট রুটে `.env` নামে কপি করুন এবং মানগুলো ঠিক করুন:
   - `TOLL_BOOTH_CODE` — এই বুথের কোড (যেমন `B01`)
   - `TOLL_CENTRAL_URL`, `TOLL_SYNC_TOKEN` — সেন্ট্রাল সার্ভার থাকলে; না থাকলে খালি রাখুন
   - `TOLL_PRINTER_TARGET` — Windows-এ প্রিন্টারের **share name** (নিচে দেখুন)
4. Laragon Terminal খুলে চালান:
   ```bat
   cd C:\laragon\www\toll-management
   deploy\booth\install.bat
   ```
   শেষে প্রথম সুপার অ্যাডমিন অ্যাকাউন্ট তৈরি করতে বলবে (সেন্ট্রালের সাথে যুক্ত বুথে ইউজার সেন্ট্রাল থেকে আসে — তখন Ctrl+C চেপে বাদ দিন)।
5. **Administrator** হিসেবে চালান (অটো-সিঙ্কের জন্য প্রতি মিনিটের টাস্ক):
   ```bat
   deploy\booth\register-scheduler.bat
   ```
6. `deploy\booth\start-collection.bat` ফাইলের শর্টকাট ডেস্কটপে রাখুন। এটি কালেকশন স্ক্রিন ফুল-স্ক্রিনে খোলে।

### থার্মাল প্রিন্টার
**পদ্ধতি ক — সরাসরি ESC/POS (প্রস্তাবিত, দ্রুততম)**
1. Control Panel → Devices and Printers → প্রিন্টারে ডান-ক্লিক → Printer properties → **Sharing** → "Share this printer" চালু করুন, share name দিন যেমন `POS58`।
2. `.env`-এ: `TOLL_PRINTER_DRIVER=escpos`, `TOLL_PRINTER_CONNECTOR=windows`, `TOLL_PRINTER_TARGET=POS58`
3. নেটওয়ার্ক প্রিন্টার হলে: `TOLL_PRINTER_CONNECTOR=network`, `TOLL_PRINTER_TARGET=<প্রিন্টারের IP>`
4. `php artisan optimize` চালান, তারপর **সেটিংস → টেস্ট প্রিন্ট** দিয়ে যাচাই করুন।

**পদ্ধতি খ — ব্রাউজার প্রিন্ট (বিকল্প)**
`TOLL_PRINTER_DRIVER=browser` রাখুন এবং থার্মাল প্রিন্টারকে Windows-এর **Default printer** করুন। `start-collection.bat` Chrome-কে `--kiosk-printing` দিয়ে চালায়, তাই কোনো ডায়ালগ ছাড়াই রসিদ প্রিন্ট হয়।

> সরাসরি প্রিন্ট ব্যর্থ হলে সফটওয়্যার স্বয়ংক্রিয়ভাবে ব্রাউজার প্রিন্টে চলে যায় — টিকিট কাটা কখনো আটকায় না।

### `.env` পরিবর্তনের পর
```bat
php artisan optimize
```

---

## ২. সেন্ট্রাল সার্ভার সেটআপ (Linux VPS)

1. সার্ভারে PHP 8.3 (mysql, mbstring, intl, gd, zip extension), Composer, MySQL 8, Nginx ইনস্টল করুন। Nginx-এর root হবে `.../toll-management/public`।
2. HTTPS চালু করুন (যেমন `certbot`) — বুথ সিঙ্ক টোকেন পাঠায়, তাই এনক্রিপশন জরুরি।
3. MySQL-এ ডাটাবেজ ও ইউজার তৈরি করুন।
4. `deploy/central/.env.central.example` → `.env` কপি করুন; `TOLL_SYNC_TOKEN` এ একটি লম্বা র‍্যান্ডম মান দিন:
   ```bash
   php -r "echo bin2hex(random_bytes(32));"
   ```
5. চালান (প্রতিবার আপডেটের সময়ও এটাই):
   ```bash
   bash deploy/central/deploy.sh
   ```
6. Cron-এ যোগ করুন:
   ```
   * * * * * cd /var/www/toll-management && php artisan schedule:run >> /dev/null 2>&1
   ```
7. প্যানেলে **কনফিগারেশন → বুথসমূহ** থেকে প্রতিটি বুথ তৈরি করুন — কোড অবশ্যই বুথের `TOLL_BOOTH_CODE` এর সাথে মিলতে হবে। অপারেটর ইউজার তৈরি করে সংশ্লিষ্ট বুথ নির্ধারণ করুন।
8. প্রতিটি বুথের `.env`-এ একই `TOLL_SYNC_TOKEN` এবং `TOLL_CENTRAL_URL` দিন।

### সিঙ্ক কীভাবে কাজ করে
- বুথ → সেন্ট্রাল: শিফট সেশন, টিকিট, অডিট লগ (UUID দিয়ে, তাই কোনো সংঘর্ষ হয় না; একই ডাটা দুইবার গেলেও একবারই জমা হয়)।
- সেন্ট্রাল → বুথ: বুথ, শিফট, ইউজার, ক্যাটাগরি, রেট, সেটিংস।
- সেন্ট্রালের সাথে যুক্ত বুথে মাস্টার ডাটা শুধু সেন্ট্রালে এডিট করা যায়।
- উপরের বারে সবুজ/হলুদ/লাল বিন্দু সিঙ্কের অবস্থা দেখায়। হাতে চালাতে: `php artisan toll:sync`

---

## ৩. দৈনন্দিন রক্ষণাবেক্ষণ

| কাজ | কমান্ড |
|---|---|
| হাতে সিঙ্ক | `php artisan toll:sync` |
| নতুন সুপার অ্যাডমিন / পাসওয়ার্ড রিসেট | `php artisan toll:create-admin` |
| কোড আপডেটের পর (বুথ) | `composer install --no-dev -o && php artisan migrate --force && php artisan filament:assets && php artisan optimize` |
| লগ দেখা | `storage\logs\laravel-YYYY-MM-DD.log` |
| ডাটাবেজ ব্যাকআপ | `mysqldump -uroot toll_management > backup.sql` |

সমস্যা হলে প্রথমে **সেটিংস → সিস্টেমের অবস্থা** পেজটি দেখুন।
