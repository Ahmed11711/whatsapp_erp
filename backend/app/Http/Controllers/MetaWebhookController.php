<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MetaWebhookController extends Controller
{
    /**
     * Handle Webhook Verification (GET)
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $verifyToken = env('META_VERIFY_TOKEN');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('Meta Webhook Verified');
            return response($challenge, 200);
        }

        Log::warning('Meta Webhook Verification Failed', $request->all());
        return response('Forbidden', 403);
    }

    /**
     * Handle Incoming Messages (POST)
     */
    public function handle(Request $request)
    {
        $body = $request->all();
        // Log::info('Meta Webhook Received', $body);

        try {
            if (isset($body['object']) && $body['object'] === 'whatsapp_business_account') {
                foreach ($body['entry'] as $entry) {
                    foreach ($entry['changes'] as $change) {
                        if ($change['field'] === 'messages') {
                            $value = $change['value'];

                            // Handle Messages
                            if (isset($value['messages'])) {
                                foreach ($value['messages'] as $messageData) {
                                    $this->processMessage($messageData, $value['contacts'] ?? []);
                                }
                            }

                            // Handle Status Updates (sent, delivered, read)
                            if (isset($value['statuses'])) {
                                foreach ($value['statuses'] as $statusData) {
                                    $this->processStatus($statusData);
                                }
                            }
                        }
                    }
                }
                return response('EVENT_RECEIVED', 200);
            }
            return response('Create', 404);
        } catch (\Exception $e) {
            Log::error('Meta Webhook Error', ['error' => $e->getMessage()]);
            return response('Internal Server Error', 500);
        }
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
