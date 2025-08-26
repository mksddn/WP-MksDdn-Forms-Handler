# Интеграции

## Telegram интеграция

### Настройка Telegram бота

#### 1. Создание бота

1. Откройте Telegram и найдите @BotFather
2. Отправьте команду `/newbot`
3. Укажите название бота (например, "Site Forms Bot")
4. Укажите username бота (например, "site_forms_bot")
5. Сохраните полученный токен

#### 2. Получение ID чата

**Для личных сообщений:**
1. Отправьте сообщение боту
2. Откройте в браузере: `https://api.telegram.org/bot<TOKEN>/getUpdates`
3. Найдите `"chat":{"id":` в ответе
4. Скопируйте ID (обычно отрицательное число для групп)

**Для групп:**
1. Добавьте бота в группу
2. Отправьте сообщение в группу
3. Проверьте обновления через API
4. Скопируйте ID группы

#### 3. Настройка в форме

1. Откройте форму в админ-панели
2. В мета-боксе "Настройки формы" найдите "Telegram"
3. Включите "Отправлять в Telegram"
4. Введите токен бота
5. Введите ID чатов через запятую
6. Сохраните форму

### Примеры уведомлений

#### Базовое уведомление
```
📧 Новая заявка с сайта

Имя: Иван Иванов
Email: ivan@example.com
Телефон: +7 (999) 123-45-67
Сообщение: Хочу заказать товар

Дата: 2025-07-30 15:30:45
```

#### Расширенное уведомление
```
🛒 Новый заказ

Товар: iPhone 15 Pro
Количество: 2
Способ доставки: Курьер
Адрес: Москва, ул. Примерная, д. 1

Клиент: Петр Петров
Email: petr@example.com
Телефон: +7 (999) 987-65-43

Сумма: 199,999 ₽
Дата: 2025-07-30 16:45:12
```

### Настройка форматирования

Добавьте кастомные шаблоны в код темы:

```php
// В functions.php
add_filter('mksddn_telegram_message_template', function($template, $form_data, $form_title) {
    if ($form_title === 'Заказ товара') {
        return "🛒 Новый заказ\n\n" .
               "Товар: {product}\n" .
               "Количество: {quantity}\n" .
               "Способ доставки: {delivery}\n" .
               "Адрес: {address}\n\n" .
               "Клиент: {name}\n" .
               "Email: {email}\n" .
               "Телефон: {phone}\n\n" .
               "Дата: {date}";
    }
    return $template;
}, 10, 3);
```

## Google Sheets интеграция

### Настройка Google Sheets API

#### 1. Создание проекта в Google Cloud

