# Условные атрибуты

В ресурсах можно управлять выводом полей через `when`, `whenLoaded`, `whenPivotLoaded`, `whenCounted`, `whenExists` и aggregate helpers.

## when

```php
final class UserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'secret' => $this->when(fn ($resource) => $resource->isAdmin(), 'secret'),
        ];
    }
}
```

Если условие ложно, ключ будет исключён из ответа.

## whenLoaded

Проверяет, что атрибут/отношение уже загружено в исходный ресурс.

```php
final class UserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'company' => $this->whenLoaded('company'),
        ];
    }
}
```

Состояние relation приходит через `RelationStateProviderInterface`, переданный в `ResourceSerializer`. Provider
различает `Loaded`, `Unloaded` и `Unknown`: публичное nullable-свойство ORM entity само по себе не считается
доказательством загрузки. Для массивов и объектов, за которые provider не отвечает, сохраняется проверка наличия
ключа/свойства.

Для `phpsoftbox/orm` готовая интеграция уже входит в Resource:

```php
use PhpSoftBox\Resource\Integration\OrmRelationStateProvider;
use PhpSoftBox\Resource\ResourceSerializer;

$serializer = new ResourceSerializer(
    relationStateProvider: new OrmRelationStateProvider($entityManagerRegistry->runtimeRegistry()),
);

$response = ApiResponse::success(
    data: new UserResource($user),
    serializer: $serializer,
);
```

В приложении serializer и provider следует зарегистрировать в контейнере один раз. Provider привязывается не к
конкретному `UnitOfWork`, а к общему runtime registry: состояние определяется по самому экземпляру entity. Поэтому
один serializer корректно обрабатывает dispatcher- и tenant-сущности, в том числе после переключения tenant-контекста,
и не открывает соединение с БД при создании. Все `EntityManager`, создаваемые одним ORM registry, автоматически
используют его общий runtime registry.

`ResourcePayloadNormalizer` компонента Inertia использует тот же serializer, поэтому отдельный Inertia-адаптер для
relation-state не нужен.

Не вызывайте `Resource::toArray()` вручную в action перед передачей payload в `ApiResponse` или Inertia: такой вызов
обходит serialization context, зарегистрированные transformers и наследуемые настройки вложенных ресурсов. Передавайте
сам `Resource`, а промежуточные преобразования оформляйте как resource transformer/decorator.

## Обязательные require-хелперы

`when*` подходит для опциональных полей: незагруженное значение исключается из ответа. Если поле является
обязательной частью контракта ответа, используйте симметричный `require*` helper:

```php
final class UserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'company'       => $this->requireLoaded('company'),
            'postsCount'    => $this->requireCounted('posts'),
            'postsExists'   => $this->requireExists('posts'),
            'postsLikesSum' => $this->requireSum('posts', 'likes'),
        ];
    }
}
```

Если обязательное значение не загружено, выбрасывается `RequiredResourceValueMissingException`. Загруженный
`null` остаётся допустимым значением для nullable relation.

Проверка `require*` выполняется только для полей, оставшихся после `only()`/`except()`. Например,
`->only('id', 'name')` не потребует eager loading relations из остальных полей `toArray()`; при добавлении такого поля
в `only()` контракт снова станет обязательным. Это работает и для alias, когда ключ payload не совпадает с именем
relation.

Доступны:

- `requireLoaded()`;
- `requirePivotLoaded()` / `requirePivotLoadedAs()`;
- `requireCounted()`;
- `requireExists()`;
- `requireAggregated()`;
- `requireSum()` / `requireAvg()` / `requireMin()` / `requireMax()`.

Как и у `when*`, вторым аргументом можно передать callback преобразования. У `require*` намеренно нет default:
отсутствующее значение является ошибкой контракта.

## whenCounted

Проверяет наличие счётчика `<relation>_count` (snake_case).

```php
final class UserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'postsCount' => $this->whenCounted('posts'),
        ];
    }
}
```

Если счётчик не загружен, ключ будет исключён из ответа.

## whenPivotLoaded / whenPivotLoadedAs

Проверяет, что на исходном ресурсе загружен pivot-объект.

```php
final class RoleResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'assignedDatetime' => $this->whenPivotLoaded(
                static fn (object $pivot): string => $pivot->createdDatetime,
            ),
        ];
    }
}
```

По умолчанию используется accessor `pivot()`. Если связь кладёт pivot в другой accessor, можно указать его явно:

```php
'assignedDatetime' => $this->whenPivotLoadedAs(
    'membership',
    static fn (object $pivot): string => $pivot->createdDatetime,
)
```

Если pivot не загружен или accessor отсутствует, ключ будет исключён из ответа.

## whenExists

Проверяет наличие флага `<relation>_exists` (snake_case).

```php
final class UserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'postsExists' => $this->whenExists('posts'),
        ];
    }
}
```

## whenAggregated / whenSum / whenAvg / whenMin / whenMax

Проверяет наличие агрегата `<relation>_<column>_<aggregate>` (snake_case).

```php
final class UserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'postsLikesSum' => $this->whenSum('posts', 'likes'),
            'postsLikesAvg' => $this->whenAvg('posts', 'likes'),
            'postsLikesMin' => $this->whenMin('posts', 'likes'),
            'postsLikesMax' => $this->whenMax('posts', 'likes'),
        ];
    }
}
```

Для произвольного aggregate:

```php
'postsLikesTotal' => $this->whenAggregated('posts', 'likes', 'sum')
```

Если агрегат не загружен, ключ будет исключён из ответа.
