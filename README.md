# WPS-Micro

Lightweight PHP framework for quickly building focused web applications.

## Requirements

- PHP 8.3 or higher
- Composer
- Node.js 20.19 or higher and npm for frontend development
- Docker and Docker Compose, if you want to run the bundled local environment

The framework test suite runs against PHP 8.3, 8.4, and 8.5 in GitHub Actions.

## Installation

Install PHP dependencies:

```bash
composer install
```

Composer installs dependencies into `application/vendor`.

Install frontend dependencies and create production assets:

```bash
npm ci
npm run build
```

Vite writes versioned CSS and JavaScript files plus its manifest to
`public/build`. Generated assets and `node_modules` are ignored by Git.

Create your local environment file:

```bash
cp .env_example .env
```

The committed `.env_example` contains default values. The local `.env` file is
ignored by Git and can be adjusted per machine.

Without an environment file, the framework uses fail-safe production defaults:
debug output and Twig auto-reload are disabled, while secure session cookies are
enabled. Local development should therefore always start from `.env_example`.

## Run with Docker

Build and start the application:

```bash
docker compose up --build
```

Open the app in a browser:

```text
http://localhost
```

The Docker setup uses:

- nginx on host port `80`
- PHP 8.3 FPM with OPcache
- MariaDB on host port `3307`
- Node.js 24 in a one-shot service that builds the Vite assets
- a one-shot migration service that runs after MariaDB becomes healthy
- project root mounted to `/var/www/wps-micro-docker`
- nginx document root set to `/var/www/wps-micro-docker/public`

If port `80` is already busy on your machine, change the nginx port mapping in
`docker-compose.yaml` and update `DOCKER_APP_URL` in `.env`, for example:

```yaml
ports:
  - 8080:80
```

```dotenv
DOCKER_APP_URL=http://localhost:8080
```

Then open:

```text
http://localhost:8080
```

Inside Docker, the application connects to MariaDB through `DB_HOST=mariadb`.
If you run PHP directly on your machine and only use the MariaDB container,
change `DB_HOST` to `127.0.0.1` and keep `DB_PORT=3307`.

For Tailwind and JavaScript hot reload, set this value in `.env`:

```dotenv
VITE_DEV_SERVER_URL=http://localhost:5173
```

Then start the optional Vite service together with the application:

```bash
docker compose --profile dev up --build
```

Remove the value or leave it empty when using the compiled files from
`public/build`.

## Production Profile

Create a deployment environment from the production template and replace all
example URLs, credentials, and passwords:

```bash
cp .env.production.example .env.production
```

Build and start the immutable production services:

```bash
docker compose --env-file .env.production -f docker-compose.production.yaml up -d --build
```

The production Dockerfile is multi-stage: Node.js builds the minimized Vite and
Tailwind assets, Composer installs optimized production dependencies, and the
final FPM and nginx images receive only their runtime files. They contain no
`node_modules`, frontend toolchain, Composer binary, or development dependencies.

The production Compose file has no source bind mounts and does not expose
MariaDB. It runs pending migrations after the database healthcheck, then starts
FPM and nginx. Its PHP profile disables displayed errors and OPcache timestamp
checks. Rebuild and restart the images for every deployment.

## Run with a Local Web Server

Point your web server document root to the `public` directory and route all
missing files to `public/index.php`.

Example nginx rule:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

The app expects `public/index.php` to be the front controller.
For Apache, the bundled `public/.htaccess` provides the equivalent rewrite rule
when `mod_rewrite` and `AllowOverride` are enabled.

For a quick local check without nginx, you can use PHP's built-in server:

```bash
php -S localhost:8000 -t public public/index.php
```

Then open:

```text
http://localhost:8000
```

## Project Structure

- `public/index.php` - front controller
- `public/img` - static public images
- `public/build` - generated Vite assets, not committed
- `resources/css`, `resources/js` - Tailwind source and JavaScript entry point
- `application/bootstrap.php` - shared HTTP and console kernel bootstrap
- `application/console.php` - console command entry point
- `application/Config/app.php` - application configuration
- `application/Database/migrations` - ordered database migrations
- `.env_example` - environment configuration template
- `application/Core` - framework core classes
- `application/Controllers` - application controllers
- `application/Middleware` - application-level middleware
- `application/Models` - application models
- `application/Services` - application business workflows
- `application/Views` - Twig templates
- `application/Exceptions` - custom exceptions
- `application/Tests` - PHPUnit test suite
- `.env.production.example` - safe production environment template
- `vite.config.js` - Vite and Tailwind build configuration
- `docker/production.Dockerfile` - multi-stage production image
- `docker-compose.production.yaml` - immutable production services
- `docker/production.ini` - optimized production PHP profile

