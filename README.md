# Mini Restaurant Reservation System

نظام حجز المطاعم المصغر هو منصة شاملة تتيح للعملاء حجز الطاولات وعرض قوائم الطعام وتقديم الطلبات وإنشاء الفواتر مع نظام دفع متطور يدعم بوابات متعددة.

## 🚀 الميزات الجديدة (v2.0)

### 💳 نظام الدفع المتطور
- **Factory Pattern** لإدارة بوابات الدفع ديناميكياً
- **Strategy Pattern** لحساب الضرائب والرسوم
- **3 بوابات دفع**: PayPal, Stripe, Paymob (مع InstaPay)
- **Universal Callback Handler** موحد لجميع البوابات
- **HMAC Security** للحماية الأمنية
- **Payment Verification** المباشر من APIs

### 🏗️ Architecture المحسنة
- **Clean Code** متبع لمبادئ SOLID
- **Open/Closed Principle** لإضافة بوابات جديدة
- **Interface Segregation** للفصل بين المسؤوليات
- **Dependency Inversion** للمرونة والاختبار

## الميزات الرئيسية

### 🔐 نظام المصادقة
- تسجيل حساب جديد
- تسجيل الدخول والخروج
- مصادقة آمنة باستخدام Laravel Sanctum

### 🍽️ إدارة قائمة الطعام
- عرض الأصناف المتاحة فقط
- إدارة الكميات اليومية المحدودة
- إعادة تعيين الكميات تلقائياً يومياً

### 🪑 إدارة الطاولات والحجوزات
- فحص توفر الطاولات حسب التاريخ والوقت وعدد الضيوف
- حجز الطاولات المتاحة
- منع الحجز المزدوج للطاولة الواحدة

### 📋 قائمة الانتظار
- انضمام العملاء لقائمة الانتظار عند امتلاء الطاولات
- إدارة قائمة الانتظار بنظام الأولوية

### 🛒 إدارة الطلبات
- تقديم طلبات من الأصناف المتاحة
- التحقق من الكميات المتاحة قبل تأكيد الطلب
- تطبيق الخصومات على الأصناف
- حساب التكلفة الإجمالية

### 💳 نظام الدفع المتطور
- **3 بوابات دفع مدعومة**:
  - **PayPal**: دفع عالمي آمن
  - **Stripe**: دفع بالبطاقات الائتمانية
  - **Paymob**: دفع محلي م��ري (كارت + InstaPay)
- **خيارين للدفع**:
  - **الخيار الأول**: 14% ضرائب + 20% رسوم خدمة
  - **الخيار الثاني**: 15% رسوم خدمة فقط
- **دعم InstaPay**: محافظ الهاتف المحمول المصرية
- إنشاء فاتورة مفصلة بعد الدفع

## التقنيات المستخدمة

- **Laravel 11** - إطار العمل الأساسي
- **Laravel Sanctum** - نظام المصادقة
- **MySQL** - قاعدة البيانات
- **Pest** - إطار الاختبارات
- **Repository Pattern** - نمط تصميم للبيانات
- **Service Layer** - طبقة الخدمات
- **Strategy Pattern** - نمط الاستراتيجية للدفع
- **Factory Pattern** - مصنع بوابات الدفع
- **PayPal SDK** - تكامل PayPal
- **Stripe SDK** - تكامل Stripe
- **Paymob API** - تكامل Paymob مع InstaPay

## API Endpoints

### المصادقة
```http
POST /api/auth/register     # تسجيل حساب جديد
POST /api/auth/login        # تسجيل الدخول
POST /api/auth/logout       # تسجيل الخروج
GET  /api/auth/me          # معلومات المستخدم
```

### قائمة الطعام
```http
GET /api/v1/menu           # عرض قائمة الطعا��
```

### الطاولات والحجوزات
```http
GET  /api/v1/tables                    # عرض الطاولات
GET  /api/v1/tables/availability       # فحص التوفر
POST /api/v1/reservations              # حجز طاولة
GET  /api/v1/reservations              # عرض الحجوزات
```

### الطلبات
```http
POST /api/v1/orders                    # تقديم طلب جديد
GET  /api/v1/orders                    # عرض الطلبات
GET  /api/v1/orders/{id}               # تفاصيل طلب
```

