<?php

namespace App\Services;

use App\Contracts\SmsGateway;
use App\Models\AppNotification;
use App\Models\User;

class NotificationService
{
    public function __construct(private SmsGateway $sms) {}

    /** إشعار داخل التطبيق؛ يحترم تفضيلات المستخدم (orders|price|stock|offers) */
    public function push(User $user, string $type, string $title, ?string $body = null, array $data = []): ?AppNotification
    {
        $key = ['order' => 'orders', 'price' => 'price', 'stock' => 'stock', 'offer' => 'offers'][$type] ?? null;
        $prefs = $user->prefs();
        if ($key && ($prefs[$key] ?? true) === false) {
            return null;
        }
        // TODO: عند ربط FCM أرسل إشعار Push لكل صف في device_tokens إن كان $prefs['push'] مفعّلاً
        return AppNotification::create(['user_id' => $user->id, 'type' => $type, 'title' => $title, 'body' => $body, 'data' => $data]);
    }

    public function sms(string $phone, string $message): void
    {
        $this->sms->send($phone, $message);
    }
}
