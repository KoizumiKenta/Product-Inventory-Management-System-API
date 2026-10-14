# Inventory API

A small REST API for products, the categories they sit in and the suppliers that provide them.
Laravel 11, token auth with Sanctum, filtering and pagination on the product list.

Swagger UI is served at `/docs` and reads `docs/openapi.yaml`.

## Requirements

PHP 8.4+ with PDO SQLite enabled and Composer. SQLite is enough to run it locally. The Docker
stack uses MySQL and Redis instead, so you don't need either installed on your machine.

## Running it locally

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

That gives you the API on `http://127.0.0.1:8000/api/v1` and the docs on
`http://127.0.0.1:8000/docs`.

The seeder adds 5 categories, 8 suppliers, 60 products and one user to log in with:

```
admin@example.com
password
```

It is safe to run again, it will not duplicate the categories.

## Running it with Docker

```bash
docker compose up -d --build
```

nginx listens on port 8000, so the URLs above still apply. The app container waits for MySQL,
installs the dependencies if `vendor` is missing, then migrates and seeds, so the very first boot
takes a minute. Follow it with `docker compose logs -f app`.

Cache goes to Redis here, which is what the product list caching is tuned for. For a shell in the
container use `docker compose exec app sh`.

## Getting a token

Register or log in, then send the token as a bearer token on every other request.

```bash
curl -X POST http://127.0.0.1:8000/api/v1/login \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"password"}'
```

```json
{
  "token": "3|QkJ0f...",
  "user": { "id": 1, "name": "Demo Admin", "email": "admin@example.com" }
}
```

```bash
curl http://127.0.0.1:8000/api/v1/products \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer 3|QkJ0f...'
```

`POST /logout` revokes the token you used. Everything under `/api` answers in JSON whether or not
you send an `Accept` header, errors included.

## Endpoints

Everything is under `/api/v1`.

| Method | Endpoint | Notes |
| --- | --- | --- |
| POST | `/register` | public, 10 requests a minute |
| POST | `/login` | public, 10 requests a minute |
| POST | `/logout` | revokes the current token, 204 |
| GET | `/me` | the authenticated user |
| GET | `/categories` | all categories with a `products_count` |
| GET | `/suppliers` | paginated, 50 per page |
| GET | `/products` | paginated list, see the filters below |
| POST | `/products` | returns 201 |
| GET | `/products/{id}` | |
| PUT/PATCH | `/products/{id}` | partial update |
| DELETE | `/products/{id}` | soft delete, 204 |
| POST | `/products/{id}/restore` | brings a soft deleted product back |

Products are created with their suppliers in one call:

```json
{
  "category_id": 3,
  "name": "Mechanical Keyboard",
  "sku": "kbd-10293",
  "price": 249.90,
  "stock_quantity": 8,
  "suppliers": [
    { "id": 2, "unit_cost": 180.00 },
    { "id": 5, "unit_cost": 176.50 }
  ]
}
```

Sending `suppliers` on an update replaces the whole list. Leaving the key out leaves it alone.

## Filtering the product list

| Parameter | Values |
| --- | --- |
| `category_id` | an existing category id |
| `min_price` / `max_price` | numbers, inclusive |
| `stock_status` | `in_stock`, `low_stock`, `out_of_stock` |
| `search` | matches name or sku |
| `sort` | `name`, `price`, `stock_quantity`, `created_at`, each with an optional `-` prefix for descending |
| `per_page` | 1 to 100, defaults to 15 |
| `page` | page number |

```
GET /api/v1/products?category_id=3&min_price=50&stock_status=low_stock&sort=-price&per_page=25
```

Filters combine with AND and the pagination links keep the query string.

## Tests

```bash
php artisan test
php artisan test --filter=ProductFilterTest
```

The local PHP CLI must have `pdo_sqlite` enabled for the test suite. On Laragon, enable
`extension=pdo_sqlite` in the active PHP configuration and restart the terminal. Docker includes
the database services used by its app stack.

In Docker: `docker compose exec app php artisan test`.

## Notes

- Deleting is a soft delete. Deleted products drop out of the list and out of `show`, and
  `POST /products/{id}/restore` puts them back.
- The product list is cached for 5 minutes per filter combination. A version counter in the cache
  is bumped on every product write (including supplier changes), so a write makes every cached
  page stale at once rather than needing individual keys cleared.
- `stock_status` is derived from `stock_quantity`, not stored: 0 is `out_of_stock`, 1 to 10 is
  `low_stock`, anything above that is `in_stock`.
- Tests run against an in-memory SQLite database and the array cache driver (set in `phpunit.xml`),
  so they leave your seeded `database/database.sqlite` alone and need no setup.
