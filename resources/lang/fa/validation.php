<?php

return [

    /*
    |--------------------------------------------------------------------------
    | خطوط زبان برای اعتبارسنجی
    |--------------------------------------------------------------------------
    |
    | خطوط زبان زیر حاوی پیام‌های خطای پیش‌فرضی هستند که توسط کلاس اعتبارسنجی
    | استفاده می‌شوند. برخی از این قوانین دارای چندین نسخه هستند (مانند قوانین مربوط به اندازه).
    | می‌توانید هر یک از این پیام‌ها را در اینجا شخصی‌سازی کنید.
    |
    */

    'accepted' => ':attribute باید پذیرفته شود.',
    'active_url' => ':attribute یک آدرس URL معتبر نیست.',
    'after' => ':attribute باید تاریخی پس از :date باشد.',
    'after_or_equal' => ':attribute باید تاریخی پس از یا برابر با :date باشد.',
    'alpha' => ':attribute فقط می‌تواند شامل حروف باشد.',
    'alpha_dash' => ':attribute فقط می‌تواند شامل حروف، اعداد، خط تیره (-) و زیرخط (_) باشد.',
    'alpha_num' => ':attribute فقط می‌تواند شامل حروف و اعداد باشد.',
    'array' => ':attribute باید یک آرایه باشد.',
    'before' => ':attribute باید تاریخی پیش از :date باشد.',
    'before_or_equal' => ':attribute باید تاریخی پیش از یا برابر با :date باشد.',
    'between' => [
        'numeric' => ':attribute باید بین :min و :max باشد.',
        'file' => ':attribute باید بین :min و :max کیلوبایت باشد.',
        'string' => ':attribute باید بین :min و :max کاراکتر باشد.',
        'array' => ':attribute باید بین :min و :max آیتم داشته باشد.',
    ],
    'boolean' => 'فیلد :attribute باید صحیح یا غلط باشد.',
    'confirmed' => 'تاییدیه :attribute مطابقت ندارد.',
    'date' => ':attribute یک تاریخ معتبر نیست.',
    'date_equals' => ':attribute باید تاریخی برابر با :date باشد.',
    'date_format' => ':attribute با فرمت :format مطابقت ندارد.',
    'different' => ':attribute و :other باید متفاوت باشند.',
    'digits' => ':attribute باید شامل :digits رقم باشد.',
    'digits_between' => ':attribute باید بین :min و :max رقم باشد.',
    'dimensions' => ':attribute دارای ابعاد تصویر نامعتبر است.',
    'distinct' => 'فیلد :attribute دارای مقدار تکراری است.',
    'email' => ':attribute باید یک آدرس ایمیل معتبر باشد.',
    'ends_with' => ':attribute باید با یکی از مقادیر زیر پایان یابد: :values.',
    'exists' => ':attribute انتخاب‌شده نامعتبر است.',
    'file' => ':attribute باید یک فایل باشد.',
    'filled' => 'فیلد :attribute باید دارای مقدار باشد.',
    'gt' => [
        'numeric' => ':attribute باید بزرگتر از :value باشد.',
        'file' => ':attribute باید بزرگتر از :value کیلوبایت باشد.',
        'string' => ':attribute باید بیشتر از :value کاراکتر باشد.',
        'array' => ':attribute باید بیش از :value آیتم داشته باشد.',
    ],
    'gte' => [
        'numeric' => ':attribute باید بزرگتر یا مساوی :value باشد.',
        'file' => ':attribute باید بزرگتر یا مساوی :value کیلوبایت باشد.',
        'string' => ':attribute باید حداقل :value کاراکتر داشته باشد.',
        'array' => ':attribute باید حداقل :value آیتم داشته باشد.',
    ],
    'image' => ':attribute باید یک تصویر باشد.',
    'in' => ':attribute انتخاب‌شده نامعتبر است.',
    'in_array' => 'فیلد :attribute در :other وجود ندارد.',
    'integer' => ':attribute باید یک عدد صحیح باشد.',
    'ip' => ':attribute باید یک آدرس IP معتبر باشد.',
    'ipv4' => ':attribute باید یک آدرس IPv4 معتبر باشد.',
    'ipv6' => ':attribute باید یک آدرس IPv6 معتبر باشد.',
    'json' => ':attribute باید یک رشته JSON معتبر باشد.',
    'lt' => [
        'numeric' => ':attribute باید کوچکتر از :value باشد.',
        'file' => ':attribute باید کوچکتر از :value کیلوبایت باشد.',
        'string' => ':attribute باید کمتر از :value کاراکتر باشد.',
        'array' => ':attribute باید کمتر از :value آیتم داشته باشد.',
    ],
    'lte' => [
        'numeric' => ':attribute باید کوچکتر یا مساوی :value باشد.',
        'file' => ':attribute باید کوچکتر یا مساوی :value کیلوبایت باشد.',
        'string' => ':attribute باید حداکثر :value کاراکتر داشته باشد.',
        'array' => ':attribute نباید بیش از :value آیتم داشته باشد.',
    ],
    'max' => [
        'numeric' => ':attribute نباید بزرگتر از :max باشد.',
        'file' => ':attribute نباید بزرگتر از :max کیلوبایت باشد.',
        'string' => ':attribute نباید بیشتر از :max کاراکتر باشد.',
        'array' => ':attribute نباید بیش از :max آیتم داشته باشد.',
    ],
    'mimes' => ':attribute باید فایلی از نوع: :values باشد.',
    'mimetypes' => ':attribute باید فایلی از نوع: :values باشد.',
    'min' => [
        'numeric' => ':attribute باید حداقل :min باشد.',
        'file' => ':attribute باید حداقل :min کیلوبایت باشد.',
        'string' => ':attribute باید حداقل :min کاراکتر باشد.',
        'array' => ':attribute باید حداقل :min آیتم داشته باشد.',
    ],
    'not_in' => ':attribute انتخاب‌شده نامعتبر است.',
    'not_regex' => 'فرمت :attribute نامعتبر است.',
    'numeric' => ':attribute باید یک عدد باشد.',
    'password' => 'رمز عبور اشتباه است.',
    'present' => 'فیلد :attribute باید وجود داشته باشد.',
    'regex' => 'فرمت :attribute نامعتبر است.',
    'required' => 'فیلد :attribute الزامی است.',
    'required_if' => 'فیلد :attribute هنگامی که :other برابر با :value است، الزامی است.',
    'required_unless' => 'فیلد :attribute الزامی است، مگر اینکه :other در :values موجود باشد.',
    'required_with' => 'فیلد :attribute هنگامی که :values وجود دارد، الزامی است.',
    'required_with_all' => 'فیلد :attribute هنگامی که :values وجود دارند، الزامی است.',
    'required_without' => 'فیلد :attribute هنگامی که :values وجود ندارد، الزامی است.',
    'required_without_all' => 'فیلد :attribute هنگامی که هیچ‌کدام از :values وجود ندارند، الزامی است.',
    'same' => ':attribute و :other باید یکسان باشند.',
    'size' => [
        'numeric' => ':attribute باید برابر با :size باشد.',
        'file' => ':attribute باید برابر با :size کیلوبایت باشد.',
        'string' => ':attribute باید برابر با :size کاراکتر باشد.',
        'array' => ':attribute باید شامل :size آیتم باشد.',
    ],
    'starts_with' => ':attribute باید با یکی از مقادیر زیر شروع شود: :values.',
    'string' => ':attribute باید یک رشته باشد.',
    'timezone' => ':attribute باید یک منطقه زمانی معتبر باشد.',
    'unique' => ':attribute قبلاً انتخاب شده است.',
    'uploaded' => 'بارگذاری :attribute با شکست مواجه شد.',
    'url' => 'فرمت :attribute نامعتبر است.',
    'uuid' => ':attribute باید یک UUID معتبر باشد.',

    /*
    |--------------------------------------------------------------------------
    | خطوط زبان برای اعتبارسنجی سفارشی
    |--------------------------------------------------------------------------
    |
    | در اینجا می‌توانید پیام‌های اعتبارسنجی سفارشی برای ویژگی‌ها (attributes) را
    | با استفاده از قرارداد "attribute.rule" تعیین کنید.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'پیام-سفارشی',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ویژگی‌های اعتبارسنجی سفارشی
    |--------------------------------------------------------------------------
    |
    | خطوط زبان زیر برای جایگزینی نام‌های مکان‌نگهدار (placeholder) با نام‌های
    | خواناتر مانند "آدرس ایمیل" به جای "email" استفاده می‌شوند.
    |
    */

    'attributes' => [],

];
