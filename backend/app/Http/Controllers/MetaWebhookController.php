<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class MetaWebhookController extends Controller
{
    /**
     * Handle Webhook Verification (GET)
     */
    public function verify(Request $request)
    {
        Log::info('Meta Webhook VERIFY data', [
            'query' => $request->query(),
            'ip' => $request->ip(),
            'headers' => $request->headers->all(),
        ]);

        return response('success', 200);
    }

//   public function handle(Request $request)
// {
//     $data = $request->all();

//     // Logs the entire JSON properly
//     Log::info('Meta Webhook HANDLE data: ' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

//     return response('success', 200);
// }


public function handle(Request $request)
{
    $data = $request->all();

    foreach ($data['entry'] as $entry) {
        foreach ($entry['changes'] as $change) {
            $value = $change['value'];

            if (isset($value['messages'])) {
                foreach ($value['messages'] as $msg) {
                    $from = $msg['from']; // الرقم اللي بعتلك الرسالة
                    Log::info("Incoming WhatsApp message from {$from}");

                    // رسالة ثابتة للتجربة
                    $this->sendStaticReply($from);
                }
            }
        }
    }

    return response('success', 200);
}

private function sendStaticReply($to)
{
    $phone_number_id = '992330837294579'; // رقم البزنس الخاص بيك
    $access_token = 'EAANBfjf5ke8BQj6wDWDwZCXyTCRJuZA2osiOWXm6z7tX1J96Jrc1yVZCxZBJLVlZB8E7EFOqZCcsGQz0ckGGnPHwPQECog1KCgCMwwNyDZAKVrAgXJW7ly8vWDnMWGPrkMOTpZCLomok08VCB7mFbwTmdWPCPlWVgToATbiZBMm1ZB5CZA7vOWzMtcpGQDl9QfL';

    $url = "https://graph.facebook.com/v17.0/{$phone_number_id}/messages";

    $response = Http::withToken($access_token)
        ->post($url, [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "text",
            "text" => [
                "body" => "Hello! This is a test message ✅"
            ]
        ]);

    Log::info('Reply sent: ' . $response->body());
}

















    private function processMessage($messageData, $contacts)
    {
        $from = $messageData['from']; // Phone number
        $id = $messageData['id']; // Message ID
        $type = $messageData['type'];
        $timestamp = $messageData['timestamp'];
        
        $body = '';
        if ($type === 'text') {
            $body = $messageData['text']['body'];
        } else {
             // Handle other types if needed (image, etc.)
             $body = '[' . ucfirst($type) . ' Message]';
        }

        // Get customer name from contacts if available
        $customerName = 'Customer ' . substr($from, -4);
        foreach ($contacts as $contact) {
            if ($contact['wa_id'] === $from) {
                $customerName = $contact['profile']['name'] ?? $customerName;
                break;
            }
        }

        // Find or create customer
        // Ensure phone starts with +
        $phone = '+' . $from;
        
        $customer = Customer::firstOrCreate(
            ['phone' => $phone],
            [
                'name' => $customerName,
                'assigned_agent_id' => $this->assignToAgent(),
            ]
        );

        $receiverId = $customer->assigned_agent_id ?? $this->assignToAgent();
         if (!$customer->assigned_agent_id && $receiverId) {
            $customer->assigned_agent_id = $receiverId;
            $customer->save();
        }

        // Check for duplicates
        if (Message::where('twilio_message_sid', $id)->exists()) {
            return;
        }

        // Create Message
        Message::create([
            'customer_id' => $customer->id,
            'sender_id' => null,
            'receiver_id' => $receiverId,
            'content' => $body,
            'direction' => 'inbound',
            'status' => 'received',
            'twilio_message_sid' => $id, // Storing Meta ID in existing column
            'created_at' => date('Y-m-d H:i:s', $timestamp),
        ]);

        Log::info('Meta Message Stored', ['id' => $id]);
    }

    private function processStatus($statusData)
    {
        $id = $statusData['id'];
        $status = $statusData['status'];
        // $recipient_id = $statusData['recipient_id'];

        $message = Message::where('twilio_message_sid', $id)->first();

        if ($message && $message->status !== $status) {
            $message->status = $status;
            $message->save();
            Log::info('Meta Message Status Updated', ['id' => $id, 'status' => $status]);
        }
    }

    private function assignToAgent(): ?int
    {
        $agent = User::where('role', 'agent')
            ->withCount('customers')
            ->orderBy('customers_count', 'asc')
            ->first();

        return $agent?->id;
    }
}
