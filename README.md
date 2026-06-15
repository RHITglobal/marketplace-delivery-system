# Marketplace Delivery System (Laravel 11 Scaffold)

Production-oriented Laravel scaffold for an online marketplace and delivery platform with REST APIs, RBAC, JWT auth, ordering, delivery tracking, and admin operations.

## Tech Stack
- PHP 8.2+
- Laravel 11 project structure
- RESTful API (MVC)
- Eloquent ORM + migrations/seeders
- JWT-based stateless auth (custom service)

## Implemented Modules
- **Authentication & User Management**: registration/login, JWT token issuance, profile APIs, password reset flow, role model (`customer`, `vendor`, `delivery_agent`, `admin`)
- **Product Management**: categories, product CRUD, vendor inventory, product image URLs, search/filtering
- **Shopping & Orders**: cart APIs, order placement, order items, order history/status, payment stubs, invoice payload endpoint
- **Delivery Management**: agent assignment, delivery status updates, tracking endpoint, delivery metrics
- **Admin Dashboard**: user/vendor management, vendor verification, analytics, revenue and settings APIs
- **Supporting Components**: reviews/ratings, vendor commissions, CORS config, API throttling, seed data, mail notification hook on order confirmation

## API Base Path
`/api/v1`

## Key Route Groups
- `POST /auth/register`, `POST /auth/login`, `POST /auth/forgot-password`, `POST /auth/reset-password`
- `GET /catalog/categories`, `GET /catalog/products`, `GET /catalog/products/{product}`
- Authenticated: profile, cart, orders, payments, reviews, delivery tracking
- Role-protected:
  - `role:vendor,admin` → vendor product/order management
  - `role:delivery_agent,admin` → delivery execution and metrics
  - `role:admin` → dashboard, users/vendors, settings, category management, delivery assignment

## Project Structure Highlights
- `app/Http/Controllers/Api/*` → modular API controllers by domain
- `app/Http/Middleware/JwtAuthenticate.php` → JWT auth middleware
- `app/Http/Middleware/EnsureUserHasRole.php` → RBAC middleware
- `app/Services/JwtService.php` → token generation/validation
- `app/Models/*` → marketplace entities
- `database/migrations/*` → normalized schema for users/products/orders/deliveries/payments/reviews/commissions/settings
- `database/seeders/DatabaseSeeder.php` → sample admin/vendor/customer/agent data
- `routes/api.php` → versioned REST API routes

## Local Setup
1. Copy environment file:
   ```bash
   cp .env.example .env
   ```
2. Set required values in `.env`:
   - `APP_KEY` (generate via artisan once dependencies are installed)
   - `JWT_SECRET`
   - DB credentials
   - mail settings (for password reset/order email)
3. Install dependencies and run migrations/seed:
   ```bash
   composer install
   php artisan key:generate
   php artisan migrate --seed
   ```
4. Start server:
   ```bash
   php artisan serve
   ```

## Seeded Accounts (password: `password`)
- Admin: `admin@marketplace.test`
- Vendor: `vendor@marketplace.test`
- Customer: `customer@marketplace.test`
- Delivery Agent: `agent@marketplace.test`

## Notes
- API is scaffolded and ready for deeper business integrations (real payment gateway, route optimization, websocket tracking, file upload storage).
- JWT is implemented without third-party auth packages to keep the scaffold lightweight and dependency-safe.
