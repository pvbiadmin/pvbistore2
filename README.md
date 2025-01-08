<p style="text-align: center;"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p style="text-align: center;">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 2000 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Project Installation Guide

This guide will help you set up the Laravel project using Laragon on Windows.

### Prerequisites
- [Laragon](https://laragon.org/download/) installed
- [Git](https://git-scm.com/downloads) installed
- [Composer](https://getcomposer.org/download/) installed
- [Node.js](https://nodejs.org/) installed (for frontend assets)

### Installation Steps

#### 1. Clone the Repository
```bash
# Navigate to Laragon's www directory
cd C:\laragon\www

# Clone your Git repository
git clone <your-repository-url> project-name

# Navigate to project directory
cd project-name
```

#### 2. Install Dependencies
```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies (if using frontend assets)
npm install
```

#### 3. Configure Environment
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

#### 4. Database Setup
1. Open Laragon database manager (HeidiSQL)
2. Create your database
3. Update `.env` file with your database credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=root
DB_PASSWORD=
```

#### 5. Run Migrations and Seeding
```bash
php artisan migrate
php artisan db:seed
```

#### 6. Build Frontend Assets
```bash
# If using Vite
npm run build
```

#### 7. Configure Virtual Host
1. Right-click Laragon tray icon
2. Navigate to Apache → Sites
3. Add "project-name.test"

#### 8. Clear Application Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

#### 9. Set Permissions
```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

#### 10. Final Step
1. Right-click Laragon tray icon
2. Select 'Restart'

### Additional Notes
- Make sure all prerequisites are properly installed before starting the installation process
- The project should now be accessible at `http://project-name.test`
- If you encounter any issues, check Laragon's error logs

### Troubleshooting
If you encounter any issues during installation, try these steps:
1. Ensure all required services (Apache, MySQL) are running in Laragon
2. Verify that all prerequisites are properly installed
3. Check the Laravel error logs in `storage/logs`

### Git Repository
1. Initialize a Git repository in the folder:
```bash
git init
```
2. Link your local repository to the remote GitHub repository:
```bash
git remote add origin <your-repository-url>
```
3. Verify the remote repository was added:
```bash
git remote -v
```
You should see:
```
origin  https://github.com/b2pholders/b2pholders.github.io.git (fetch)
origin  https://github.com/b2pholders/b2pholders.github.io.git (push)
```
4. Stage all the files in your folder:
```bash
git add .
```
5. Commit the files with a message:
```bash
git commit -m "Initial commit"
```
6. Push your local files to the remote repository:
```bash
git push -u origin main
```
- If you’re using an older repository, the default branch might be `master` instead of `main`. Use `git branch` to check your current branch and adjust the command accordingly.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
