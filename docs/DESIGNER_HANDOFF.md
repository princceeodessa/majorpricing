# МАЖОР — гайд для дизайнера

Документ для человека который умеет CSS, но никогда не работал с Laravel-проектами. Цель — поставить проект локально, открыть в браузере, найти нужный файл, поправить стиль, сохранить, увидеть результат.

PHP, контроллеры, миграции и т.п. — **не трогать**. Только то, что в списке ниже.

---

## 0. Что это за проект

B2B-каталог МАЖОР: вход по логину, личный кабинет, каталог товаров, корзина, оформление заказа. Один сайт, одна codebase, бренд «МАЖОР» (красный `#d60000`). Серверная часть — Laravel 13 (PHP), но всё что про внешний вид живёт в двух местах:

- `resources/css/app.css` — единственный CSS-файл (~18 000 строк, разбит на секции).
- `resources/views/**/*.blade.php` — HTML-шаблоны Blade. Внутри обычный HTML + Tailwind-классы + кое-где `{{ переменная }}`.

Tailwind 4 подключён через Vite. Конфиг Tailwind лежит **внутри** `app.css` в блоке `@theme { ... }` — отдельного `tailwind.config.js` нет.

---

## 1. Что поставить один раз на свою машину

Всё под Windows. Все ссылки официальные.

| ПО | Зачем | Откуда |
|---|---|---|
| **Laragon Full** | поднимает PHP + MySQL + Apache в один клик | https://laragon.org/download/ |
| **Node.js 20+ LTS** | компилирует CSS (Vite/Tailwind) | https://nodejs.org/en/download |
| **Git for Windows** | получение проекта | https://git-scm.com/download/win |
| **VS Code** | редактор (можно любой, но VS Code удобнее всего) | https://code.visualstudio.com/ |

Полезные плагины в VS Code (необязательно, но удобно):
- `Tailwind CSS IntelliSense` — автокомплит классов Tailwind
- `Laravel Blade formatter` или `Blade Formatter` — подсветка `.blade.php`

---

## 2. Поставить проект (один раз)

1. Открой Laragon → меню **Menu → Quick app → ничего не выбираем**, нужна только запущенная среда. Нажми **Start All** в главном окне. Все три индикатора (Apache, MySQL, …) должны загореться зелёным.

2. Открой проводник в `C:\laragon\www\`. Сюда положим проект.

3. Открой Git Bash в этой папке (правый клик → **Git Bash Here**) и выполни:

   ```bash
   git clone <ссылка-которую-даст-владелец> major
   cd major
   ```

   (Если владелец передаёт zip-архивом — распакуй его так, чтобы получилась папка `C:\laragon\www\major\` с файлами `composer.json`, `package.json` внутри.)

4. Установи зависимости. В Git Bash в папке `C:\laragon\www\major`:

   ```bash
   composer install
   npm install
   ```

   `composer install` тянет PHP-зависимости (несколько минут), `npm install` — Vite/Tailwind (минута).

5. Скопируй настройки окружения:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

6. Создай пустую БД и накати миграции:

   - Laragon → **Menu → MySQL → HeidiSQL** (или любой другой клиент)
   - Создай базу с именем `major` (кодировка `utf8mb4`)
   - Вернись в Git Bash:

     ```bash
     php artisan migrate --seed
     ```

   Команда поднимет схему и набьёт минимальные тестовые данные.

7. Создай ссылку на сайт. Laragon делает это автоматически: в `C:\laragon\www\major\public` лежит `index.php` → Laragon выпустит `http://major.test`. Проверь: в Laragon **Menu → Apache → sites-enabled** должен быть файл `auto.major.test.conf`. Если нет — **Menu → Quick app → Reload**, и Laragon перечитает.

---

## 3. Запуск каждый день

Два процесса должны крутиться параллельно:

**Терминал 1 — Laragon** (один раз нажал Start All, дальше живёт сам).

**Терминал 2 — Vite** (компилирует CSS «на лету» пока ты редактируешь):

```bash
cd C:\laragon\www\major
npm run dev
```