1. Перейдите в [Google Cloud Console](https://console.cloud.google.com/)
2. Создайте новый проект
3. Включите Google Sheets API
4. Создайте сервисный аккаунт
5. Скачайте JSON файл с ключами

#### 2. Настройка таблицы

1. Создайте Google таблицу
2. Поделитесь таблицей с email сервисного аккаунта
3. Скопируйте ID таблицы из URL
4. Создайте лист для данных

#### 3. Настройка в форме

1. Откройте форму в админ-панели
2. Включите "Отправлять в Google Sheets"
3. Введите ID таблицы
4. Укажите название листа
5. Сохраните форму

### Структура данных

#### Автоматические колонки

- **Timestamp**: Дата и время отправки
- **Form Title**: Название формы
- **IP Address**: IP адрес отправителя

#### Пользовательские поля

Все поля формы автоматически добавляются как колонки.

### Пример структуры таблицы

| Timestamp | Form Title | Name | Email | Phone | Message | Product | Quantity |
|-----------|------------|------|-------|-------|---------|---------|----------|
| 2025-07-30 15:30:45 | Контактная форма | Иван Иванов | ivan@example.com | +7 (999) 123-45-67 | Хочу заказать товар | | |
| 2025-07-30 16:45:12 | Заказ товара | Петр Петров | petr@example.com | +7 (999) 987-65-43 | | iPhone 15 Pro | 2 |

### Настройка форматирования

Добавьте кастомные настройки:

```php
// В functions.php
add_filter('mksddn_sheets_data', function($data, $form_data, $form_title) {
    // Добавить дополнительные поля
    $data['custom_field'] = 'custom_value';
    
    // Форматировать дату
    $data['timestamp'] = date('Y-m-d H:i:s');
    
    return $data;
}, 10, 3);
```

## Admin Storage

### Настройка сохранения в админ-панели

#### 1. Включение сохранения

1. Откройте форму в админ-панели
2. Включите "Сохранять в админ-панели"
3. Сохраните форму

#### 2. Просмотр отправок

1. Перейдите в **Отправки форм**
2. Просматривайте все отправки
3. Используйте фильтры по форме и дате

#### 3. Экспорт данных

1. Перейдите в **Отправки форм** → **Export Submissions**
2. Выберите форму для экспорта
3. Укажите период (опционально)
4. Нажмите "Export CSV"

### Структура данных

#### Мета-поля отправки

- `_form_id`: ID формы
- `_form_title`: Название формы
- `_form_data`: Данные формы (JSON)
- `_delivery_results`: Результаты доставки
- `_submission_date`: Дата отправки
- `_submission_ip`: IP адрес

### API для разработчиков

#### Получение отправок

```php
// Получить все отправки формы
$submissions = get_posts([
    'post_type' => 'form_submissions',
    'meta_query' => [
        [
            'key' => '_form_id',
            'value' => $form_id
        ]
    ]
]);

// Получить данные отправки
$form_data = get_post_meta($submission_id, '_form_data', true);
$delivery_results = get_post_meta($submission_id, '_delivery_results', true);
```

#### Хуки для кастомизации

```php
// После сохранения отправки
add_action('mksddn_form_submission_saved', function($submission_id, $form_data, $form_id) {
    // Ваш код
}, 10, 3);

// После отправки email
add_action('mksddn_email_sent', function($recipients, $subject, $message, $form_id) {
    // Ваш код
}, 10, 4);

// После отправки в Telegram
add_action('mksddn_telegram_sent', function($bot_token, $chat_ids, $message, $form_id) {
    // Ваш код
}, 10, 4);

// После отправки в Google Sheets
add_action('mksddn_sheets_sent', function($spreadsheet_id, $sheet_name, $data, $form_id) {
    // Ваш код
}, 10, 4);
```

## REST API

### Endpoints

#### Отправка формы

```
POST /wp-json/mksddn-forms-handler/v1/forms/{slug}/submit
```

**Параметры:**
- `slug`: Слаг формы
- `form_data`: JSON с данными формы

**Пример запроса:**

```javascript
fetch('/wp-json/mksddn-forms-handler/v1/forms/contact-form/submit', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        name: 'Иван Иванов',
        email: 'ivan@example.com',
        phone: '+7 (999) 123-45-67',
        message: 'Хочу заказать товар'
    })
})
.then(response => response.json())
.then(data => console.log(data));
```

**Пример ответа:**

```json
{
    "success": true,
    "message": "Form submitted successfully",
    "delivery_results": {
        "email": {
            "success": true,
            "error": null
        },
        "telegram": {
            "success": true,
            "error": null,
            "enabled": true
        },
        "google_sheets": {
            "success": true,
            "error": null,
            "enabled": true
        },
        "admin_storage": {
            "success": true,
            "error": null,
            "enabled": true
        }
    }
}
```

#### Получение списка форм

```
GET /wp-json/mksddn-forms-handler/v1/forms
```

Параметры:
- `per_page` (1..100)
- `page` (>=1)
- `search` (строка, опционально)

Пример ответа:

```json
[
  {
    "id": 12,
    "slug": "contact-form",
    "title": "Contact Form",
    "submit_url": "/wp-json/mksddn-forms-handler/v1/forms/contact-form/submit"
  }
]
```

#### Получение одной формы по слагу

```
GET /wp-json/mksddn-forms-handler/v1/forms/{slug}
```

Пример ответа:

```json
{
  "id": 12,
  "slug": "contact-form",
  "title": "Contact Form",
  "submit_url": "/wp-json/mksddn-forms-handler/v1/forms/contact-form/submit"
}
```

### Обработка ошибок

#### Ошибки валидации

```json
{
    "success": false,
    "message": "Unauthorized fields detected: spam_field",
    "code": "unauthorized_fields",
    "status": 400,
    "unauthorized_fields": ["spam_field"],
    "allowed_fields": ["name", "email", "message"]
}
```

#### Ошибки отправки

```json
{
    "success": false,
    "message": "Failed to deliver form submission",
    "code": "send_error",
    "status": 500,
    "delivery_results": {
        "email": {
            "success": false,
            "error": "SMTP connection failed"
        }
    }
}
```

## Troubleshooting

### Telegram проблемы

**Бот не отвечает:**
1. Проверьте токен бота
2. Убедитесь, что бот добавлен в чат
3. Проверьте права бота в чате

**Сообщения не отправляются:**
1. Проверьте ID чата
2. Убедитесь, что бот активен
3. Проверьте логи ошибок

### Google Sheets проблемы

**Ошибка доступа:**
1. Проверьте права доступа сервисного аккаунта
2. Убедитесь, что таблица доступна для записи
3. Проверьте ID таблицы

**Данные не записываются:**
1. Проверьте название листа
2. Убедитесь, что лист существует
3. Проверьте формат данных

### Admin Storage проблемы

**Отправки не сохраняются:**
1. Проверьте права записи в БД
2. Убедитесь, что включено сохранение
3. Проверьте логи ошибок

**Экспорт не работает:**
1. Проверьте права доступа пользователя
2. Убедитесь, что выбрана форма
3. Проверьте настройки сервера

---

**Версия документации**: 1.0  
**Последнее обновление**: 2025-07-30 