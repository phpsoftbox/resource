# Условные атрибуты

В ресурсах можно управлять выводом полей через `when`, `whenLoaded`, `whenCounted`.

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