Должно вывести строку типа `VITE v8.x ready in 350ms` и `Local: http://localhost:5173/`. **Этот терминал не закрывать**, пока работаешь. Менять CSS/Blade — браузер сам обновится через секунду.

Заходи в браузер: **http://major.test**.

Тестовый логин для входа (если миграции с сидером прошли) — спроси у владельца.

---

## 4. Где править внешний вид

### 4.1. Главный CSS-файл

**`resources/css/app.css`** — единственный CSS. Все правки делаешь здесь.

Структура файла (ориентируйся по `Ctrl+F` по этим маркерам):

| Что | Где искать (поиск по тексту) | Зачем |
|---|---|---|
| Брендовые токены (цвета, тени, шрифты) | `@theme {` (самый верх файла, ~строка 10) | `--brand`, `--brand-strong`, `--accent`, `--shadow-md`, `--font-sans` и т.п. — менять цвет бренда в одном месте |
| Адаптив каталога | `Каталог: единый закон сетки` | количество колонок на разных ширинах |
| Карточка товара | `PHASE 5: product page premium touches` | детальная страница товара |
| Корзина | `Корзина: позиция в одну колонку` | страница `/cart` |
| Личный кабинет | `ЛК / Account` | страница `/account` |
| Bottom-nav (мобильная нижняя панель) | `PHASE C — FLOATING BOTTOM-NAV` | плавающее нижнее меню на мобиле |
| Хлебные крошки | `PHASE 5: breadcrumbs` | навигация наверху страниц |
| Футер | `PHASE 4: brand footer` | подвал сайта |
| Шапка | `PHASE 4: sticky header polish` | верхняя плашка с лого/поиском |
| Sticky-CTA на мобиле | `PHASE 3: sticky cart bar` | плашка «Оформить» внизу при товарах в корзине |
| 404 / 500 | `PHASE 5: branded error screens` | страницы ошибок |
| Скелетоны загрузки | `PHASE 2: skeletons for catalog loading` | прелоадеры |
| Empty-states (пустая корзина и т.п.) | `PHASE 2: empty states` | картинка + текст когда нет данных |

В файле есть **«мусорные»** секции с заголовками типа `NUCLEAR OVERRIDE`, `KILL all rose-tinted borders` — это исторические правки прошлых релизов, **трогать не нужно** (но и удалять не нужно, без них поедет старый дизайн).

### 4.2. Брендовые токены (главное)

В самом верху `app.css` блок:

```css
@theme {
    --brand: #d60000;            /* основной красный МАЖОР */
    --brand-strong: #b40d12;     /* hover, глубина */
    --brand-deep: #4a0509;       /* самый тёмный, для акцентов в заголовках */
    --brand-soft: #ffe5e5;       /* нежный розовый фон для бейджей */
    --accent: #f97316;           /* тёплый оранж — градиенты CTA */
    --accent-strong: #ea580c;

    --font-sans: 'Manrope', ...;
    --font-display: 'IBM Plex Sans', ...;

    --shadow-xs / --shadow-sm / --shadow-md / --shadow-lg / --shadow-xl
    --tap-target: 2.75rem;       /* 44px минимальная зона тапа */
    --focus-ring: ...
}
```

**Меняешь цвет бренда → меняется весь сайт.** Не дублируй цвета по разным местам — правь токен.

Использовать токены можно так:

```css
.my-button { background: var(--brand); }
.my-button:hover { background: var(--brand-strong); }
.my-card { box-shadow: var(--shadow-md); }
```

В Blade-шаблонах вместо `bg-red-600` пиши `style="background: var(--brand)"` или Tailwind `bg-[color:var(--brand)]`.

### 4.3. Шаблоны (Blade)

Это HTML с включениями `{{ ... }}` и директивами `@if / @foreach / @include`. Тебе нужно только менять разметку и CSS-классы. **Любую логику в фигурных скобках — оставлять как есть.**

