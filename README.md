# E-commerce Shopping Cart System

A simple e-commerce shopping cart application built with Laravel and React (via Inertia.js).

## Features

- **User Authentication**: Built-in authentication using Laravel Breeze with React
- **Product Browsing**: Users can view available products with details (name, price, stock)
- **Shopping Cart**: 
  - Add products to cart
  - Update quantities
  - Remove items
  - Cart persists per authenticated user (stored in database)
- **Low Stock Notifications**: Automated email alerts to admin when products run low on stock
- **Daily Sales Reports**: Scheduled job that sends daily sales summary to admin email
- **Stock Management**: Real-time stock tracking and validation

## Tech Stack

- **Backend**: Laravel 12
- **Frontend**: React with Inertia.js
- **Styling**: Tailwind CSS
- **Database**: SQLite (default) / MySQL / PostgreSQL
- **Queue**: Laravel Queue for job processing
- **Scheduler**: Laravel Task Scheduling

## Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd shopping-cart
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node dependencies**
   ```bash
   npm install
   ```

4. **Set up environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure database** (in `.env` file)
   ```
   DB_CONNECTION=sqlite
   # OR for MySQL
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=shopping_cart
   DB_USERNAME=root
   DB_PASSWORD=
   ```

6. **Configure mail** (in `.env` file)
   
   For local development with MailHog or Mailpit:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=127.0.0.1
   MAIL_PORT=1025
   MAIL_USERNAME=null
   MAIL_PASSWORD=null
   MAIL_ENCRYPTION=null
   MAIL_FROM_ADDRESS="hello@example.com"
   MAIL_FROM_NAME="${APP_NAME}"
   ```
   
   Or use the log driver for simple testing:
   ```
   MAIL_MAILER=log
   ```
   
   See the [Mail Testing](#mail-testing) section for detailed setup instructions.

7. **Run migrations and seeders**
   ```bash
   php artisan migrate:fresh --seed
   ```

8. **Build frontend assets**
   ```bash
   npm run build
   # OR for development
   npm run dev
   ```

## Running the Application

### Development Mode

You can use Laravel's convenient dev script that runs multiple services:

```bash
composer dev
```

This will start:
- Laravel development server (port 8000)
- Queue worker
- Log viewer (Pail)
- Vite dev server

### Manual Start

Alternatively, run each service in separate terminals:

1. **Start the development server**
   ```bash
   php artisan serve
   ```

2. **Start the queue worker** (for low stock notifications)
   ```bash
   php artisan queue:work
   ```

3. **Start Vite dev server** (for hot reload)
   ```bash
   npm run dev
   ```

4. **Run the scheduler** (for daily sales reports)
   ```bash
   php artisan schedule:work
   ```
   
   OR add to crontab for production:
   ```bash
   * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
   ```

## Default Credentials

### Admin User
- **Email**: admin@example.com
- **Password**: password

### Test User
- **Email**: test@example.com
- **Password**: password

## Database Schema

### Tables

1. **users** - User authentication and profiles
   - `is_admin` - Flag to identify admin users

2. **products** - Product catalog
   - `name`, `description`, `price`, `stock_quantity`, `image_url`

3. **cart_items** - Shopping cart items per user
   - Foreign keys: `user_id`, `product_id`
   - Unique constraint on `(user_id, product_id)`

4. **orders** - Completed orders
   - Foreign key: `user_id`
   - `total_amount`, `status`

5. **order_items** - Items in each order
   - Foreign keys: `order_id`, `product_id`
   - `quantity`, `price` (stored at time of purchase)

## Key Features Implementation

### Low Stock Notification

When a product's stock falls to 10 or below after checkout, a job is dispatched to the queue:

```php
if ($product->isLowStock()) {
    LowStockNotification::dispatch($product);
}
```

The job sends an email to the admin user with product details.

### Daily Sales Report

Configured to run daily at 6:00 PM (18:00) in `routes/console.php`:

```php
Schedule::job(new DailySalesReport())->dailyAt('18:00');
```

The report includes:
- Total orders for the day
- Total revenue
- Products sold with quantities and revenue per product

### Cart Management

Cart items are stored in the database and associated with authenticated users:
- Cart persists across sessions
- Stock validation on add/update
- Automatic cart clearing after checkout
- Real-time stock updates

## API Routes

All routes require authentication:

### Products
- `GET /products` - List all products
- `GET /products/{product}` - View single product

### Cart
- `GET /cart` - View cart
- `POST /cart/add` - Add item to cart
- `PATCH /cart/{cartItem}` - Update cart item quantity
- `DELETE /cart/{cartItem}` - Remove item from cart
- `POST /cart/checkout` - Complete purchase

## Testing the Application

1. **Register a new user** or login with test credentials
2. **Browse products** on the products page
3. **Add items to cart** with desired quantities
4. **View cart** and update quantities or remove items
5. **Checkout** to complete the order
6. **Check low stock alerts** - If any product drops to 10 or below, check your mail testing tool (see [Mail Testing](#mail-testing))
7. **Test daily report** - See [Mail Testing](#mail-testing) section for instructions

## Mail Testing

The application sends emails for:
- **Low Stock Alerts**: When product stock falls to 10 or below
- **Daily Sales Reports**: Automated daily summary at 6:00 PM

### Option 1: MailHog (Recommended for Local Testing)

MailHog is a lightweight email testing tool with a web interface.

#### Installation

**macOS (via Homebrew):**
```bash
brew install mailhog
```

**Linux:**
```bash
# Download binary
wget https://github.com/mailhog/MailHog/releases/download/v1.0.1/MailHog_linux_amd64
chmod +x MailHog_linux_amd64
sudo mv MailHog_linux_amd64 /usr/local/bin/mailhog
```

**Windows:**
Download from [MailHog Releases](https://github.com/mailhog/MailHog/releases)

#### Running MailHog

Start MailHog in a separate terminal:
```bash
mailhog
```

MailHog will start:
- **SMTP Server**: `localhost:1025`
- **Web UI**: `http://localhost:8025`

