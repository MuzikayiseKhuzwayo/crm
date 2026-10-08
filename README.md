# Laravel CRM

<p align="center">
  <img src="art/01KSMTPHKP6TR0YRTC4HRY426J.png" alt="Laravel CRM Banner" width="800" style="border-radius: 12px; box-shadow: 0 20px 40px rgba(0,0,0,0.15);">
</p>

<p align="center">
  <a href="https://github.com/MuzikayiseKhuzwayo/crm/blob/master/LICENSE.md"><img src="https://img.shields.io/badge/license-MIT-6366f1.svg?style=flat-square" alt="MIT License"></a>
  <a href="https://packagist.org/packages/venturedrake/laravel-crm"><img src="https://img.shields.io/packagist/v/venturedrake/laravel-crm.svg?style=flat-square&color=emerald" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/venturedrake/laravel-crm"><img src="https://img.shields.io/packagist/dt/venturedrake/laravel-crm.svg?style=flat-square&color=blue" alt="Total Downloads"></a>
  <img src="https://img.shields.io/badge/Laravel-11%20|%2012%20|%2013-ff2d20.svg?style=flat-square" alt="Laravel 11-13">
  <img src="https://img.shields.io/badge/PHP-8.2%20|%208.4+-777bb4.svg?style=flat-square" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/UI-Livewire%20%7C%20DaisyUI%205%20%7C%20Tailwind%204-06b6d4.svg?style=flat-square" alt="Modern UI Stack">
</p>

---

## ⚡ The Modern Open-Source CRM for Laravel

**Laravel CRM** (`venturedrake/laravel-crm`) transforms any Laravel application into an enterprise-grade Customer Relationship Management platform. Designed as an unopinionated, embeddable package, it provides complete pipeline lifecycle management, omni-channel customer chat, outbound marketing automation, and automated sales motions—with zero external microservice dependencies.

- 🖥️ **Unified Presentation Cockpit:** Reactive Livewire 3/4 components styled with Tailwind CSS v4, DaisyUI v5, and MaryUI.
- 🎯 **Full Sales Pipeline:** Leads, Kanban Boards, Deals, Quotes with digital signing, Orders, Invoices, Deliveries, and Purchase Orders.
- 💬 **Omni-Channel Customer Chat:** Embeddable cross-domain visitor widget with live WebSocket operator broadcasting.
- 📣 **Email & SMS Marketing:** Automated campaign dispatchers, rich template builders, ClickSend SMS, and open/click telemetry.
- 🔌 **Unified REST API (v2):** Token-authenticated endpoints for headless mobile and partner integrations.
- 🛡️ **Zero-Drift Enterprise Security:** Multi-tenant team isolation (`LARAVEL_CRM_TEAMS`), 41 Spatie policies, and at-rest AES-256 field encryption.