### نظام الدفع الجديد
```http
# معلومات الدفع
GET  /api/v1/payment-methods           # خيارات الدفع
GET  /api/v1/payment-gateways          # بوابات الدفع المتاحة

# معالجة الدفع
POST /api/v1/orders/{id}/pay           # دفع طلب (PayPal فقط)

# حالة الدفع والفواتير
GET  /api/v1/orders/{id}/payment-status     # حالة الدفع
GET  /api/v1/invoices/{id}                  # تفاصيل الفاتورة
GET  /api/v1/payment/{gateway}/verify/{transactionId}  # تحقق من الدفع

# Callbacks والإشعارات (عامة - بدون مصادقة)
POST /api/payment/{gateway}/callback         # معالج عام للاستجابات
GET  /api/payment/paypal/success            # نجاح PayPal
GET  /api/payment/paypal/cancel             # إلغاء PayPal
POST /api/webhooks/stripe                   # webhook من Stripe
POST /api/webhooks/paymob                   # webhook من Paymob
```

### قائمة الانتظار
```http
POST /api/v1/waiting-list              # انضمام لقائمة الانتظار
GET  /api/v1/waiting-list              # عرض قائمة الانتظار
DELETE /api/v1/waiting-list/{id}       # مغادرة قائمة الانتظار
```

## 🛠️ التثبيت والإعداد

### 1. استنساخ المشروع
```bash
git clone <repository-url>
cd mini-restaurant-system
```

### 2. تثبيت التبعيات
```bash
composer install
npm install
```

### 3. إعداد البيئة
```bash
cp .env.example .env
php artisan key:generate
```

### 4. إعداد قاعدة البيانات
```bash
# تحديث إعدادات قاعدة البيانات في .env
php artisan migrate --seed
```

### 5. إعداد بوابات الدفع
```bash
# إضافة مفاتيح API في .env
PAYPAL_CLIENT_ID=your_paypal_client_id
PAYPAL_CLIENT_SECRET=your_paypal_client_secret
STRIPE_PUBLIC_KEY=pk_test_your_stripe_public_key
STRIPE_SECRET_KEY=sk_test_your_stripe_secret_key
PAYMOB_API_KEY=your_paymob_api_key
PAYMOB_INTEGRATION_ID=your_integration_id
PAYMOB_INSTAPAY_INTEGRATION_ID=your_instapay_integration_id
```

### 6. تشغيل الخادم
```bash
php artisan serve
```

## 💳 نظام الدفع - أمثلة الاستخدام

### دفع بـ Stripe
```json
POST /api/v1/orders/123/pay
{
    "payment_option": 1,
    "payment_gateway": "stripe",
    "payment_data": {
        "currency": "usd"
    }
}
```

### دفع بـ PayPal
```json
POST /api/v1/orders/123/pay
{
    "payment_option": 2,
    "payment_gateway": "paypal",
    "payment_data": {
        "currency": "USD"
    }
}
```

### دفع بـ InstaPay (Paymob)
```json
POST /api/v1/orders/123/pay
{
    "payment_option": 1,
    "payment_gateway": "paymob",
    "payment_data": {
        "payment_method": "instapay",
        "mobile_number": "+201234567890",
        "currency": "EGP"
    }
}
```

## 📊 هيكل قاعدة البيانات

### الجداول الرئيسية
- `users` - المستخدمين
- `tables` - الطاولات
- `menu_items` - أصناف الطعام
- `reservations` - الحجوزات
- `orders` - الطلبات
- `order_items` - تفاصيل الطلبات
- `invoices` - الفواتير (محدث بدعم بوابات الدفع)
- `waiting_list_entries` - قائمة الانتظار

### الحقول الجديدة في جدول الفواتير
- `payment_gateway` - بوابة الدفع المستخدمة
- `transaction_id` - رقم المعاملة
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
- **Factory Pattern**: لإنشاء بوابات الدفع
- **Observer Pattern**: للإشعارات (مستقبلياً)

### مبادئ SOLID المطبقة
- **Single Responsibility**: كل كلاس له مسؤولية واحدة
- **Open/Closed**: مفتوح للتوسع، مغلق للتعديل
- **Interface Segregation**: فصل الواجهات
- **Dependency Inversion**: الاعتماد على التجريدات

## 🔒 الأمان

### الميزات الأمنية
- مصادقة آمنة باستخدام Laravel Sanctum
- تشفير كلمات المرور
- حماية CSRF
- تحقق من صحة البيانات
- HMAC verification لـ Paymob callbacks
- Webhook signature verification لـ Stripe
