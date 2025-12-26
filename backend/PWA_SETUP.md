# PWA + Push Notifications Setup

## Backend Setup

### 1. Install Web Push Package

```bash
composer require minishlink/web-push
```

### 2. Generate VAPID Keys

You can generate VAPID keys using the web-push library:

```php
php artisan tinker
```

```php
use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();
echo "Public Key: " . $keys['publicKey'] . "\n";
echo "Private Key: " . $keys['privateKey'] . "\n";
```

Or use an online tool: https://web-push-codelab.glitch.me/

### 3. Add to .env

```env
VAPID_PUBLIC_KEY=your_public_key_here
VAPID_PRIVATE_KEY=your_private_key_here
VAPID_SUBJECT=mailto:your-email@example.com
```

### 4. Run Migration

```bash
php artisan migrate
```

## Frontend Setup

### 1. Add VAPID Public Key to .env

```env
VITE_VAPID_PUBLIC_KEY=your_public_key_here
```

### 2. Build Icons

Create PWA icons in `frontend/public/icons/`:
- icon-72x72.png
- icon-96x96.png
- icon-128x128.png
- icon-144x144.png
- icon-152x152.png
- icon-192x192.png
- icon-384x384.png
- icon-512x512.png

### 3. Build for Production

```bash
npm run build
```

The service worker will be automatically registered in production mode.

## Usage

### Sending Push Notifications from Backend

```php
use App\Services\Push\PushNotificationService;

$pushService = app(PushNotificationService::class);

// Send to specific user
$pushService->sendToUser($userId, [
    'title' => 'Новое уведомление',
    'body' => 'У вас новое сообщение',
    'url' => '/notifications',
    'tag' => 'notification',
    'data' => [
        'notificationId' => 123,
    ],
]);

// Send to multiple users
$pushService->sendToUsers([1, 2, 3], [
    'title' => 'Объявление',
    'body' => 'Важное объявление для всех',
]);

// Flush queued notifications
$pushService->flush();
```

### Frontend Integration

Push notifications are automatically subscribed after login. To manually manage:

```typescript
import { usePWA } from '@/composables/usePWA'

const pwa = usePWA()

// Subscribe
await pwa.subscribeToPush()

// Unsubscribe
await pwa.unsubscribeFromPush()

// Install PWA
await pwa.installPWA()
```

## Testing

1. Build the frontend: `npm run build`
2. Serve with HTTPS (required for push notifications)
3. Open in browser and allow notifications
4. Test push notification from backend

## Notes

- Push notifications require HTTPS (except localhost)
- Service Worker only works in production build
- Users must grant notification permission
- Invalid subscriptions are automatically cleaned up