👉 **[View Interactive Product Showcase](https://github.com/MuzikayiseKhuzwayo/crm/blob/master/public/showcase.html)**

---

## 🏛️ System Architecture

Laravel CRM is engineered across a strict 3-tier boundary:

```mermaid
flowchart TD
    subgraph Layer1 ["Layer 1: Unified Presentation (Cockpit)"]
        UI["Livewire 3/4 + DaisyUI v5 + MaryUI"]
        Kanban["Drag & Drop Pipeline Kanban (SortableJS)"]
        ChatWidget["Embeddable Visitor Chat Widget (/p/chat)"]
        PublicPortal["Public Sign & Pay Portals (/p/quotes, /p/invoices)"]
    end

    subgraph Layer2 ["Layer 2: Gateway & Control Plane"]
        WebRoutes["CRM Web Router (auth.laravel-crm, crm middleware)"]
        ApiV2["Unified REST API v2 (/crm/api/v2, Sanctum tokens)"]
        Tracking["Zero-Session Tracking Pixels & Click Redirects"]
        Broadcasting["Event Broadcast Engine (Echo / Soketi / Reverb)"]
    end

    subgraph Layer3 ["Layer 3: Deterministic Execution Engine"]
        DomainServices["28+ Domain Services (Lead, Deal, Chat, Order, Playbook)"]
        ModelObservers["87 Models + 58 Observers (UUIDs & Serial Human IDs)"]
        DB[(Multi-Tenant Storage: MySQL / SQLite / PostgreSQL)]
        Schedulers["Automated Cron Dispatchers (Reminders, Campaigns, Stage Sync)"]
    end

    Layer1 --> Layer2
    Layer2 --> Layer3
```

---

## 🚀 10-Minute Quickstart

Install the package into your Laravel 11, 12, or 13 app:

```bash
composer require venturedrake/laravel-crm
```

Run the automated one-shot installer:

```bash
php artisan laravelcrm:install
```

Grant CRM access to an administrative user:

```bash
php artisan laravelcrm:add-user admin@example.com --name="System Administrator" --role="Owner"
```

Start your server and visit `/crm`:

```bash
php artisan serve
```

---

## 📚 Boundary-First Documentation (Diátaxis)

Our documentation is strictly organized according to the **Diátaxis** framework to serve every developer persona:

<div align="center">

| Quadrant | Purpose | Key Guides |
|---|---|---|
| **🎓 [Tutorials](https://github.com/MuzikayiseKhuzwayo/crm/blob/master/docs/tutorials/quickstart.md)** | Learning-oriented zero-to-one walkthroughs | • [10-Minute Zero-to-One Quickstart](https://github.com/MuzikayiseKhuzwayo/crm/blob/master/docs/tutorials/quickstart.md) |
| **🛠️ [How-To Guides](https://github.com/MuzikayiseKhuzwayo/crm/tree/master/docs/how-to)** | Problem-oriented recipes for common tasks | • [Importing Leads & Datasets](https://github.com/MuzikayiseKhuzwayo/crm/blob/master/docs/how-to/importing-leads.md)<br>• [Setting Up Real-Time Chat](https://github.com/MuzikayiseKhuzwayo/crm/blob/master/docs/how-to/setting-up-chat.md)<br>• [Email & SMS Marketing](https://github.com/MuzikayiseKhuzwayo/crm/blob/master/docs/how-to/email-and-sms-marketing.md)<br>• [Version Upgrading Guide](https://github.com/MuzikayiseKhuzwayo/crm/blob/master/docs/how-to/upgrading.md) |
| **📖 [Reference](https://github.com/MuzikayiseKhuzwayo/crm/tree/master/docs/reference)** | Machine-accurate specifications and tables | • [REST API (v2) Specification](https://github.com/MuzikayiseKhuzwayo/crm/blob/master/docs/reference/api-v2.md)<br>• [Artisan CLI Command Table](https://github.com/MuzikayiseKhuzwayo/crm/blob/master/docs/reference/cli-commands.md)<br>• [Configuration & .env Keys](https://github.com/MuzikayiseKhuzwayo/crm/blob/master/docs/reference/configuration.md) |
| **💡 [Explanation](https://github.com/MuzikayiseKhuzwayo/crm/tree/master/docs/explanation)** | Architectural narratives and system reasoning | • [Architecture & Design Philosophy](https://github.com/MuzikayiseKhuzwayo/crm/blob/master/docs/explanation/architecture-philosophy.md)<br>• [Security, Roles & Field Encryption](https://github.com/MuzikayiseKhuzwayo/crm/blob/master/docs/explanation/security-and-encryption.md)<br>• [Production Scaling & High-Throughput](https://github.com/MuzikayiseKhuzwayo/crm/blob/master/docs/explanation/scaling-and-deployment.md) |

</div>

---

## ⚡ Unified REST API (v2)

Headless applications and external integrations interact with the unified API under `/crm/api/v2`:

```bash
# Issue an API token
curl -X POST https://your-domain.com/crm/api/v2/auth/token \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"secret","device_name":"Mobile"}'

# Fetch leads with sorting and relationships
curl -X GET "https://your-domain.com/crm/api/v2/leads?sort=-amount" \
  -H "Authorization: Bearer <token>" \
  -H "Accept: application/json"
```

---

## 🧪 Testing & Code Standards

Laravel CRM maintains a rigorous testbench test suite:

```bash
# Run test suite (Pest)
composer test

# Check code formatting (Laravel Pint)
composer format-test

# Compile frontend assets
npm run build
```

---

## 📄 License

Laravel CRM is open-sourced software licensed under the [MIT license](https://github.com/MuzikayiseKhuzwayo/crm/blob/master/LICENSE.md).