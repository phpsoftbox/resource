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

По умолчанию одиночный вложенный ресурс не получает дополнительную обёртку:

```php
(new UserResource($user))->wrapper(); // null
```

Envelope можно включить явно для конкретного экземпляра:

```php
(new UserResource($user))->withWrapper('data');
```

Либо задать его в самом ресурсе:

```php
final class UserResource extends Resource
{
    protected ?string $wrapper = 'user';
}
```

## Сериализация через json_encode

`Resource::jsonSerialize()` делегирует в `ResourceSerializer` по умолчанию, поэтому `json_encode($resource)`
даёт тот же payload, что и финальная сериализация: скрытые поля (`when*` с ложным условием) удаляются, вложенные
ресурсы и `require*`-значения нормализуются. Раньше скрытое поле попадало в JSON как `{}`.

Зарегистрированные transformers и `RelationStateProviderInterface` при этом не применяются — для ответов
приложения передавайте ресурс в `ApiResponse`/Inertia с настроенным serializer или вызывайте
`$serializer->serialize($resource)` явно.

## Выпадающие списки

`Resource::dropdown()` собирает опции `{value, label, meta?}` для select. Источник — объект
`DropdownAwareInterface` или имя класса со статическим `dropdown()` (например, enum с trait `EnumOptions`);
имя класса оборачивается в `EnumDropdownSource`.

```php
Resource::dropdown(StatusEnum::class);          // [{value: 'all', label: 'Все'}, ...опции enum]
Resource::dropdown(StatusEnum::class, false);   // только опции enum
Resource::dropdown($source, emptyValue: null, emptyLabel: 'Не выбрано');
Resource::dropdown($source, ['value' => 0, 'label' => 'Любой', 'meta' => []]);
```

Второй аргумент `$prependEmpty`: `true` — добавить пустой пункт из `$emptyValue` / `$emptyLabel`
(по умолчанию `'all'` / `'Все'`), `false` — не добавлять, массив — добавить указанный пункт.
Класс без статического `dropdown()` приводит к `InvalidArgumentException`.

## ResourceCollection

`ResourceCollection` превращает массив/итератор в список ресурсов.
Вложенная коллекция по умолчанию использует envelope `data`, рядом с которым
serializer размещает непустые мета-данные коллекции.

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

`only()` и `except()` задают выборку полей ресурса. Обычный payload по-прежнему проходит через transformer pipeline,
после чего применяется финальный фильтр. Значения `require*` вычисляются отложенно: если поле исключено выборкой,
его контракт не проверяется и забытая eager loading не приводит к исключению для поля, которого нет в ответе.
Это удобно для вложенных ресурсов, когда не нужно вручную повторять часть `toArray()`.

```php
'product' => $this->whenLoaded(
    'product',
    static fn (Product $product): ResourceInterface => (new ProductResource($product))
        ->only('id', 'name', 'vendor_code'),
    null,
),
```

Для коллекций фильтр применяется к каждому элементу:

```php
'roles' => $this->whenLoaded(
    'roles',
    static fn (EntityCollection $roles): ResourceCollection => RoleResource::collection($roles->all())
        ->only('id', 'name'),
    [],
),
```

`except()` применяется после `only()`, поэтому можно сначала задать широкий
набор полей, а затем убрать одно или несколько полей:

```php
(new ProductResource($product))
    ->only('id', 'name', 'vendor_code', 'barcodes')
    ->except('barcodes');
```

Вложенные ресурсы нужно оставлять объектами до финальной границы сериализации.
Ранний вызов `toArray()` уничтожает информацию о типе ресурса и не позволяет
применить зарегистрированные трансформеры.

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
