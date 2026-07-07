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

Point your web server document root to the project root and route all missing
files to `index.php`.

Example nginx rule:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

The app expects `index.php` to be the front controller.

## Project Structure

- `index.php` - front controller
- `application/bootstrap.php` - application bootstrap
- `application/Core` - framework core classes
- `application/Controllers` - application controllers
- `application/Models` - application models
- `application/Views` - Twig templates
- `application/Exceptions` - custom exceptions