## Request Lifecycle

The framework core follows a small request/response pipeline:

```text
Request -> Global Middleware -> Router -> Route Middleware -> Controller -> Response
```

- `Request` wraps PHP globals and exposes headers, JSON/form input, files, and cookies.
- Global middleware runs before route matching.
- `Router` matches the request path to a controller action.
- Route middleware runs after matching and before the controller is created.
- `Dispatcher` creates the controller, executes the action, and normalizes the result.
- `Controller` actions should return a `Response`.
- `Response` sends status, single-value or multi-value headers, and content to the client.

## Routing

Explicit routes live in `application/Routes/web.php` and are registered by the
application kernel:

```php
$router->get('/', [ControllerHome::class, 'actionIndex']);
$router->get('/products/{id}', [ControllerProduct::class, 'actionShow']);
$router->head('/health', [ControllerHealth::class, 'actionHead']);
$router->post('/cart/add', [ControllerCart::class, 'actionAdd']);
```

Only explicitly registered routes are dispatchable. Unknown paths return `404`;
controller classes are never discovered from URL segments.

Route parameters are passed to action arguments with the same name:

```php
public function actionShow(string $id): Response
{
    // ...
}
```

Forms can use `_method` to match `PUT`, `PATCH`, and `DELETE` routes.
When a path exists for another HTTP method, the framework returns a `405 Method
Not Allowed` response with an `Allow` header. A `HEAD` request first checks for
an explicit `HEAD` route and then falls back to the matching `GET` route. The
response preserves its status and headers but does not send a body.

Unsafe form methods require a CSRF token:

```twig
<form method="post" action="{{ url('/cart/add') }}">
    {{ csrf_field() }}
</form>
```

## Middleware

Global and default route middleware are configured in `application/Config/app.php`.
Route-level middleware can also be attached directly in
`application/Routes/web.php`:

```php
use Middleware\AuthMiddleware;

$router
    ->post('/cart/add', [ControllerCart::class, 'actionAdd'])
    ->middleware(AuthMiddleware::class);
```

Middleware classes implement `Core\Middleware` and receive the current
`Request` plus the next layer callback.

The included `Middleware\AuthMiddleware` example considers a request
authenticated when the session contains `user_id`. Browser requests are
redirected to `/login`, while clients that request JSON receive a `401`
response. Store the user identifier after a successful login:

```php
$this->session->set('user_id', $userId);
```

Global middleware wraps route matching and error responses. Route middleware
runs after a route is matched, while the controller is created only after all
middleware passes the request forward.

CSRF protection is registered as default route middleware, so routing errors
such as `404` and `405` are resolved before a session is opened.

## Application Kernel

`Kernel` builds the framework infrastructure through a small shared-service
container. It creates the configured database connection, `Router`, and
`Dispatcher`; controller dependencies are resolved through constructor
injection. Twig is hidden behind a lazy `ViewRenderer`, so JSON controllers do
not initialize the template engine or its cache.

The container implements PSR-11, supports explicit interface bindings, detects
circular dependencies, and binds the current `Request` by type while the
middleware pipeline is running.

Default application settings live in `application/Config/app.php`. Environment
overrides are loaded from `.env` before the kernel is created.

## Request Data

JSON and URL-encoded request bodies are parsed automatically. Common request
helpers include:

```php
$request->input('email');
$request->query('page', 1);
$request->body('name');
$request->json('product_id');
$request->only(['email', 'name']);
$request->file('avatar');
$request->cookie('theme');
```

Malformed JSON receives a `400 Bad Request` response. A client can request JSON
errors with `Accept: application/json`.

## Responses

Controller actions can render HTML, return JSON, or redirect through the base
controller helpers:

```php
return $this->render('products/show.twig', ['product' => $product]);
return $this->json(['product' => $product], 200);
return $this->redirect('/products');
```

Response headers are case-insensitive. Use `setHeader()` to replace a value and
`addHeader()` when a header needs multiple values:

