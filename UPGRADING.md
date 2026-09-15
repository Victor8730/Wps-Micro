# Upgrading WPS Micro

## Upgrading From 3.0 To 3.1

PHP 8.3 remains the minimum requirement. Application code stays in the skeleton;
no directory migration is required.

```bash
composer require webpagestudio/wps-micro:^3.1
```

Version 3.1 adds named routes, groups, parameter constraints, `route:list`, and
custom validation rules. Review these behaviors when updating application code:

- Duplicate method/path pairs and duplicate route names fail during registration.
- Constraints evaluate decoded values within individual URL segments. Encoded
  slashes stay inside their parameter. Only a final standalone parameter may
  consume additional unencoded segments when its constraint allows slashes.
- `min` and `max` compare values for fields using `integer` or `numeric`, and
  character lengths for string fields. Decimal and exponent-form numeric strings
  preserve precision; non-finite values and unsafe exponents are rejected.
- String validation rules are always names. Use closures, invokable objects,
  callable arrays, or first-class callable syntax for inline validation.

Run application tests after updating. Package versions come from Git tags;
do not add a hard-coded `version` field to the framework's `composer.json`.

## Upgrading From 2.1 To 3.0

WPS Micro 3 separates the reusable framework core from application code. This
is an intentional major-version boundary and is not an in-place directory
upgrade.

For a new application, start with the application skeleton:

```bash
composer create-project webpagestudio/wps-micro-skeleton my-site
```

For an existing 2.1 application, use the following migration checklist.

### 1. Separate Application Code

Move controllers, models, services, middleware, routes, migrations, templates,
frontend assets, public files, and deployment configuration into an application
repository. The v3 core package contains only reusable framework code.

Use the official
[WPS Micro Skeleton](https://github.com/Victor8730/Wps-Micro-Skeleton)
as the reference application structure.

### 2. Update Composer

Remove the custom `application/vendor` directory configuration and require the
v3 core package:

```json
{
  "require": {
    "php": "^8.3",
    "webpagestudio/wps-micro": "^3.0"
  },
  "autoload": {
    "psr-4": {
      "App\\": "app/"
    }
  }
}
```

Dependencies are installed into the standard root-level `vendor/` directory.

### 3. Update Framework Namespaces

Replace the v2 framework namespaces:

```text
Core\*       -> WpsMicro\Core\*
Exceptions\* -> WpsMicro\Core\Exceptions\*
```

Application classes should use an application-owned namespace such as `App\`.

### 4. Update Bootstrap And Paths

Load `vendor/autoload.php`, load the environment file, and create the kernel
from the application configuration:

```php
use WpsMicro\Core\Env;
use WpsMicro\Core\Kernel;

$rootPath = dirname(__DIR__);

require $rootPath . '/vendor/autoload.php';

Env::load($rootPath . '/.env');

return Kernel::fromConfigFile($rootPath . '/config/app.php');
```

Update configured paths for routes, migrations, views, Twig cache, logs, and
the Vite manifest to their locations in the application repository.

### 5. Update Routes

The configured routes file must exist, be readable, and return a callable:

```php
use WpsMicro\Core\Router;

return static function (Router $router): void {
    $router->get('/', [ControllerHome::class, 'actionIndex']);
};
```

Legacy convention routing is not available.

### 6. Return Response Objects

Every controller action must return `WpsMicro\Core\Response` or one of its
subclasses. String returns and `echo` output are no longer converted into a
response:

```php
public function actionIndex(): Response
{
    return $this->render('home/home.twig');
}
```

### 7. Configure Console Generators

Generator commands no longer assume application directories or namespaces.
Pass both from the application console bootstrap:

```php
$console
    ->add(new MakeControllerCommand($root . '/app/Controllers', 'App\\Controllers'))
    ->add(new MakeModelCommand($root . '/app/Models', 'App\\Models'))
    ->add(new MakeMigrationCommand($root . '/database/migrations'));
```

Run the application and framework test suites after moving the code. Review the
application skeleton when translating frontend and Docker configuration.
