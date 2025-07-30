# Создание и настройка форм

## Создание новой формы

### 1. Основные настройки

1. Перейдите в **Формы** → **Добавить новую**
2. Заполните обязательные поля:
   - **Название формы**: Уникальное название для идентификации
   - **Получатели**: Email адреса через запятую
   - **Тема письма**: Тема email сообщения

### 2. Настройка полей формы

В мета-боксе **"Настройки формы"** настройте поля:

#### Типы полей

**Text (Текст)**
- Простое текстовое поле
- Подходит для имени, телефона, адреса

**Email (Email)**
- Поле для email адреса
- Автоматическая валидация формата

**Textarea (Многострочный текст)**
- Большое текстовое поле
- Подходит для сообщений, комментариев

**Select (Выпадающий список)**
- Список вариантов для выбора
- Укажите варианты через запятую

**Checkbox (Флажок)**
- Да/Нет выбор
- Подходит для согласий, подписок

**Radio (Переключатель)**
- Выбор одного варианта из нескольких
- Укажите варианты через запятую

#### Настройки полей

Для каждого поля доступны настройки:

- **Label**: Название поля (обязательно)
- **Required**: Обязательное поле
- **Validation**: Тип валидации
- **Placeholder**: Подсказка в поле
- **Default Value**: Значение по умолчанию

### 3. Дополнительные настройки

#### Email настройки
- **BCC получатель**: Скрытая копия письма
- **Тема письма**: Динамическая тема с подстановкой полей

#### Интеграции
- **Telegram**: Отправка уведомлений в Telegram
- **Google Sheets**: Сохранение данных в таблицы
- **Admin Storage**: Сохранение в админ-панели

## Примеры форм

### Контактная форма

```json
{
  "name": {
    "type": "text",
    "label": "Имя",
    "required": true,
    "placeholder": "Ваше имя"
  },
  "email": {
    "type": "email",
    "label": "Email",
    "required": true,
    "placeholder": "your@email.com"
  },
  "phone": {
    "type": "text",
    "label": "Телефон",
    "required": false,
    "placeholder": "+7 (999) 123-45-67"
  },
  "message": {
    "type": "textarea",
    "label": "Сообщение",
    "required": true,
    "placeholder": "Ваше сообщение"
  }
}
```

### Форма заказа

```json
{
  "product": {
    "type": "select",
    "label": "Товар",
    "required": true,
    "options": "Товар 1, Товар 2, Товар 3"
  },
  "quantity": {
    "type": "text",
    "label": "Количество",
    "required": true,
    "validation": "number"
  },
  "delivery": {
    "type": "radio",
    "label": "Способ доставки",
    "required": true,
    "options": "Курьер, Самовывоз, Почта"
  },
  "address": {
    "type": "textarea",
    "label": "Адрес доставки",
    "required": false
  }
}
```

### Форма обратной связи

```json
{
  "name": {
    "type": "text",
    "label": "Имя",
    "required": true
  },
  "email": {
    "type": "email",
    "label": "Email",
    "required": true
  },
  "subject": {
    "type": "select",
    "label": "Тема",
    "required": true,
    "options": "Общий вопрос, Техническая поддержка, Жалоба, Предложение"
  },
  "message": {
    "type": "textarea",
    "label": "Сообщение",
    "required": true,
    "placeholder": "Опишите ваш вопрос или проблему"
  },
  "agreement": {
    "type": "checkbox",
    "label": "Согласен с обработкой персональных данных",
    "required": true
  }
}
```

## Размещение форм на сайте

### Шорткоды

Основной способ размещения - шорткоды:

```php
[form id="contact-form"]
[form id="order-form"]
[form id="feedback-form"]
```

### В коде темы

```php
<?php echo do_shortcode('[form id="contact-form"]'); ?>
```

### В виджетах

Добавьте шорткод в любой текстовый виджет.

## Настройка интеграций

### Telegram интеграция

1. Создайте бота через @BotFather
2. Получите токен бота
3. Добавьте токен в настройки формы
4. Укажите ID чатов для уведомлений

### Google Sheets интеграция

1. Создайте Google таблицу
2. Настройте доступ для сервисного аккаунта
3. Добавьте ID таблицы в настройки формы
4. Укажите название листа

### Admin Storage

1. Включите сохранение в админ-панели
2. Отправки будут доступны в **Отправки форм**
3. Возможен экспорт в CSV

## Валидация данных

### Встроенная валидация

- **Email**: Проверка формата email
- **Required**: Проверка заполнения обязательных полей
- **Number**: Проверка числового значения
- **Phone**: Проверка формата телефона

### Кастомная валидация

Добавьте JavaScript для дополнительной валидации:

```javascript
jQuery(document).ready(function($) {
    $('form[data-form-id="contact-form"]').on('submit', function(e) {
        var phone = $('input[name="phone"]').val();
        if (phone && !/^\+?[\d\s\-\(\)]+$/.test(phone)) {
            alert('Неверный формат телефона');
            e.preventDefault();
        }
    });
});
```

## Стилизация форм

### CSS классы

Формы используют следующие CSS классы:

```css
.mksddn-form {
    /* Контейнер формы */
}

.mksddn-form-field {
    /* Поле формы */
}

.mksddn-form-field input,
.mksddn-form-field textarea,
.mksddn-form-field select {
    /* Элементы ввода */
}

.mksddn-form-submit {
    /* Кнопка отправки */
}

.mksddn-form-error {
    /* Сообщения об ошибках */
}

.mksddn-form-success {
    /* Сообщения об успехе */
}
```

### Кастомные стили

Добавьте CSS в файл темы:

```css
.mksddn-form {
    max-width: 600px;
    margin: 0 auto;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
}

.mksddn-form-field {
    margin-bottom: 15px;
}

.mksddn-form-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.mksddn-form-field input,
.mksddn-form-field textarea,
.mksddn-form-field select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.mksddn-form-submit {
    background: #0073aa;
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.mksddn-form-submit:hover {
    background: #005a87;
}
```

## Troubleshooting

### Форма не отправляется

1. Проверьте настройки SMTP
2. Убедитесь в правильности email получателей
3. Проверьте консоль браузера на ошибки JavaScript
4. Проверьте логи сервера

### Поля не отображаются

1. Проверьте настройки полей в мета-боксе
2. Убедитесь, что форма опубликована
3. Проверьте права доступа пользователя

### Стили не применяются

1. Проверьте CSS файл темы
2. Убедитесь в правильности селекторов
3. Очистите кэш браузера

---

**Версия документации**: 1.0  
**Последнее обновление**: 2025-07-30 