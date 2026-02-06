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
