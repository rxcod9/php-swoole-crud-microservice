# PHP Swoole CRUD Microservice

A high-performance **PHP CRUD microservice** built with **Swoole**, featuring **MySQL**, **Redis**, **Prometheus**, **Grafana**, **Caddy**, and **Swagger UI** integration. Designed for **scalable**, **observable**, and **containerized** deployments.

By 🐼 [Ramakant Gangwar](https://github.com/rxcod9)


# ⚙️ Swagger / OpenAPI
![Swagger](docs/images/swagger.webp)

# ⚡️ Performance
![Performance](docs/images/performance.webp)

# ❤️ Health
![Performance](docs/images/health.webp)

# 📊 Grafana Dashboards
![Grafana1](docs/images/grafana1.webp)
![Grafana2](docs/images/grafana2.webp)
![Grafana3](docs/images/grafana3.webp)


[![Latest Version](https://img.shields.io/github/v/release/rxcod9/php-swoole-crud-microservice?style=flat-square)](https://github.com/rxcod9/php-swoole-crud-microservice/releases)
![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/rxcod9/php-swoole-crud-microservice/run-tests.yml?branch=main&label=tests)
[![Total Downloads](https://img.shields.io/packagist/dt/rxcod9/php-swoole-crud-microservice.svg?style=flat-square)](https://packagist.org/packages/rxcod9/php-swoole-crud-microservice)

---

## Features

- Fast HTTP server powered by **Swoole**
- MySQL database with **connection pooling**
- Redis caching and pooling
- Prometheus metrics endpoint
- Grafana dashboards for monitoring
- **Caddy** for HTTPS and reverse proxy
- Swagger UI for API documentation
- Health checks for all services

---

## Getting Started

### Prerequisites

**Docker** & **Docker Compose**

### Docker Hub Quick Start

If you prefer using the pre-built Docker image, follow these steps:

```bash
# Pull the latest image
docker pull rxcod9/php-swoole-crud-microservice:latest

# Run the container
docker run -d -p 9501:9501 --name php-crud-microservice rxcod9/php-swoole-crud-microservice

# Run database migrations inside the running container
docker exec -it php-crud-microservice php scripts/migrate.php
```

### Docker Compose Usage

This repository includes a `docker-compose.yml` to run the full stack:

```bash
# Start all services (PHP app, MySQL, Redis, Prometheus, Grafana, Caddy)
docker compose up -d --build

# Stop all services
docker compose down

# View logs
docker compose logs -f
```

Edit `.env` or `docker-compose.override.yml` to customize ports and database credentials.


### Quick Start

```bash
# Copy example environment
cp .env.example .env

# Install PHP dependencies
composer install

# Start all services in detached mode
docker compose up -d --build
```

### Database Migration

```bash
# Run migrations inside the app container
docker compose exec app php scripts/migrate.php
```

### API Documentation

```bash
# Generate OpenAPI spec
php bin/generate-swagger.php
```

Access Swagger UI at [http://localhost:8080](http://localhost:8080)

---

## Example API Usage

```bash
# Create a user
curl -s -X POST http://localhost:9501/users \
    -H 'Content-Type: application/json' \
    -d '{"name":"alice","email":"alice@example.com"}'

# Get all users
curl -s -X GET http://localhost:9501/users -H 'Content-Type: application/json' | jq

# Get a user by ID
curl -s -X GET http://localhost:9501/users/1 -H 'Content-Type: application/json' | jq

# Get a user by email
curl -s -X GET http://localhost:9501/users/email/alice%40example.com -H 'Content-Type: application/json' | jq

# Update a user
curl -i -X PUT http://localhost:9501/users/1 \
    -H 'Content-Type: application/json' \
    -d '{"name":"alice-updated","email":"alice-updated@example.com"}'

# Delete a user
curl -i -X DELETE http://localhost:9501/users/1 -H 'Content-Type: application/json'
```

---

## Benchmarking

```bash
# Using k6
k6 run --http-debug="full" k6/crud_load_test.js > logs/k6.log 2>&1
k6 run --http-debug="full" k6/crud_load_test_read.js > logs/k6_read.log 2>&1

# Using ApacheBench
ab -n 100000 -c 100 -v 4 http://localhost:9501/users/1 2>&1 | tee ab.log
```

---

## Monitoring

- **Prometheus** scrapes metrics from the app and MySQL exporter.
- **Grafana** visualizes metrics (default port: `3000`).

---

## Environment Variables

All configurable options are defined in `docker-compose.yml` and `.env.example`.

---

## License

MIT