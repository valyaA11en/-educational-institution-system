<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushNotificationService
{
    private ?WebPush $webPush = null;

    public function __construct()
    {
        $vapidPublicKey = config('services.webpush.vapid_public_key');
        $vapidPrivateKey = config('services.webpush.vapid_private_key');
        $vapidSubject = config('services.webpush.vapid_subject', config('app.url'));

        if ($vapidPublicKey && $vapidPrivateKey) {
            $this->webPush = new WebPush([
                'VAPID' => [
                    'subject' => $vapidSubject,
                    'publicKey' => $vapidPublicKey,
                    'privateKey' => $vapidPrivateKey,
                ],
            ]);
        }
    }

    public function sendToUser(int $userId, array $payload): void
    {
        if (!$this->webPush) {
            Log::warning('WebPush not configured, skipping push notification');
            return;
        }

        $subscriptions = PushSubscription::where('user_id', $userId)->get();

        foreach ($subscriptions as $subscription) {
            try {
                $this->sendToSubscription($subscription, $payload);
            } catch (\Exception $e) {
                Log::error('Failed to send push notification', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);

                // If subscription is invalid, remove it
                if (str_contains($e->getMessage(), '410') || str_contains($e->getMessage(), 'expired')) {
                    $subscription->delete();
                }
            }
        }
    }

    public function sendToSubscription(PushSubscription $subscription, array $payload): void
    {
        if (!$this->webPush) {
            return;
        }

        $keys = $subscription->keys_json ?? [];
        
        if (empty($keys['p256dh']) || empty($keys['auth'])) {
            Log::warning('Invalid push subscription keys', [
                'subscription_id' => $subscription->id,
            ]);
            return;
        }

        $pushSubscription = Subscription::create([
            'endpoint' => $subscription->endpoint,
            'keys' => [
                'p256dh' => $keys['p256dh'],
                'auth' => $keys['auth'],
            ],
            'contentEncoding' => 'aesgcm',
        ]);

        $this->webPush->queueNotification(
            $pushSubscription,
            json_encode($payload)
        );

        $results = $this->webPush->flush();

        foreach ($results as $result) {
            if (!$result->isSuccess()) {
                throw new \Exception($result->getReason());
            }
        }

        // TODO: Add last_notified_at field to push_subscriptions table if needed
        // $subscription->update(['last_notified_at' => now()]);
    }

    public function sendToAll(array $payload, ?array $userIds = null): void
    {
        if (!$this->webPush) {
            return;
        }

        $query = PushSubscription::query();
        if ($userIds) {
            $query->whereIn('user_id', $userIds);
        }

        $subscriptions = $query->get();

        foreach ($subscriptions as $subscription) {
            try {
                $this->sendToSubscription($subscription, $payload);
            } catch (\Exception $e) {
                Log::error('Failed to send push notification', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);

                if (str_contains($e->getMessage(), '410') || str_contains($e->getMessage(), 'expired')) {
                    $subscription->delete();
                }
            }
        }
    }
}


