# GO_LIVE_CHECKLIST — доступ и безопасность

Используйте как обязательную приёмку перед релизом и после изменений в auth/tenancy. Детали ролей: [ACCESS_MATRIX.md](ACCESS_MATRIX.md), установка: [SETUP_ADMIN.md](SETUP_ADMIN.md).

**Версия / дата прохождения:** _заполнить после прогона_

---

## Минимальные access tests (из ACCESS_MATRIX)

- [ ] **Tenant-only user** не открывает Platform Console (даже с прямым URL `https://{PLATFORM_HOST}/platform` и `/platform/login` после логина — отказ или 403).
- [ ] **Platform user без `tenant_user`** не открывает Tenant Admin на домене клиента (`/admin` — отказ после логина / 403).
- [ ] **Пользователь tenant A** не видит данные tenant B (прямые URL к `/admin/.../records`, экспорт, виджеты).
- [ ] **`status = blocked` у User** — не входит **ни** в Platform, **ни** в Tenant Admin (обе панели).
- [ ] На **platform host** запрос к `/platform` **без** platform-роли после попытки логина → 403 (или отказ Filament).
- [ ] **Неизвестный домен** (не `platform_host`, не в `tenant_domains`) → ответ 404 с view «Домен не подключён» (`errors.domain-not-connected`).

_Как проверить:_ завести тестовых пользователей под каждый сценарий; чистый браузер / инкогнито; смотреть HTTP-код и отсутствие данных в UI.

---

## Host spoofing (security)

- [ ] Запрос к **`/admin`** (и при необходимости публичные tenant-маршруты) с **подменённым заголовком `Host`** (например имя чужого tenant-домена), при том что реальный виртуальный хост приложения остаётся вашим (или через инструмент вроде curl с `-H "Host: ..."`).
- [ ] **Ожидание:** по одному только недоверенному `Host` **не** должен резолвиться чужой tenant, если среда настроена корректно (`TrustProxies`, реальный клиентский host за прокси).
- [ ] Зафиксировать **фактическое** поведение в вашей среде (OSPanel, Nginx, Cloudflare). При расхождении с ожиданием — настроить доверенные прокси и/или ограничение использования `X-Forwarded-Host`.

---

## Global Search и экспорты

- [ ] Global Search **выключен** в обеих панелях (регрессия не включила поиск).
- [ ] Экспорт (например лиды) не отдаёт строки другого tenant при смене прямых URL (ручная проверка + политики).

---

## Редирект после логина

- [ ] Вход на Platform → дашборд `/platform`.
- [ ] Вход на Tenant Admin, только tenant → `/admin`.
- [ ] Пользователь с **platform-ролью** и membership, вошедший через **`/admin/login`** → редирект на Platform Console (см. [SETUP_ADMIN.md](SETUP_ADMIN.md)).

---

## Автотесты

- [ ] `php artisan test --filter=AccessControl` (или полный suite) проходит в CI.

---

## UI контекста

- [ ] В Tenant Admin в шапке видно **имя текущего tenant** (снижение риска «редактировал не того клиента»).
- [ ] В Platform Console видна подпись **Platform Console**.

---

## Ручной прогон на локалке (закрытие Access & Readiness)

Перед переходом к **Phase 5** пройти по пунктам (инкогнито / разные браузерные профили по желанию):

| Сценарий | Что проверить |
|----------|----------------|
| **Platform host** | `https://{PLATFORM_HOST}/platform` — вход platform-пользователем, индикатор «Platform Console», редирект после логина на `/platform`. |
| **Tenant host** | `https://{tenant_domain}/admin` — вход с `tenant_user`, индикатор **Tenant: …**, редирект на `/admin` (без platform-роли). |
| **Blocked user** | `User.status = blocked` — не пускает ни в одну панель. |
| **Оба контекста** | Пользователь с platform-ролью + `tenant_user`: с `/admin/login` после входа — редирект на Platform Console (см. [SETUP_ADMIN.md](SETUP_ADMIN.md)). |
| **Unknown host** | Домен не из `tenant_domains` и не platform — страница «Домен не подключён» или ожидаемое 404. |

После отметки чекбоксов выше — проставить **дату** в шапке документа. Спринт **Access & Readiness** считается завершённым; далее — [DELIVERY_ROADMAP.md](DELIVERY_ROADMAP.md) Phase 5.
