# Tutorial: 10-Minute Zero-to-One Quickstart

Welcome to **Laravel CRM**! This tutorial walks you through installing Laravel CRM into a fresh or existing Laravel 11, 12, or 13 application and getting your first customer relationship pipeline operational in under 10 minutes.

---

## 1. Prerequisites

Before starting, ensure your system has:
- **PHP**: 8.2 or higher (8.4+ recommended) with `pdo_sqlite`, `bcmath`, `fileinfo`, `gd`, `zip` extensions enabled
- **Composer**: 2.x
- **Node.js & npm**: Node 18+ and npm 9+
- A running Laravel 11, 12, or 13 application

---

## 2. Install the Package

Require `venturedrake/laravel-crm` via Composer:

```bash
composer require venturedrake/laravel-crm
```

The service provider (`VentureDrake\LaravelCrm\LaravelCrmServiceProvider`) is auto-discovered by Laravel.

---

## 3. Run the Automated Installer

Laravel CRM provides a one-shot installer that publishes assets, runs database migrations, seeds default roles, permissions, lead sources, and pipeline stages:

```bash
php artisan laravelcrm:install
```

You will be prompted to confirm the installation. Once finished, you will see a success confirmation and a list of seeded configurations.

---

## 4. Grant CRM Access to a User

Ensure your host app `App\Models\User` model uses the `HasCrmAccess` trait:

```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use VentureDrake\LaravelCrm\Traits\HasCrmAccess;

class User extends Authenticatable
{
    use HasCrmAccess;
    // ...
}
```

Now, provision or upgrade your user to have CRM Owner permissions:

```bash
php artisan laravelcrm:add-user admin@example.com --name="System Admin" --role="Owner"
```

---

## 5. Access the Cockpit

Start your Laravel development server:

```bash
php artisan serve
```

In your browser, navigate to:
```
http://localhost:8000/crm
```

Log in with your user credentials. You will be greeted by the **Laravel CRM Dashboard Cockpit**, displaying your pipeline overview, activity feed, and metrics.

---

## 6. Create Your First Lead

1. In the sidebar, click **Leads**.
2. Click **New Lead** in the top right.
3. Enter the lead title (e.g. `Acme Corp - Enterprise Cloud License`), expected deal value, contact name, and email.
4. Click **Save Lead**.
5. Switch to the **Board View** to see your lead on the interactive Kanban board. Drag the card between stages (`New`, `Contacted`, `Qualified`) to trigger stage transition tracking.

---

## 7. Next Steps

Now that your base CRM is operational:
- [Read the API Reference](../reference/api-v2.md) to integrate headless applications.
- [Configure the Chat Widget](../how-to/setting-up-chat.md) to receive visitor chats on your website.
- [Set up Email & SMS Marketing](../how-to/email-and-sms-marketing.md) for outbound sequences.