| Файл | Что это |
|---|---|
| `resources/views/layouts/app.blade.php` | **Главный лейаут**: `<head>`, шапка сайта (логотип, поиск, корзина, профиль), подключение CSS/JS, тосты-уведомления, общие модалки. Всё что видишь на каждой странице — отсюда. |
| `resources/views/home/index.blade.php` | **Главная**: лендинг + форма входа. Открывается на `/`. |
| `resources/views/auth/login.blade.php` | **Страница входа** `/login`. Отдельный экран без шапки. |
| `resources/views/auth/register-request.blade.php` | **Регистрация** `/register-request`. Заявка на доступ. |
| `resources/views/catalog/index.blade.php` | **Каталог-список** `/catalog`. Сетка карточек товаров, фильтры. |
| `resources/views/catalog/product.blade.php` | **Карточка товара** `/catalog/product/{slug}`. Галерея, цена, описание, кнопка «В корзину». |
| `resources/views/catalog/category.blade.php` | **Страница категории** `/catalog/category/{slug}`. Та же сетка что в каталоге, но фильтр по категории. |
| `resources/views/catalog/categories-index.blade.php` | **Список всех категорий** `/catalog/categories`. Плитки категорий с картинками. |
| `resources/views/cart/index.blade.php` | **Корзина** `/cart`. Позиции, сумма, кнопка оформить. Здесь же inline-форма оформления заказа. |
| `resources/views/orders/index.blade.php` | **Мои заказы** `/orders`. История заявок клиента. |
| `resources/views/account/show.blade.php` | **Личный кабинет** `/account`. Профиль, адреса, контакты. |
| `resources/views/favorites/index.blade.php` | **Избранное** `/favorites`. Сохранённые товары. |
| `resources/views/legal/privacy.blade.php` | **Политика конфиденциальности**. |
| `resources/views/errors/404.blade.php` | **404** — страница не найдена. |
| `resources/views/errors/500.blade.php` | **500** — внутренняя ошибка. |
| `resources/views/errors/403.blade.php` | **403** — нет доступа. |

**Партиалы (фрагменты, подключаются в разные страницы через `@include`):**

| Файл | Что это |
|---|---|
| `resources/views/partials/breadcrumbs.blade.php` | Хлебные крошки (наверху страниц после шапки). |
| `resources/views/partials/footer.blade.php` | Футер сайта (контакты, ссылки, копирайт). |
| `resources/views/partials/sticky-cart-bar.blade.php` | Плавающая плашка «Оформить» внизу на мобиле когда в корзине что-то есть. |
| `resources/views/partials/support-widget.blade.php` | Виджет «Связаться с менеджером» в углу. |
| `resources/views/partials/favorite-toggle.blade.php` | Кнопка-сердечко «в избранное» на карточках. |

### 4.4. Tailwind vs кастом

Везде где можно — пользуйся **Tailwind-классами прямо в Blade** (`text-lg`, `mt-4`, `rounded-2xl`, `flex`, `gap-3` и т.п.). Это быстрее и предсказуемее.

Кастомный CSS в `app.css` — только для:
- сложных композиций которые Tailwind делать неудобно (анимации, `::before/::after`, специфичные ховеры);
- общих классов которые встречаются часто (`.catalog-buy-button`, `.brand-card`).

Шорткат: открой страницу в браузере, F12 → выдели элемент → посмотри какие классы на нём → найди их в `app.css` (если нет — значит это Tailwind, ищи название в Tailwind-доке).

---

## 5. Как смотреть результат

1. `npm run dev` запущен → Vite смотрит за `app.css` и Blade.
2. Сохраняй файл (`Ctrl+S`).
3. Браузер сам обновится через 0.5–1 сек (если не обновляется — `Ctrl+Shift+R`).
4. Если что-то сломалось — посмотри терминал с `npm run dev`, он покажет красную ошибку с номером строки.

Если правишь только CSS — горячая подмена без перезагрузки страницы (HMR). Удобно.

---

## 6. Адаптив

Сайт **mobile-first**. Breakpoints стандартные Tailwind:

| Префикс | Ширина |
|---|---|
| (без префикса) | мобильный, базовый стиль |
| `sm:` | ≥ 640px |
| `md:` | ≥ 768px |
| `lg:` | ≥ 1024px |
| `xl:` | ≥ 1280px |
| `2xl:` | ≥ 1536px |