#### Configure Laravel for MailHog

Update your `.env` file:
```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Now all emails will be caught by MailHog and viewable at `http://localhost:8025`

### Option 2: Mailpit

Mailpit is a modern alternative to MailHog (included with Laravel Sail).

#### Installation

**macOS (via Homebrew):**
```bash
brew install mailpit
```

**Other platforms:**
See [Mailpit Installation Guide](https://github.com/axllent/mailpit)

#### Running Mailpit

```bash
mailpit
```

#### Configure Laravel for Mailpit

Update your `.env` file:
```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Access the web UI at `http://localhost:8025`

### Option 3: Log Driver (Simple Testing)

For quick testing without additional tools, use Laravel's log driver:

```env
MAIL_MAILER=log
```

Emails will be logged to `storage/logs/laravel.log`

### Testing Email Features

#### Test Low Stock Alert

1. Start MailHog/Mailpit (or configure log driver)
2. Make sure queue worker is running: `php artisan queue:work`
3. Place an order that reduces product stock to 10 or below
4. Check MailHog/Mailpit web interface or log file for the alert email

#### Test Daily Sales Report

Manually trigger the report:
```bash
php artisan tinker
>>> App\Jobs\DailySalesReport::dispatch();
```

Or wait for the scheduled time (6:00 PM daily) with scheduler running:
```bash
php artisan schedule:work
```

## Queue Configuration

The default queue connection is `database`. Jobs are stored in the `jobs` table.

For production, consider using Redis:

```env
QUEUE_CONNECTION=redis
```

## Production Deployment

1. Set `APP_ENV=production` in `.env`
2. Run `php artisan config:cache`
3. Run `php artisan route:cache`
4. Run `php artisan view:cache`
5. Set up proper queue worker (Supervisor recommended)
6. Set up cron job for scheduler
7. Use a proper mail service (SendGrid, Mailgun, SES, etc.)

## License

This project is open-sourced software licensed under the MIT license.
