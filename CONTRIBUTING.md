# Участие в разработке lphenom/auth

Спасибо за интерес к проекту! 🎉

## Требования

- PHP >= 8.1
- Docker + Docker Compose (для запуска тестов и окружения)
- Composer

## Настройка окружения

```bash
git clone git@github.com:lphenom/auth.git
cd auth
make install
make test
```

## Стиль кода

PSR-12. Автоисправление:

```bash
make fix
```

Проверка:

```bash
make lint
```

## Статический анализ

```bash
make stan   # PHPStan level 8
```

## Совместимость с KPHP

Весь код **обязан** оставаться KPHP-совместимым. Правила:

- Нет constructor property promotion (`__construct(private $x)`)
- Нет `readonly` свойств
- Нет `Reflection`, `eval()`, `$$var`, `new $className()`
- Нет `str_starts_with`, `str_ends_with`, `str_contains` — используйте `substr`/`strpos`
- Нет `match` — используйте `if/elseif/else`
- `try/catch` всегда с явным `catch`
- Нет `callable` в типизированных массивах
- Нет trailing comma в аргументах вызовов функций
- Нет `__destruct()`
- Нет union типов `int|string|bool|float|null`

## Сообщения коммитов

Следуйте [Conventional Commits](https://www.conventionalcommits.org/):

```
feat(auth): добавить поддержку revoke
fix(auth): исправить проверку токена
test(auth): добавить тест middleware
```

## Чеклист Pull Request

- [ ] Тесты проходят: `make test`
- [ ] Нет ошибок линтера: `make lint`
- [ ] PHPStan проходит: `make stan`
- [ ] KPHP-совместимо (нет запрещённых конструкций)
- [ ] Документация обновлена при изменении публичного API

## Лицензия

Участвуя в проекте, вы соглашаетесь, что ваши изменения будут лицензированы под [MIT License](LICENSE).