```php
$response = $this->json(['status' => 'ok']);
$response->setHeader('Cache-Control', 'no-store');
$response->addHeader('Set-Cookie', 'theme=dark; Path=/; SameSite=Lax');
$response->addHeader('Set-Cookie', 'locale=en; Path=/; SameSite=Lax');

return $response;
```

Header names and values are validated, and values containing line breaks are
rejected. Status codes must be between `100` and `599`.

## Model Layer

Models receive a configured `PDO` connection from the container and should focus
on application data access. Keep validation in validators, request handling in
controllers, and business workflows in services as the application grows.

The included `Home` model, `home_messages` migration, and home page demonstrate
the complete read path from a database migration through a model and controller
to a Twig template.

## Validation

Controllers can validate request input with simple rules:

```php
$data = $this->validate([
    'name' => 'required|string|min:2|max:120',
    'email' => 'required|string|email|max:255',
    'age' => 'nullable|integer',
    'price' => 'required|numeric',
    'website' => 'nullable|string|url',
    'active' => 'required|boolean',
    'tags' => 'nullable|array',
    'role' => 'required|in:admin,editor,customer',
    'password' => 'required|string|min:8|max:255|confirmed',
]);
```

Rules can be provided as a pipe-separated string or as an array:

```php
$data = $this->validate([
    'status' => ['required', 'in:draft,published'],
]);
```

| Rule | Example | Description |
| --- | --- | --- |
| `required` | `required` | The field must be present and not empty. |
| `nullable` | `nullable` | The field may be `null` or an empty string. |
| `string` | `string` | The value must be a string. |
| `array` | `array` | The value must be an array. |
| `boolean` | `boolean` | The value must be `true`, `false`, `0`, `1`, `"0"`, or `"1"`. |
| `email` | `email` | The value must be a valid email address. |
| `integer` | `integer` | The value must be a valid integer. |
| `numeric` | `numeric` | The value must be numeric. |
| `url` | `url` | The value must be a valid URL. |
| `min` | `min:8` | The value must contain at least the given number of characters. |
| `max` | `max:255` | The value may not exceed the given number of characters. |
| `in` | `in:admin,editor` | The value must match one of the listed values. |
| `confirmed` | `confirmed` | The value must match the corresponding `_confirmation` field. |

For example, `password|confirmed` expects a `password_confirmation` input with
the same value. Fields without the `required` rule are optional. `min` and `max`
measure string length, so use the `string` rule for those fields. On browser
validation failure the framework flashes errors and non-sensitive old input,
then redirects only to a same-origin location. Requests that expect JSON return
`422` immediately without opening or writing to the session.

The validator is intentionally stateless and only validates request data. File
operations, remote URL checks, and other I/O belong in dedicated application
services where timeouts and failures can be handled explicitly.

## Sessions

Controllers that use sessions receive the shared `Session` service through
constructor injection. Store it on that controller and use `get()`, `set()`,
and `flash()` for simple state such as carts, flash messages, and
authentication markers. Controllers that do not declare `Session` avoid that
dependency entirely.

The PHP session starts lazily on the first session read or write. Requests that
do not use session data avoid opening the session and acquiring its lock.

Use `$this->session->regenerate()` after authentication,
`$this->session->invalidate()` during logout, and `$this->session->close()`
before long-running work when no more session writes are needed. Flash values
expire after one following request. Cookie name, lifetime, domain, security,
HTTP-only, and SameSite settings are configured through `.env`; session lifetime
is expressed in seconds.

## Authentication

The example application includes a complete session-based authentication flow:

- `GET /register` and `POST /register` create a user
- `GET /login` and `POST /login` authenticate credentials
- `GET /account` displays the authenticated user
- `POST /logout` invalidates the authenticated session

Passwords are stored with PHP's `PASSWORD_DEFAULT` algorithm. Authentication
regenerates the session identifier, logout invalidates it, and user records
passed to views never contain the password hash. The account and logout routes
are protected by `Middleware\AuthMiddleware`.

Create the `users` table by running the pending migrations:

```bash
php application/console.php migrate
```

## Frontend Assets

The example application uses Vite, Tailwind CSS 4, and vanilla JavaScript. The
main entry imports both the CSS source and the small client-side form behavior:

```text
resources/js/app.js -> resources/css/app.css
```

Create minimized, versioned production assets with:

```bash
npm run build
```

For local hot reload outside Docker, set
`VITE_DEV_SERVER_URL=http://localhost:5173` in `.env`, then run:

