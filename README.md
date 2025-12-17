# Limit Order Exchange Mini-Engine

A real-time limit order exchange trading engine built with Laravel 12 and Vue 3. This application simulates a cryptocurrency exchange where users can place buy/sell limit orders, which are matched automatically by the matching engine.

## Features

- **Limit Order Trading** - Place buy/sell limit orders with price and quantity
- **Real-time Order Matching** - Automatic order matching engine with price-time priority
- **Live Updates** - WebSocket-powered real-time updates for orders, trades, and balances
- **Order Book** - Live order book visualization with bid/ask spread
- **User Authentication** - Secure authentication with Laravel Fortify & Sanctum
- **Portfolio Management** - Track your assets (USD, BTC) and trading history

## Tech Stack

### Backend
- **Framework**: [Laravel 12](https://laravel.com/) (PHP 8.2+)
- **Authentication**: [Laravel Fortify](https://laravel.com/docs/fortify) + [Sanctum](https://laravel.com/docs/sanctum)
- **Real-time**: [Laravel Reverb](https://laravel.com/docs/reverb) (WebSockets)
- **Database**: SQLite (default) / MySQL / PostgreSQL
- **Queue**: Database driver (for async order processing)
- **Testing**: [Pest PHP](https://pestphp.com/)

### Frontend  
- **Framework**: [Vue 3](https://vuejs.org/) with Composition API
- **Build Tool**: [Vite](https://vitejs.dev/)
- **Server-side Rendering**: [Inertia.js](https://inertiajs.com/)
- **Styling**: [Tailwind CSS 4](https://tailwindcss.com/)
- **Components**: [Reka UI](https://reka-ui.com/) (Headless Components)
- **Icons**: [Lucide Vue](https://lucide.dev/)
- **Real-time Client**: [Laravel Echo](https://laravel.com/docs/broadcasting#client-side-installation) + Pusher.js

## Prerequisites

- **PHP** >= 8.2 with extensions: BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML
- **Composer** >= 2.x
- **Node.js** >= 18.x
- **npm** >= 9.x

## Installation

### 1. Clone the repository

```bash
git clone git@github.com:yogesh16/limit-order-exchange-mini-engine.git
cd limit-order-exchange-mini-engine
```

### 2. Install dependencies

```bash
composer install
npm install
```

### 3. Environment setup

Copy the example environment file:

```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

### 4. Database setup

Create the SQLite database file:

```bash
touch database/database.sqlite
```

Run migrations:

```bash
php artisan migrate
```

### 5. (Optional) Seed sample data

```bash
php artisan db:seed
```

This creates test users and sample orders in the order book.

## Environment Configuration

Key environment variables in `.env`:

```env
# Application
APP_NAME="Limit Order Exchange"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database (SQLite by default)
DB_CONNECTION=sqlite

# Queue (required for order processing)
QUEUE_CONNECTION=database

# WebSocket (Laravel Reverb)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=1
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

# Vite Reverb config
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

## Running the Application

### Development Server (Recommended)

Run all services concurrently with a single command:

```bash
composer dev
```

This starts:
- **Laravel Server** at `http://localhost:8000`
- **Queue Worker** for processing orders
- **Vite Dev Server** for hot module replacement

### Manual Startup

Alternatively, run each service separately:

```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Queue worker (required for order matching)
php artisan queue:listen --tries=1

# Terminal 3: Vite dev server
npm run dev

# Terminal 4: Reverb WebSocket server (for real-time updates)
php artisan reverb:start
```

Then visit `http://localhost:8000`

## Running Tests

### All tests
```bash
composer test
```

Or directly with Pest:
```bash
php artisan test
```

### Run specific test files
```bash
php artisan test tests/Feature/Order/OrderPlacementTest.php
```

### Run with coverage
```bash
php artisan test --coverage
```

## API Endpoints

All API routes are prefixed with `/api`. Authentication is handled via Laravel Sanctum.

### Profile

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/profile` | ✅ | Get current user profile with balances |

### Orders

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/orders` | ❌ | Get order book (all open orders) |
| `POST` | `/api/orders` | ✅ | Place a new limit order |
| `POST` | `/api/orders/{id}/cancel` | ✅ | Cancel an open order |

### Order Request Body

```json
{
  "side": "buy",
  "price": "50000.00",
  "quantity": "0.5"
}
```

**Parameters:**
- `side` - Order side: `buy` or `sell`
- `price` - Limit price (decimal string)
- `quantity` - Order quantity (decimal string)

### Order Response

```json
{
  "id": 1,
  "user_id": 1,
  "side": "buy",
  "price": "50000.00",
  "quantity": "0.50000000",
  "filled_amount": "0.00000000",
  "status": "open",
  "created_at": "2025-12-17T10:30:00.000000Z"
}
```

## WebSocket Events

Real-time updates are broadcast on the following channels:

| Channel | Event | Description |
|---------|-------|-------------|
| `orders` | `OrderUpdated` | Order book changes (new orders, fills, cancellations) |
| `private-user.{id}` | `BalanceUpdated` | User balance/asset updates |

## Project Structure

```
├── app/
│   ├── Actions/           # Single-purpose action classes
│   ├── Enums/             # OrderSide, OrderStatus enums
│   ├── Events/            # Broadcast events
│   ├── Http/Controllers/  # API & Web controllers
│   ├── Models/            # Eloquent models (User, Order, Trade, Asset)
│   └── Services/          # Business logic
│       ├── MatchingEngineService.php  # Order matching engine
│       └── OrderService.php           # Order CRUD operations
├── database/
│   ├── factories/         # Model factories for testing
│   ├── migrations/        # Database schema
│   └── seeders/           # Sample data seeders
├── resources/
│   ├── js/                # Vue components & pages
│   │   ├── components/    # Reusable UI components
│   │   ├── layouts/       # Page layouts
│   │   └── pages/         # Inertia pages (Dashboard, Trading)
│   └── views/             # Blade templates
├── routes/
│   ├── api.php            # API routes
│   ├── web.php            # Web routes
│   └── channels.php       # Broadcast channels
└── tests/
    ├── Feature/           # Feature tests
    │   ├── Order/         # Order placement, matching, cancellation
    │   └── Auth/          # Authentication tests
    └── Unit/              # Unit tests
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Run tests (`composer test`)
4. Commit your changes (`git commit -m 'Add amazing feature'`)
5. Push to the branch (`git push origin feature/amazing-feature`)
6. Open a Pull Request

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
