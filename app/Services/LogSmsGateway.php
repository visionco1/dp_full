<?php

namespace App\Services;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

/** للتطوير: يكتب الرسالة في storage/logs. استبدله بمزوّد SMS حقيقي (Unifonic, Taqnyat, Twilio...) */
class LogSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): void
    {
        Log::info("SMS to +{$phone}: {$message}");
    }
}