```bash
npm run dev
```

Tailwind scans Twig templates and JavaScript sources through the `@source`
directives in `resources/css/app.css`. No separate Tailwind config file is
required.

## Views

WPS Micro uses Twig for server-rendered HTML. Templates live in
`application/Views` by default, and every template name passed to a controller
is relative to that directory. Use forward slashes in template names on every
operating system.

```text
application/Views/
|-- layouts/
|   `-- app.twig
|-- partials/
|   `-- product-card.twig
|-- products/
|   |-- index.twig
|   `-- show.twig
`-- 404.twig
```

Group page templates by feature or controller, keep shared page frames in
`layouts`, and keep reusable fragments in `partials`. The directory names are
conventions rather than framework requirements, so they can be adapted to the
application.

### Rendering Templates

Controllers extending `Core\Controller` can return a rendered Twig response
with `render()`:

```php
public function actionShow(string $id): Response
{
    $product = $this->products->find($id);

    return $this->render('products/show.twig', [
        'auth_user' => $this->auth->user(),
        'product' => $product,
        'related_products' => $this->products->related($id),
    ]);
}
```

The matching route can pass `{id}` directly to the action:

```php
$router->get('/products/{id}', [ControllerProduct::class, 'actionShow']);
```

The first `render()` argument is the template path, the second is its context,
and the optional third argument is the HTTP status code:

```php
return $this->render('errors/not-found.twig', [], 404);
```

Only values explicitly passed in the context, Twig built-ins, and registered
helpers are available to the template. Prefer preparing data in controllers or
services instead of running business logic inside Twig.

### Layout Inheritance

The bundled `layouts/app.twig` template contains the shared document, header,
navigation, logo, footer, Vite entry, and two overridable blocks:

```twig
<title>{% block title %}WPS Micro{% endblock %}</title>

{% block content %}{% endblock %}
```

A page extends that layout and replaces the blocks it needs. The `extends`
statement should be the first template instruction:

```twig
{% extends 'layouts/app.twig' %}

{% block title %}{{ product.name }} | WPS Micro{% endblock %}

{% block content %}
    <article>
        <h1>{{ product.name }}</h1>
        <p>{{ product.description }}</p>
    </article>
{% endblock %}
```

Layouts can extend other layouts as the application grows. For example, an
account layout may extend `layouts/app.twig`, wrap the main content with account
navigation, and expose another block for individual account pages. Keep the
inheritance chain short so it remains obvious where the final markup comes
from.

### Partials And Includes

Use `include` for repeated fragments such as navigation, alerts, product cards,
pagination, and form fields. A partial receives the current context by default:

```twig
{% include 'partials/product-card.twig' %}
```

Passing an explicit context with `only` makes the partial's dependencies clear
and prevents unrelated page variables from leaking into it:

```twig
{% for product in related_products %}
    {% include 'partials/product-card.twig' with {
        product: product,
        show_price: true
    } only %}
{% endfor %}
```

The partial can then focus only on those values:

```twig
<article>
    <h2>
        <a href="{{ url('/products/' ~ product.id) }}">{{ product.name }}</a>
    </h2>

    {% if show_price %}
        <p>{{ product.price }}</p>
    {% endif %}
</article>
```

For small reusable markup functions, Twig macros are another option:

```twig
{# application/Views/macros/ui.twig #}
{% macro submit(label) %}
    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-white">
        {{ label }}
    </button>
{% endmacro %}
```

```twig
{% import 'macros/ui.twig' as ui %}

{{ ui.submit('Save product') }}
```

### Variables And Control Flow

Twig dot notation works with array keys and public object properties. Use
conditions and loops for presentation decisions:

```twig
{% if products is empty %}
    <p>No products found.</p>
{% else %}
    {% for product in products %}
        <p>{{ loop.index }}. {{ product.name }}</p>
    {% endfor %}
{% endif %}
```

Check optional values before reading them. The bundled layout follows this
pattern for authentication data:

```twig
{% if auth_user is defined and auth_user %}
    <p>{{ auth_user.name }}</p>
{% endif %}
```

### View Helpers

Twig templates include small helpers for common website work:

```twig
{{ vite('resources/js/app.js') }}
{{ asset('img/logo.png') }}
{{ url('/products') }}
{{ csrf_token() }}
{{ csrf_field() }}
{{ old('email') }}
{{ flash('success') }}
{{ error('email') }}
```

