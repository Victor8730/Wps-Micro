# WPS-Micro

Small MVC skeleton for quickly building PHP web applications.

## Requirements

- PHP 7.4 or higher
- Composer
- Docker and Docker Compose, if you want to run the bundled local environment

## Installation

Install PHP dependencies:

```bash
composer install
```

Composer installs dependencies into `application/vendor`.

Create your local environment file:

```bash
cp .env_example .env
```

The committed `.env_example` contains default values. The local `.env` file is
ignored by Git and can be adjusted per machine.

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
- PHP-FPM
- MariaDB on host port `3307`
- project root mounted to `/var/www/wps-micro-docker`
- nginx document root set to `/var/www/wps-micro-docker/public`

If port `80` is already busy on your machine, change the nginx port mapping in
`docker-compose.yaml`, for example:

```yaml
ports:
  - 8080:80
```

Then open:

```text
http://localhost:8080
```

Inside Docker, the application connects to MariaDB through `DB_HOST=mariadb`.
If you run PHP directly on your machine and only use the MariaDB container,
change `DB_HOST` to `127.0.0.1` and keep `DB_PORT=3307`.

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
- `public/css`, `public/js`, `public/img`, `public/fonts` - public assets
- `application/bootstrap.php` - application bootstrap
- `application/console.php` - console command entry point
- `application/Config/app.php` - application configuration
- `application/Database/schema.sql` - local database bootstrap schema
- `.env_example` - environment configuration template
- `application/Core` - framework core classes
- `application/Controllers` - application controllers
- `application/Models` - application models
- `application/Views` - Twig templates
- `application/Exceptions` - custom exceptions

## Request Lifecycle

The framework core follows a small request/response pipeline:

```text
Request -> Global Middleware -> Router -> Route Middleware -> Controller -> Response
```

- `Request` wraps PHP globals and exposes method, path, headers, query data, and body data.
- Global middleware runs before route matching.
- `Router` matches the request path to a controller action.
- Route middleware runs after matching and before the controller is created.
- `Dispatcher` creates the controller, executes the action, and normalizes the result.
- `Controller` actions should return a `Response`.
- `Response` sends status, headers, and content to the client.

## Routing

Explicit routes live in `application/Routes/web.php` and are registered by the
application kernel:

```php
$router->get('/', [ControllerHome::class, 'actionIndex']);
$router->get('/products/{id}', [ControllerProduct::class, 'actionShow']);
$router->post('/cart/add', [ControllerCart::class, 'actionAdd']);
```

Route parameters are passed to action arguments with the same name:

```php
public function actionShow(string $id): Response
{
    // ...
}
```

Forms can use `_method` to match `PUT`, `PATCH`, and `DELETE` routes.
When a path exists for another HTTP method, the framework returns a `405 Method
Not Allowed` response with an `Allow` header instead of using convention-based
routing.

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
$router
    ->post('/cart/add', [ControllerCart::class, 'actionAdd'])
    ->middleware(App\Middleware\AuthMiddleware::class);
```

Middleware classes implement `Core\Middleware` and receive the current
`Request` plus the next layer callback.

Global middleware wraps route matching and error responses. Route middleware
runs after a route is matched, while the controller is created only after all
middleware passes the request forward.

CSRF protection is registered as default route middleware, so routing errors
such as `404` and `405` are resolved before a session is opened.

## Application Kernel

`Kernel` builds the framework infrastructure through a small shared-service
container. It creates the configured Twig environment, database connection,
`Router`, and `Dispatcher`; controller dependencies are resolved through
constructor injection.

Default application settings live in `application/Config/app.php`. Environment
overrides are loaded from `.env` before the kernel is created.

## Model Layer

Models receive a configured `PDO` connection from the container and should focus
on application data access. Keep validation in validators, request handling in
controllers, and business workflows in services as the application grows.

## Validation

Controllers can validate request input with simple rules:

```php
$data = $this->validate([
    'email' => 'required|email',
    'name' => 'required|min:2|max:255',
]);
```

Available rules include `required`, `nullable`, `email`, `integer`, `numeric`,
`url`, `min`, `max`, and `in`. On validation failure the framework flashes
errors and old input, then redirects back. AJAX requests receive a `422` JSON
response.

## Sessions

Controllers receive a shared `Session` service through the base controller. Use
`$this->session->get()`, `$this->session->set()`, and
`$this->session->flash()` for simple state such as carts, flash messages, and
authentication markers.

The PHP session starts lazily on the first session read or write. Requests that
do not use session data avoid opening the session and acquiring its lock.

## Views

Twig templates include small helpers for common website work:

```twig
{{ asset('css/app.css') }}
{{ url('/products') }}
{{ csrf_token() }}
{{ csrf_field() }}
{{ old('email') }}
{{ flash('success') }}
{{ error('email') }}
```

CSRF protection is enabled for `POST`, `PUT`, `PATCH`, and `DELETE` requests.

## Error Handling

When `APP_DEBUG=true`, uncaught exceptions render a small debug page with the
exception class, message, file, line, and trace. In production, set
`APP_DEBUG=false` to return a generic `500` response.

## Migrations

Database migrations live in `application/Database/migrations`. Run pending
migrations with:

```bash
php application/console.php migrate
```

Roll back the last migration with:

```bash
php application/console.php migrate:rollback
```

Roll back multiple migrations with:

```bash
php application/console.php migrate:rollback --steps=2
```

The Docker database bootstrap schema also records the initial migration, so the
CLI migrator can be used safely after a fresh `docker compose up`.

## Console

The old installer has been replaced by console commands:

```bash
php application/console.php list
php application/console.php make:controller Product
php application/console.php make:model Product
php application/console.php make:migration create_products_table
```
