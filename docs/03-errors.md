# Ошибки

Ошибки формируются в формате:

```json
{
  "message": "Данные не прошли валидацию.",
  "fields": {
    "email": ["Некорректный email."]
  },
  "code": "validation"
}
```

## ErrorBag

```php
use PhpSoftBox\Resource\ErrorBag;

$errors = new ErrorBag(
    message: 'Данные не прошли валидацию.',
    fields: [
        'email' => ['Некорректный email.'],
    ],
    code: 'validation',
);
```

## ApiResponse с ErrorBag

```php
use PhpSoftBox\Resource\ApiResponse;
use PhpSoftBox\Resource\ErrorBag;

$errors = new ErrorBag('Данные не прошли валидацию.', ['email' => ['Некорректный email.']]);
$response = ApiResponse::success()->withErrors($errors);
```
