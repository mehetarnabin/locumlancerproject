```markdown
# Symfony Project Setup Guide

This is a Symfony 7.1 based project with several integrations like Doctrine ORM, JWT Auth, Stripe, Sentry, CKEditor, OAuth, and more.

---

## Requirements

- PHP >= 8.2
- Composer
- Symfony CLI (Optional but recommended)
- MySQL 

---

## Installation Steps

### 1. Clone the Repository

```bash
git clone git@gitlab.com:anubhabi/locumlancer.git
cd locumlancer
```

---

### 2. Install PHP Dependencies

```bash
composer install
```

---

### 3. Configure Environment Variables

Copy `.env` to `.env.local` and configure your environment variables.

```bash
cp .env .env.local
```

Update your database credentials:

```
DATABASE_URL="mysql://db_user:db_password@127.0.0.1:3306/db_name?serverVersion=8.0"
```

Configure mailer if needed (Brevo Mailer):

```
MAILER_DSN=brevo+smtp://API_KEY@default
```

---

### 4. Database Setup

Run migrations:

```bash
php bin/console doctrine:migrations:migrate
```

---

### 5. Run Symfony Server

```bash
symfony server:start --no-tls
```

Access the project:

```
http://127.0.0.1:8000
```

---

## Useful Commands

| Command | Description |
|---------|-------------|
| `php bin/console cache:clear` | Clear cache |
| `php bin/console doctrine:migrations:migrate` | Run database migrations |
| `php bin/console debug:router` | Show available routes |
| `php bin/console messenger:consume` | Start Messenger worker |

---

## Installed Packages & Bundles Highlights

- Doctrine ORM & Migrations
- StofDoctrineExtensionsBundle (Sluggable, Timestampable etc.)
- JWT Authentication (firebase/php-jwt)
- Sentry for error monitoring
- Stripe payment integration
- HWIOAuthBundle for social login
- CKEditor for rich text editing
- Datatables Bundle for backend tables
- Pagerfanta for pagination

---

## Troubleshooting

- Clear Cache:
```bash
php bin/console cache:clear
```

- Check Logs:
```bash
var/log/
```

---

