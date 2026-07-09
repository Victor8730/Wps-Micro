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
- `application/Core` - framework core classes
- `application/Controllers` - application controllers
- `application/Models` - application models
- `application/Views` - Twig templates
- `application/Exceptions` - custom exceptions

## Request Lifecycle

The framework core follows a small request/response pipeline:

```text
Request -> Router -> Dispatcher -> Controller -> Response
```

- `Request` wraps PHP globals and exposes method, path, headers, query data, and body data.
- `Router` matches the request path to a controller action.
- `Dispatcher` creates the controller, executes the action, and normalizes the result.
- `Controller` actions should return a `Response`.
- `Response` sends status, headers, and content to the client.