Пример:
```html
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
```

Тест адаптива — F12 → Toggle device toolbar → Galaxy S20 / iPhone 12 / iPad. Обязательно проверить:
- ничего не вылезает за край (нет горизонтального скролла);
- все кнопки тапаются (минимум 44×44px — есть токен `--tap-target`);
- текст читается (не меньше 14px, лучше 16px на мобиле);
- шапка с лого не лезет на статус-бар.

---

## 7. Сборка под прод

После всех правок, перед передачей владельцу:

```bash
npm run build
```

Это компилирует CSS+JS в `public/build/`. Эти файлы потом идут на сервер. Сам ты их не коммить — владелец соберёт у себя.

---

## 8. Что НЕ трогать

**Никогда** не правь и не удаляй:

| Папка/файл | Почему |
|---|---|
| `app/` | PHP-код (контроллеры, модели). Не CSS — другой язык. |
| `config/` | Конфиги Laravel. |
| `database/` | Миграции (схема БД). |
| `routes/` | Маршруты URL. |
| `bootstrap/`, `vendor/`, `node_modules/`, `storage/` | Служебные. |
| `composer.json`, `composer.lock`, `package-lock.json` | Списки зависимостей. |
| `.env` | Локальные секреты. |

Внутри `.blade.php` файлов **не меняй и не удаляй**:
- `{{ ... }}` — вывод переменной;
- `{!! ... !!}` — вывод сырого HTML;
- `@if / @foreach / @include / @csrf / @error` — Blade-директивы;
- `route('...')`, `url('...')`, `asset('...')` — генерация ссылок и путей.

Менять можно:
- HTML-теги вокруг них (например, `<div>` поменять на `<section>`, добавить обёртку);
- `class="..."` (CSS-классы — твоя зона);
- `style="..."` (инлайн-стили — твоя зона);
- порядок блоков на странице.

---

## 9. Где взять брендовые ассеты

- Лого в SVG: `public/brand/major-logo-wide.svg`
- Иконки: лежат прямо в `layouts/app.blade.php` как inline-SVG. Если нужен набор — пиши, передам как файл.
- Шрифты: Manrope + IBM Plex Sans подключены из Google Fonts в `layouts/app.blade.php` (тег `<link rel="preconnect">` + `<link href="https://fonts.googleapis.com/...">`).

---

## 10. Если что-то не работает

| Симптом | Что проверить |
|---|---|
| `http://major.test` не открывается | Laragon Start All зажат? Apache горит зелёным? |
| Открывается, но «Connection refused» при логине | MySQL запущен? База `major` создана? Миграции прошли? |
| Стили не меняются после правок | `npm run dev` крутится в терминале? Без него Vite не пересобирает. `Ctrl+Shift+R` в браузере (жёсткое обновление). |
| В терминале `npm run dev` красная ошибка | Скопируй текст ошибки владельцу — обычно опечатка в CSS. |
| Сайт открылся, но все шрифты «системные» | Сеть до Google Fonts заблокирована? Открой devtools → Network, посмотри статусы запросов к `fonts.googleapis.com`. |
| Виден сайт но без вёрстки (голый HTML) | Не загрузился CSS. F12 → Network → проверь что есть запросы на `app.css` от Vite (порт 5173). |

---

## 11. Передача работы обратно

Когда нагалопировал правки и всё устраивает:

1. Сохрани все файлы.
2. В Git Bash в папке проекта:

   ```bash
   git status         # покажет что изменилось
   git add -A
   git commit -m "Дизайн: <короткое описание>"
   ```

3. Если есть доступ к репозиторию — `git push`. Если нет — заархивируй папки `resources/css/` и `resources/views/`, пришли владельцу.

**Никогда** не коммить:
- `.env` (свой пароль БД, ключи);
- `vendor/`, `node_modules/` (тяжёлые, ставятся командой);
- `storage/logs/` (логи).

В `.gitignore` они уже исключены — если не лезть в `.gitignore`, всё ок.

---

## Контакты

Владелец: МАЖОР. Любой вопрос — пиши, не догадывайся.
