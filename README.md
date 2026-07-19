# WPS Micro

Lightweight PHP framework core for building focused web applications.

WPS Micro provides the reusable request lifecycle, container, routing,
middleware, validation, sessions, Twig integration, database access,
migrations, and console primitives. Application controllers, models, routes,
views, frontend assets, and deployment files live in a separate application
skeleton.

## Requirements

- PHP 8.3 or higher
- Composer
- PDO and mbstring PHP extensions

The test suite runs against PHP 8.3, 8.4, and 8.5 in GitHub Actions.

## Start A New Application

Use the application skeleton instead of installing the framework into an empty
directory:

```bash
composer create-project webpagestudio/wps-micro-skeleton my-site
```

The skeleton requires this package and keeps application code outside
`vendor/`. Framework updates therefore do not replace controllers, models,
routes, migrations, or templates:

```bash
composer update webpagestudio/wps-micro
```

For an existing Composer project, install only the core:

```bash
composer require webpagestudio/wps-micro
```

## Package Structure

- `src` - framework runtime and public APIs
- `src/Console` - console application and reusable commands
- `src/Exceptions` - framework and HTTP exceptions
- `src/Middleware` - built-in middleware
- `tests` - framework unit and integration tests

All framework classes use the `WpsMicro\Core\` namespace.

## Application Boundary

The framework package owns infrastructure:

- `Request`, `Response`, `Router`, and `Dispatcher`
- `Container` and `Kernel`
- middleware pipeline and CSRF protection
- validation, sessions, and error handling
- Twig rendering and Vite manifest integration
- PDO connection, models, migrations, and migrator
- console application and generator commands

The application owns product behavior:

- controllers and application middleware
- models, repositories, and business services
- routes and configuration
- database migrations
- Twig templates and frontend assets
- public entry point and deployment configuration

Core classes never import `App\` classes or assume an application directory.
The application passes routes, middleware, paths, and error handlers through
configuration.

## Request Lifecycle

```text
Request -> Global Middleware -> Router -> Route Middleware -> Controller -> Response
```

The `Kernel` registers framework services in the PSR-11 container. The
`Dispatcher` executes the middleware pipeline, resolves a controller through
the container, invokes the matched action, and normalizes the result to a
`Response`.

## Bootstrap

A minimal application bootstrap can load environment values and create a
kernel from a PHP configuration file:

```php
<?php

declare(strict_types=1);

use WpsMicro\Core\Env;
use WpsMicro\Core\Kernel;

$rootPath = dirname(__DIR__);

require $rootPath . '/vendor/autoload.php';

Env::load($rootPath . '/.env');

return Kernel::fromConfigFile($rootPath . '/config/app.php');
```

The public front controller handles globals and sends the response:

```php
/** @var \WpsMicro\Core\Kernel $kernel */
$kernel = require dirname(__DIR__) . '/bootstrap/app.php';
$kernel->handleGlobals()->send();
```

## Routing

Route files return a callable that receives the router:

```php
use App\Controllers\ControllerProduct;
use WpsMicro\Core\Router;

return static function (Router $router): void {
    $router->get('/products/{id}', [ControllerProduct::class, 'actionShow']);
    $router->post('/cart/add', [ControllerCart::class, 'actionAdd']);
    $router->delete('/cart/{id}', [ControllerCart::class, 'actionRemove']);
};
```

Explicit `HEAD` routes are supported. When no explicit route exists, a `HEAD`
request falls back to the matching `GET` route and returns the same status and
headers without a response body.

## Controllers And Responses

Application controllers may extend the framework controller:

```php
namespace App\Controllers;

use WpsMicro\Core\Controller;
use WpsMicro\Core\Response;

final class ControllerProduct extends Controller
{
    public function actionShow(string $id): Response
    {
        return $this->render('products/show.twig', ['id' => $id]);
    }
}
```

Controller helpers return HTML, JSON, and redirects:

```php
return $this->render('products/index.twig', ['products' => $products]);
return $this->json(['products' => $products]);
return $this->redirect('/products');
```

`Response` supports case-insensitive header lookup and multiple values:

```php
$response
    ->setHeader('Cache-Control', 'no-store')
    ->addHeader('Set-Cookie', 'theme=dark; Path=/; SameSite=Lax')
    ->addHeader('Set-Cookie', 'locale=en; Path=/; SameSite=Lax');
```

## Middleware

Middleware implements `WpsMicro\Core\Middleware`:

```php
use WpsMicro\Core\Middleware;
use WpsMicro\Core\Request;
use WpsMicro\Core\Response;

final class AuthMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        return $next($request);
    }
}
```

Middleware can be configured globally, as default route middleware, or attached
to one route.

## Validation

The validator supports:

- `required`, `nullable`, and `confirmed`
- `string`, `array`, and `boolean`
- `email`, `url`, `integer`, and `numeric`
- `min`, `max`, and `in`

Browser validation failures flash sanitized input and redirect only to a
same-origin location. JSON requests receive a `422` response without starting
or changing the session.

## Database And Migrations

`Database` creates a configured PDO connection. `Model` provides that connection
to application persistence classes, while business workflows remain in
application services.

Migration files return a `Migration` instance and implement both directions:

```php
use WpsMicro\Core\Migration;

return new class extends Migration {
    public function up(PDO $db): void
    {
        $db->exec('CREATE TABLE products (id INTEGER PRIMARY KEY)');
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE products');
    }
};
```

## Console Commands

The framework provides migration commands and configurable generators.
Applications decide where generated files are written:

```php
$console
    ->add(new MakeControllerCommand($root . '/app/Controllers', 'App\\Controllers'))
    ->add(new MakeModelCommand($root . '/app/Models', 'App\\Models'))
    ->add(new MakeMigrationCommand($root . '/database/migrations'));
```

No generator writes inside the installed framework package.

## Development

Install dependencies and run the framework suite:

```bash
composer install
composer test
```

Validate package metadata:

```bash
composer validate --strict --no-check-publish
```
