# 🍽️ Mini Restaurant API

نظام إدارة مطعم شامل مبني بـ Laravel يوفر واجهة برمجة تطبيقات (API) لإدارة الطلبات، الحجوزات، قائمة الطعام، والمدفوعات.

## 📋 المحتويات

- [المميزات](#-المميزات)
- [متطلبات النظام](#️-متطلبات-النظام)
- [التثبيت](#-التثبيت)
- [إعداد البيئة](#️-إعداد-البيئة)
- [API Endpoints](#-api-endpoints)
- [المصادقة](#-المصادقة)
- [نماذج البيانات](#-نماذج-البيانات)
- [الاختبار](#-الاختبار)
- [المعمارية](#️-المعمارية)

## ✨ المميزات

### 🍽️ إدارة قائمة الطعام
- عرض جميع عناصر القائمة المتاحة
- إدارة المخزون والكميات المتاحة
- نظام الأسعار والتصنيفات

### 📦 إدارة الطلبات
- إنشاء طلبات جديدة مع عناصر متعددة
- تتبع حالة الطلبات
- حساب المبلغ الإجمالي تلقائياً
- إدارة المخزون عند الطلب

### 🪑 نظام الحجوزات
- حجز الطاولات بتاريخ ووقت محدد
- التحقق من توفر الطاولات
- إدارة عدد الضيوف

### ⏳ قائمة الانتظار
- إضافة العملاء لقائمة الانتظار
- عرض قائمة الانتظار للمستخدم
- إدارة ترتيب الانتظار

### 💳 نظام الدفع
- دعم PayPal للمدفوعات الإلكترونية
- معالجة callbacks للدفع
- تتبع حالة المدفوعات

## ⚙️ متطلبات النظام

- PHP >= 8.2
- Composer
- Laravel 12.x
- MySQL/PostgreSQL
- Node.js & NPM (للـ frontend assets)

## 🚀 التثبيت

### 1. استنساخ المشروع
```bash
git clone <repository-url>
cd apps_squre
```

### 2. تثبيت Dependencies
```bash
# تثبيت PHP dependencies
composer install

# تثبيت Node dependencies (إذا كانت متوفرة)
npm install
```

### 3. إعداد البيئة
```bash
# نسخ ملف البيئة
cp .env.example .env

# توليد مفتاح التطبيق
php artisan key:generate
```

### 4. إعداد قاعدة البيانات
```bash
# تشغيل المهاجرات
php artisan migrate

# تشغيل Seeders (اختياري)
php artisan db:seed
```

### 5. تشغيل الخادم
```bash
php artisan serve
```

## 🛠️ إعداد البيئة

### ملف .env
```env
APP_NAME="Mini Restaurant API"
APP_ENV=local
APP_KEY=base64:your-app-key
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mini_restaurant
DB_USERNAME=your_username
DB_PASSWORD=your_password

# PayPal Configuration
PAYPAL_MODE=sandbox
PAYPAL_SANDBOX_CLIENT_ID=your_sandbox_client_id
PAYPAL_SANDBOX_CLIENT_SECRET=your_sandbox_client_secret
PAYPAL_LIVE_CLIENT_ID=your_live_client_id
PAYPAL_LIVE_CLIENT_SECRET=your_live_client_secret
```

## 🔌 API Endpoints

### 🔐 المصادقة
```http
POST /api/auth/register    # تسجيل مستخدم جديد
POST /api/auth/login       # تسجيل الدخول
POST /api/auth/logout      # تسجيل الخروج
```

### 🍽️ قائمة الطعام
```http
GET /api/menu-items        # عرض جميع عناصر القائمة المتاحة
```

### 📦 الطلبات
```http
GET /api/orders           # عرض طلبات المستخدم
POST /api/orders          # إنشاء طلب جديد
GET /api/orders/{id}      # عرض تفاصيل طلب محدد
```

#### مثال إنشاء طلب جديد
```json
POST /api/orders
{
    "items": [
        {
            "menu_item_id": 1,
            "quantity": 2
        },
        {
            "menu_item_id": 3,
            "quantity": 1
        }
    ],
    "special_instructions": "بدون بصل"
}
```

### 🪑 الحجوزات
```http
GET /api/reservations     # عرض حجوزات المستخدم
POST /api/reservations    # إنشاء حجز جديد
```

#### مثال إنشاء حجز جديد
```json
POST /api/reservations
{
    "table_id": 1,
    "reservation_date": "2025-09-20",
    "reservation_time": "19:00",
    "guests_count": 4,
    "notes": "احتفال عيد ميلاد"
}
```

### ⏳ قائمة الانتظار
```http
GET /api/waiting-list     # عرض قائمة الانتظار للمستخدم
POST /api/waiting-list    # إضافة للقائمة الانتظار
```

### 💳 المدفوعات
```http
POST /api/payments        # إنشاء عملية دفع
POST /api/payment/callback # معالجة callback من PayPal
```

## 🔑 المصادقة

يستخدم النظام Laravel Sanctum للمصادقة. يجب تضمين token في header لجميع الطلبات المحمية:

```http
Authorization: Bearer your-auth-token
```

### تسجيل الدخول
```json
POST /api/auth/login
{
    "email": "user@example.com",
    "password": "password"
}
```

### الاستجابة
```json
{
    "status": 200,
    "message": "Login successful",
    "data": {
        "user": {...},
        "token": "your-auth-token"
    }
}
```

## 📊 نماذج البيانات

### User (المستخدم)
- `id` - معرف المستخدم
- `name` - الاسم
- `email` - البريد الإلكتروني
- `email_verified_at` - تاريخ تأكيد البريد
- `created_at` - تاريخ الإنشاء

### MenuItem (عنصر القائمة)
- `id` - معرف العنصر
- `name` - اسم الطبق
- `description` - وصف الطبق
- `price` - السعر
- `category` - التصنيف
- `available_quantity` - الكمية المتاحة
- `is_available` - متاح/غير متاح

### Order (الطلب)
- `id` - معرف الطلب
- `user_id` - معرف المستخدم
- `total_amount` - المبلغ الإجمالي
- `status` - حالة الطلب (pending, confirmed, preparing, ready, delivered)
- `notes` - ملاحظات خاصة

### OrderItem (عنصر الطلب)
- `id` - معرف عنصر الطلب
- `order_id` - معرف الطلب
- `menu_item_id` - معرف عنصر القائمة
- `quantity` - الكمية
- `price` - السعر
- `discount` - الخصم

### Reservation (الحجز)
- `id` - معرف الحجز
- `user_id` - معرف المستخدم
- `table_id` - معرف الطاولة
- `reservation_time` - وقت الحجز
- `guests_count` - عدد الضيوف
- `status` - حالة الحجز
- `notes` - ملاحظات

### Table (الطاولة)
- `id` - معرف الطاولة
- `number` - رقم الطاولة
- `capacity` - السعة
- `location` - الموقع
- `is_available` - متاحة/محجوزة

### WaitingListEntry (قائمة الانتظار)
- `id` - معرف الإدخال
- `user_id` - معرف المستخدم
- `guests_count` - عدد الضيوف
- `status` - حالة الانتظار
- `estimated_wait_time` - الوقت المتوقع للانتظار

### Invoice (الفاتورة)
- `id` - معرف الفاتورة
- `order_id` - معرف الطلب
- `amount` - المبلغ
- `tax_amount` - مبلغ الضريبة
- `payment_status` - حالة الدفع
- `payment_details` - تفاصيل الدفع (JSON)

## 🧪 الاختبار

### تشغيل الاختبارات
```bash
php artisan test
# أو
./vendor/bin/pest
```

### مجموعة Postman
يتضمن المشروع مجموعة Postman كاملة مع أمثلة لجميع الـ endpoints:
- `Mini_Restaurant_API.postman_collection.json`

## 📚 الوثائق التفصيلية

للحصول على دليل شامل لنظام الدفع، راجع:
- [دليل نظام الدفع الشامل](PAYMENT_SYSTEM_COMPLETE_GUIDE.md)

## 🏗️ المعمارية

### Design Patterns المستخدمة
- **Repository Pattern**: لفصل منطق الوصول للبيانات
- **Service Layer Pattern**: لمنطق الأعمال
- **Strategy Pattern**: لحساب الضرائب والرسوم
- **Observer Pattern**: للإشعارات (مستقبلياً)

### مبادئ SOLID المطبقة
- **Single Responsibility**: كل كلاس له مسؤولية واحدة
- **Open/Closed**: مفتوح للتوسع، مغلق للتعديل
- **Interface Segregation**: فصل الواجهات
- **Dependency Inversion**: الاعتماد على التجريدات

### هيكل المجلدات
```
app/
├── Http/
│   ├── Controllers/Api/     # API Controllers
│   ├── Requests/           # Form Requests للتحقق من البيانات
│   ├── Resources/          # API Resources للاستجابات
│   ├── Services/           # Business Logic Services
│   ├── Repositories/       # Data Access Layer
│   ├── Interfaces/         # Repository Interfaces
│   └── Traits/            # Shared Traits
├── Models/                # Eloquent Models
└── Providers/            # Service Providers
```

### Response Format
جميع API responses تتبع التنسيق التالي:
```json
{
    "status": 200,
    "message": "Success message",
    "error": null,
    "data": {...}
}
```
