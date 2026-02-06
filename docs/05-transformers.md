# Registry трансформеров и serializer

`ResourcePayloadTransformerRegistry` позволяет один раз зарегистрировать
трансформер для класса или интерфейса ресурса:

```php
use PhpSoftBox\Resource\ResourcePayloadTransformerRegistry;
use PhpSoftBox\Resource\ResourceSerializer;

$registry = new ResourcePayloadTransformerRegistry();
$registry->register(
    ProductResource::class,
    $container->get(ProductUrlResourceTransformer::class),
);

$serializer = new ResourceSerializer($registry);
```

`ResourceSerializer` рекурсивно обходит ресурсы в массивах и коллекциях. Поэтому
вложенный ресурс должен возвращаться объектом:

```php
public function toArray(): array
{
    return [
        'id'      => $this->id,
        'product' => new ProductResource($this->product),
    ];
}
```

Трансформеры можно регистрировать для точного класса, родительского класса или
интерфейса. Сначала выполняются трансформеры с большим `priority`, а при равном
приоритете сохраняется порядок регистрации.

## Исключения для вложенных ресурсов

Родительский ресурс может исключить выбранные registry-трансформеры для заданного
типа ресурса внутри своего дерева:

```php
$resource = (new ShipmentResource($shipment))
    ->withoutRegisteredTransformersFor(
        ShipmentProductResource::class,
        [ProductUrlResourceTransformer::class],
    );
```

Пустой список отключает все registry-трансформеры заданного типа:

```php
$resource->withoutRegisteredTransformersFor(ShipmentProductResource::class);
```

Правила действуют только в дереве данного ресурса, наследуются на любой глубине
и не затрагивают явные трансформеры `through()`. Остальные registry-трансформеры
сохраняют свой приоритет и порядок.

## Порядок pipeline

Для каждого ресурса serializer выполняет операции в следующем порядке:

1. Получает сырой payload через `toArray()`.
2. Сериализует вложенные ресурсы.
3. Находит registry-трансформеры и удаляет исключённые.
4. Выполняет оставшиеся registry-трансформеры.
5. Выполняет `only()`, `except()` и `through()` в порядке fluent-цепочки.
6. Применяет wrapper в контексте родительского payload.

Прямой `toArray()` не обращается к registry. Автоматические трансформеры
гарантируются на границе `ResourceSerializerInterface`, в `ApiResponse` с
внедрённым serializer или через normalizer Inertia.
