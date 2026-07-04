<?php

namespace App\Services;

use App\Models\NotificationLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Send a notification based on type and channel.
     */
    public function send(array $data): NotificationLog
    {
        $log = NotificationLog::create([
            'type'      => $data['type'],
            'channel'   => $data['channel'] ?? 'email',
            'recipient' => $data['recipient'],
            'subject'   => $data['subject'] ?? null,
            'payload'   => $data['payload'] ?? [],
            'status'    => 'pending',
        ]);

        try {
            match ($log->channel) {
                'email' => $this->sendEmail($log),
                'sms'   => $this->sendSms($log),
                default => throw new \InvalidArgumentException("Unknown channel: {$log->channel}"),
            };

            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Notification send failed', [
                'log_id' => $log->id,
                'error'  => $e->getMessage(),
            ]);
            $log->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
        }

        return $log->fresh();
    }

    private function sendEmail(NotificationLog $log): void
    {
        $template = $this->resolveEmailTemplate($log->type);
        $payload = $log->payload;

        Mail::send([], [], function ($message) use ($log, $template, $payload) {
            $message->to($log->recipient)
                ->subject($log->subject ?? $template['subject'])
                ->html($this->renderTemplate($template['body'], $payload));
        });
    }

    private function sendSms(NotificationLog $log): void
    {
        // SMS provider integration placeholder
        // In production: integrate with Twilio, Nexmo, etc.
        Log::info('SMS notification (mock)', [
            'recipient' => $log->recipient,
            'type'      => $log->type,
            'message'   => $this->renderTemplate($this->resolveSmsTemplate($log->type), $log->payload),
        ]);
    }

    private function resolveEmailTemplate(string $type): array
    {
        return match ($type) {
            'order_confirmed' => [
                'subject' => 'Your order has been confirmed - EventHub',
                'body'    => '<h2>Order Confirmed!</h2><p>Hi {{name}},</p><p>Your order <strong>{{order_number}}</strong> for <strong>{{event_title}}</strong> has been confirmed.</p><p>Total: {{currency}} {{total_amount}}</p><p>Your ticket code(s): {{ticket_codes}}</p>',
            ],
            'order_cancelled' => [
                'subject' => 'Your order has been cancelled - EventHub',
                'body'    => '<h2>Order Cancelled</h2><p>Hi {{name}},</p><p>Your order <strong>{{order_number}}</strong> has been cancelled.</p><p>Reason: {{reason}}</p>',
            ],
            'vendor_approved' => [
                'subject' => 'Your vendor account has been approved - EventHub',
                'body'    => '<h2>Welcome to EventHub!</h2><p>Hi {{name}},</p><p>Your vendor account <strong>{{business_name}}</strong> has been approved. You can now create events.</p>',
            ],
            'waitlist_available' => [
                'subject' => 'Tickets are now available - EventHub',
                'body'    => '<h2>Good news!</h2><p>Hi {{name}},</p><p>Tickets for <strong>{{event_title}}</strong> ({{ticket_type}}) are now available. <a href="{{event_url}}">Book now</a> before they sell out!</p>',
            ],
            'payout_processed' => [
                'subject' => 'Payout processed - EventHub',
                'body'    => '<h2>Payout Processed</h2><p>Hi {{name}},</p><p>Your payout of <strong>{{currency}} {{amount}}</strong> (Batch: {{batch_number}}) has been processed.</p>',
            ],
            default => [
                'subject' => 'Notification from EventHub',
                'body'    => '<p>{{message}}</p>',
            ],
        };
    }

    private function resolveSmsTemplate(string $type): string
    {
        return match ($type) {
            'order_confirmed'    => 'EventHub: Order {{order_number}} confirmed for {{event_title}}. Ticket: {{ticket_codes}}',
            'order_cancelled'    => 'EventHub: Order {{order_number}} has been cancelled.',
            'waitlist_available' => 'EventHub: Tickets available for {{event_title}}! Book now: {{event_url}}',
            default              => 'EventHub: {{message}}',
        };
    }

    private function renderTemplate(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $template = str_replace('{{' . $key . '}}', htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'), $template);
            }
        }
        return $template;
    }
}
