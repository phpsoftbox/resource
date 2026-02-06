# ApiResponse и envelope

Ответы всегда возвращаются в едином формате:

```json
{
  "data": null,
  "meta": {},
  "errors": null
}
```

Ключи `data`, `meta`, `errors` присутствуют всегда.  
При успешном ответе `errors` равен `null`, а при ошибке `data` равен `null`.

## Успешный ответ

```php
use PhpSoftBox\Resource\ApiResponse;

$response = ApiResponse::success(
    data: ['id' => 10, 'email' => 'demo@example.com'],
    meta: ['trace_id' => 'abc-123'],
);

$payload = $response->toArray();
```

## Несколько ресурсов и данные вместе

```php
use PhpSoftBox\Resource\ApiResponse;
use PhpSoftBox\Resource\ResourceCollection;

$payload = [
    'users' => (new ResourceCollection($users))->collects(UserResource::class),
    'warehouse' => new WarehouseResource($warehouse),
    'boxStatuses' => $statuses,
    'filters' => $validated,
];

$response = ApiResponse::success($payload);
```

По умолчанию вложенные ресурсы оборачиваются в ключ `data`:

```json
{
  "data": {
    "users": { "data": [ ... ] },
    "warehouse": { "data": { ... } }
  },
  "meta": {},
  "errors": null
}
```

Если обёртка не нужна:

```php
$payload = [
    'user' => (new UserResource($user))->withoutWrapper(),
];
```

## Ответ с ошибками

```php
use PhpSoftBox\Resource\ApiResponse;

$response = ApiResponse::error(
    message: 'Данные не прошли валидацию.',
    fields: [
        'email' => ['Некорректный email.'],
    ],
    meta: ['trace_id' => 'abc-123'],
    code: 'validation',
);
```

## Объединение мета-данных

```php
use PhpSoftBox\Resource\ApiResponse;

$response = ApiResponse::success(['id' => 1])->mergeMeta(['request_id' => 'req-1']);
```
