# My OrnitoPHP App

Created with `composer create-project ornitophp/starter`.

## Getting started

```bash
# 1. Configure your database
cp .env.example .env
# Edit .env with your MySQL credentials

# 2. Install dependencies (already done if you used create-project)
composer install

# 3. Run migrations and seed
php bin/ornito migrate
php bin/ornito db:seed

# 4. Start the dev server
php -S localhost:8000 -t public/
```

## Project structure

```
app/            Your code goes here
├── Controllers/
├── Models/
config/         Configuration files
database/       Migrations and seeders
public/         Web root (front controller)
routes/         Route definitions
views/          Templates
```

## Console commands

```bash
php bin/ornito migrate           # Run migrations
php bin/ornito db:seed           # Seed demo data
php bin/ornito db:fresh          # Drop all, migrate, seed
php bin/ornito create:model      # Generate model + migration
php bin/ornito create:controller # Generate controller
```

## Framework docs

See [ornitophp/framework](https://github.com/OrnitoPHP/framework) for full documentation.

## License

MIT
