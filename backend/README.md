📦 Backend Installation (Laravel)
1. Clone the repository
git clone https://your-backend-repo.git
cd backend

2. Install dependencies
composer install

3. Copy environment file
cp .env.example .env

4. Configure .env

Update database settings, CORS, logging, mail, queue, etc.

Example:

APP_NAME=Inventory
APP_ENV=local
APP_KEY=base64:xxxxxxx
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inventory
DB_USERNAME=root
DB_PASSWORD=

JWT_SECRET=your_secret_here

5. Generate Application Key
php artisan key:generate

6. Generate JWT Secret
php artisan jwt:secret

7. Run migrations & seeders
php artisan migrate --seed

8. Start the development server
php artisan serve
9. Clear Catch
php artisan optimize:clear



## web socket 

# Terminal 2 — Reverb WebSocket server
php artisan reverb:start

# Terminal 3 — Queue worker
php artisan queue:work
