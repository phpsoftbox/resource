# Ресурсы

`Resource` отвечает за преобразование одного элемента (модели/DTO/массива) к нужной структуре.

```php
use PhpSoftBox\Resource\Resource;

final class UserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
        ];
    }
}
```

`Resource` поддерживает магический доступ к полям массива/объекта через `__get`.

Для автодополнения можно использовать `@mixin`:

```php
/**
 * @mixin UserEntity
 */
final class UserResource extends Resource
{
}
```

По умолчанию вложенные ресурсы оборачиваются в ключ `data`.  
Если обёртка не нужна, можно отключить её:

```php
(new UserResource($user))->withoutWrapper();
```

Также можно переопределить обёртку в самом ресурсе:

```php
final class UserResource extends Resource
{
    protected ?string $wrapper = 'user';
}
```

## ResourceCollection

`ResourceCollection` превращает массив/итератор в список ресурсов.

```php
use PhpSoftBox\Resource\ResourceCollection;

$items = [
    ['id' => 1, 'email' => 'a@example.com'],
    ['id' => 2, 'email' => 'b@example.com'],
];

$collection = (new ResourceCollection($items))->collects(UserResource::class);
```

Также можно использовать статический метод у ресурса:

```php
$collection = UserResource::collection($items);
```

`ResourceCollection` принимает только iterable. Для одного объекта используйте `new UserResource($user)`.

Можно указать свой mapper:

```php
use PhpSoftBox\Resource\ResourceCollection;

$collection = (new ResourceCollection($items))->map(
    static fn (array $item): array => ['id' => $item['id']]
);
```

## Мета-данные коллекции

```php
use PhpSoftBox\Resource\ResourceCollection;

$collection = (new ResourceCollection($items))
    ->collects(UserResource::class)
    ->withMeta(['total' => 2]);
```

## Пагинация

Если в `collection()` передан `PaginationResultInterface`, ресурс автоматически
сериализует элементы и сохранит `data/links/meta`:

```php
use PhpSoftBox\Pagination\Paginator as PaginationPaginator;
use PhpSoftBox\Resource\ApiResponse;

$pagination = (new PaginationPaginator(perPage: 20))
    ->path('/users')
    ->make(items: $users, total: $total, page: $page);

$response = ApiResponse::success([
    'users' => UserResource::collection($pagination),
]);
```
