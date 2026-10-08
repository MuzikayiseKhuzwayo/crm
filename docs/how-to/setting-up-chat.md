# How-To: Setting Up Real-Time Customer Chat

Laravel CRM includes an integrated omni-channel customer support chat module with embeddable visitor widget, real-time operator alerts, and optional WebSocket streaming.

---

## 1. Enabling the Chat Module

In your `config/laravel-crm.php`, ensure `'chat'` is listed in the `modules` array:

```php
'modules' => [
    'leads',
    'deals',
    'quotes',
    'chat',
    // ...
],
```

---

## 2. Generating a Chat Widget

1. Navigate to **Settings** > **Chat Widgets** in the CRM cockpit (`/crm/settings/chat-widgets`).
2. Click **Create Widget**.
3. Configure the appearance:
   - **Widget Name**: e.g., `Marketing Website Widget`
   - **Primary Color**: Hex color code (e.g., `#4f46e5`)
   - **Greeting Message**: Default message shown to visitors
   - **Operator Assignment**: Default user or team to receive inbound chats
4. Save the widget. The system generates a unique `public_key`.

---

## 3. Embedding on Your Website

Add the embed script to your external website before the closing `</body>` tag:

```html
<script 
    src="https://your-crm-domain.com/p/chat/{PUBLIC_KEY}.js" 
    async 
    defer>
</script>
```

Replace `{PUBLIC_KEY}` with your widget's public key. The script injects an isolated iframe loading `/p/chat/{PUBLIC_KEY}` which operates cross-origin without requiring cookies or triggering CSRF errors.

---

## 4. Real-Time Broadcast Configuration (Optional)

By default, the chat inbox operates with responsive polling. To enable instant WebSocket message delivery:

1. Configure your Laravel broadcasting driver (Pusher, Soketi, or Reverb) in `.env`:
   ```env
   BROADCAST_CONNECTION=reverb
   ```
2. When messages are sent, the `VentureDrake\LaravelCrm\Events\ChatMessageSent` event broadcasts on public channel `crm-chat.{conversation_external_id}`.
3. Operators in `/crm/chat` receive live notifications and sound alerts when new visitor sessions are initiated.
