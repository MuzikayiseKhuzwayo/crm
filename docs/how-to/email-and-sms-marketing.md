# How-To: Email & SMS Marketing Automation

Laravel CRM provides marketing campaign management, rich template builders, automated schedulers, and delivery tracking for both Email and SMS.

---

## 1. Prerequisites & Credentials

### Email
Configure your host application's mail settings in `.env` (`MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, etc.).

### SMS (ClickSend)
Laravel CRM integrates natively with [ClickSend](https://www.clicksend.com/) for high-deliverability SMS dispatch.
1. In the CRM cockpit, go to **Settings** > **Integrations** > **ClickSend**.
2. Enter your **ClickSend Username**, **API Key**, and optional **Default From** phone number or alphanumeric sender ID.
3. Click **Save Settings**. ClickSend connectivity is verified automatically.

---

## 2. Creating Reusable Templates

1. Navigate to **Email Templates** or **SMS Templates**.
2. Click **New Template**.
3. Use personalization merge tags in your subject and content:
   - `{{first_name}}`
   - `{{last_name}}`
   - `{{organization}}`
   - `{{email}}`
4. The Email Template editor includes rich-text formatting powered by TinyMCE.

---

## 3. Creating and Launching a Campaign

1. Navigate to **Email Campaigns** or **SMS Campaigns**.
2. Click **New Campaign**.
3. Select your audience (e.g. all Leads in stage "Cold Prospect", or a filtered tag).
4. Choose an existing template or author the message inline.
5. Set the **Scheduled At** timestamp or choose **Send Immediately**.
6. Save and approve the campaign.

---

## 4. Background Schedulers & Queue Workers

Ensure your Laravel scheduler (`php artisan schedule:run`) is running via cron:

```cron
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

The CRM registers two automated workers in the scheduler:
- `laravelcrm:email-campaigns-dispatch` (every minute)
- `laravelcrm:sms-campaigns-dispatch` (every minute)

To run the workers manually for testing:
```bash
php artisan laravelcrm:email-campaigns-dispatch
php artisan laravelcrm:sms-campaigns-dispatch
```

---

## 5. Delivery & Click Tracking

- **Open Tracking**: An invisible 1x1 transparent GIF (`/p/email/o/{token}.gif`) tracks opens when loaded in recipient email clients.
- **Link Clicks**: Outbound URLs in emails (`/p/email/c/{token}`) and SMS (`/p/sms/c/{token}`) record engagement telemetry and redirect to destination URLs.
- **Analytics Dashboard**: View aggregate open rates, click rates, and individual recipient timelines in the campaign show view.
