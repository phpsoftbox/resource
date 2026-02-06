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

## only / except

`only()` и `except()` фильтруют поля уже сериализованного ресурса. Это удобно
для вложенных ресурсов, когда не нужно вручную повторять часть `toArray()`.

```php
'product' => $this->whenLoaded(
    'product',
    static fn (Product $product): array => (new ProductResource($product))
        ->only('id', 'name', 'vendor_code')
        ->toArray(),
    null,
),
```

Для коллекций фильтр применяется к каждому элементу:

```php
'roles' => $this->whenLoaded(
    'roles',
    static fn (EntityCollection $roles): array => RoleResource::collection($roles->all())
        ->only('id', 'name')
        ->toArray(),
    [],
),
```

`except()` применяется после `only()`, поэтому можно сначала задать широкий
набор полей, а затем убрать одно или несколько полей:

```php
(new ProductResource($product))
    ->only('id', 'name', 'vendor_code', 'barcodes')
    ->except('barcodes')
    ->toArray();
```

## through

`through()` применяет post-processing к уже сериализованному payload. Это
подходит для экранных данных, которые не являются базовой формой ресурса и
требуют внешнего контекста или batch-запроса.

```php
$payload = (new ShipmentResource($shipment))
    ->through(static function (array $payload): array {
        $payload['view_url'] = '/shipments/' . $payload['id'];

        return $payload;
    })
    ->toArray();
```

Transformers применяются по порядку. `through()` можно комбинировать с
`only()` и `except()`:

```php
$payload = (new UserResource($user))
    ->only('id', 'name')
    ->through(static function (array $payload): array {
        $payload['label'] = '#' . $payload['id'] . ' ' . $payload['name'];

        return $payload;
    })
    ->toArray();
```

Если transformer нужен как отдельный сервис, реализуйте
`ResourcePayloadTransformerInterface`:

```php
use PhpSoftBox\Resource\ResourceInterface;
use PhpSoftBox\Resource\ResourcePayloadTransformerInterface;

final readonly class ProductUrlTransformer implements ResourcePayloadTransformerInterface
{
    public function transform(array $payload, ResourceInterface $resource): array
    {
        $payload['url'] = '/products/' . $payload['id'];

        return $payload;
    }
}
```

`through()` не изменяет исходный ресурс. Он возвращает декоратор, который
сохраняет `wrapper()` и `meta()` внутреннего ресурса.

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
