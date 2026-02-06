# PhpSoftBox Resource

Компонент для сериализации данных API через ресурсы и единый envelope.

## Установка

```bash
composer require phpsoftbox/resource
```

## Быстрый старт

```php
<?php

use PhpSoftBox\Resource\ApiResponse;
use PhpSoftBox\Resource\Resource;

final class UserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource['id'],
            'email' => $this->resource['email'],
        ];
    }
}

$user = ['id' => 10, 'email' => 'demo@example.com'];

$response = ApiResponse::success(new UserResource($user));

return $response->toArray();
```

## Документация

- [docs/01-usage.md](docs/01-usage.md) — envelope и ApiResponse
- [docs/02-resources.md](docs/02-resources.md) — Resource и ResourceCollection
- [docs/03-errors.md](docs/03-errors.md) — формат ошибок и ErrorBag
- [docs/04-conditional.md](docs/04-conditional.md) — условные атрибуты
