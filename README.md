# FatherShops API (Laravel)

Backend API for the FatherShops technical task. It fetches data from the
FatherStock CDN and returns clean JSON for the Next.js frontend.
No database is used.

## Requirements
- PHP 8.2+
- Composer

## Setup
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```
The API runs at `http://localhost:8000`.

## Endpoints
| Method | URL | Description |
|---|---|---|
| GET | /api/categories | All categories with images |
| GET | /api/categories/{id}/products | Products of a category |
| GET | /api/products/{id} | Full product details |

If the data is missing or the external request fails, the API returns
`404` with a JSON message, for example `{"message": "Product not found"}`.

## Notes
- Responses are cached for 10 minutes (file cache) to reduce external calls.
- Duplicate products from the external API are removed.
- Product descriptions are converted to plain text for safety.
- IDs must be numeric, otherwise the route returns 404.
- Logic lives in `app/Services/FatherStockService.php`.