- `vite()` renders development-server tags or versioned production assets from
  Vite's manifest.
- `asset()` builds a URL for a file stored directly in `public`.
- `url()` builds an application URL using the configured `APP_URL`.
- `csrf_token()` returns the current token, while `csrf_field()` renders its
  hidden form input.
- `old()` returns input flashed during the previous request.
- `flash()` pulls a one-time session message. Assign it to a Twig variable when
  it needs to be checked and displayed.
- `errors()` returns all validation errors, while `error()` returns the first
  message for one field.

The `vite()` helper uses `VITE_DEV_SERVER_URL` when it is configured. Otherwise
it reads the production manifest and includes versioned CSS, module preload,
and JavaScript tags. The `asset()` helper remains available for files that are
copied directly into `public`.

### Forms And Validation State

Every state-changing form should contain a CSRF field. The validation exception
handler automatically flashes errors and non-sensitive old input back to the
next request:

```twig
<form action="{{ url('/products') }}" method="post" data-validate novalidate>
    {{ csrf_field() }}

    <label for="name">Name</label>
    <input
        id="name"
        name="name"
        value="{{ old('name') }}"
        aria-invalid="{{ error('name') ? 'true' : 'false' }}"
        required
    >

    {% if error('name') %}
        <p>{{ error('name') }}</p>
    {% endif %}

    <button type="submit">Save</button>
</form>
```

CSRF protection is enabled for `POST`, `PUT`, `PATCH`, and `DELETE` requests.
The bundled templates have no jQuery or Bootstrap dependency. Their small
client-side form validation behavior is written in vanilla JavaScript.

### Escaping, Tailwind, And Cache

Twig HTML autoescaping is enabled by default, so `{{ value }}` is safe for
ordinary user-provided text. Use `|raw` only for trusted HTML that has already
been sanitized. Framework helpers that intentionally return markup, such as
`vite()` and `csrf_field()`, are registered as safe HTML.

Tailwind scans files under `application/Views`. Keep complete class names in the
templates so the compiler can discover them. Prefer a map of full classes over
dynamically composing names such as `bg-{{ color }}-500`.

View paths, caching, reload behavior, and escaping are controlled by:

```dotenv
TWIG_VIEWS_PATH=application/Views
TWIG_CACHE_PATH=application/Cache
TWIG_AUTO_RELOAD=true
TWIG_AUTOESCAPE=html
```

The Twig cache directory must be writable by PHP. Keep auto-reload enabled for
local development and disabled in production; production deployments should
rebuild and restart the application image when templates change.

## Error Handling

When `APP_DEBUG=true`, uncaught exceptions render a small debug page with the
exception class, message, file, line, and trace. In production, set
`APP_DEBUG=false` to return a generic `500` response. JSON clients receive JSON
error payloads, and server errors are written to `LOG_PATH`.
Failures that happen before the application configuration is available are sent
to the standard PHP or FPM error log. Browser `404` rendering is configured by
the application through `errors.not_found`, keeping the Core dispatcher
independent from application controllers.

## Migrations

Database migrations live in `application/Database/migrations`. Run pending
migrations with:

```bash
php application/console.php migrate
```

The migrator creates its own tracking table before applying migration files, so
there is no separate schema dump to keep synchronized. Migration files are the
single source of truth for the database structure.

Roll back the last migration with:

```bash
php application/console.php migrate:rollback
```

Roll back multiple migrations with:

```bash
php application/console.php migrate:rollback --steps=2
```

Docker Compose runs the same command through its one-shot `migrate` service
after MariaDB passes its healthcheck. PHP-FPM starts only when that command
finishes successfully.

Migration tracking supports both MySQL/MariaDB and SQLite connections.

## Console

The old installer has been replaced by console commands:

```bash
php application/console.php list
php application/console.php make:controller Product
php application/console.php make:model Product
php application/console.php make:migration create_products_table
```

## Testing

Run the framework test suite with:

```bash
composer test
```

The suite includes unit tests plus integration coverage for the application
kernel, routing, middleware, Twig rendering, CSRF, PDO, migrations, rollback,
production configuration, and error handling. SQLite integration tests require
the `pdo_sqlite` extension.

GitHub Actions builds and audits the frontend on Node.js 24, validates Composer,
audits PHP dependencies, lints project PHP files, and runs PHPUnit on PHP 8.3,
8.4, and 8.5.
