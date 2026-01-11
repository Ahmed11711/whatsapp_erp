<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Message;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    /**
     * Initialize controller with Twilio service.
     */
    public function __construct(
        private TwilioService $twilioService
    ) {}

    /**
     * List all messages for the authenticated agent.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $messages = Message::with(['customer', 'sender', 'receiver'])
            ->where(function ($q) use ($user) {
                $q->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->limit(200)
            ->get();

        return response()->json($messages);
    }

    /**
     * List conversations grouped by customer, including last message and unread count.
     */
    public function conversations(Request $request)
    {
        $user = $request->user();

        $customers = Customer::withCount([
            'messages as unread_count' => function ($q) use ($user) {
                $q->where('receiver_id', $user->id)
                    ->whereIn('status', ['sent', 'received']);
            },
        ])
            ->with(['messages' => function ($q) use ($user) {
                $q->where(function ($q2) use ($user) {
                    $q2->where('sender_id', $user->id)
                        ->orWhere('receiver_id', $user->id);
                })
                    ->latest()
                    ->limit(1);
            }])
            ->where(function ($q) use ($user) {
                $q->where('assigned_agent_id', $user->id)
                    ->orWhereNull('assigned_agent_id');
            })
            ->get();

        $data = $customers->map(function (Customer $customer) {
            $lastMessage = $customer->messages->first();

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'unread_count' => $customer->unread_count,
                'last_message' => $lastMessage ? [
                    'id' => $lastMessage->id,
                    'content' => $lastMessage->content,
                    'status' => $lastMessage->status,
                    'created_at' => $lastMessage->created_at,
                ] : null,
            ];
        });

        return response()->json($data);
    }

    /**
     * Get full conversation with a specific customer.
     */
    public function show(Request $request, Customer $customer)
    {
        $user = $request->user();

        $messages = Message::with(['sender', 'receiver'])
            ->where('customer_id', $customer->id)
            ->where(function ($q) use ($user) {
                $q->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'customer' => $customer,
            'messages' => $messages,
        ]);
    }

    /**
     * Send a message in a conversation.
     *
     * Creates a message record and sends it via Twilio WhatsApp API
     * to the customer's phone number.
     */
    public function store(Request $request, Customer $customer)
    {
        $user = $request->user();

        $data = $request->validate([
            'content' => ['required', 'string', 'max:1600'], // WhatsApp limit
        ]);

        // Create message record
        $message = Message::create([
            'customer_id' => $customer->id,
            'sender_id' => $user->id,
            'receiver_id' => null, // customer side (not a user in system)
            'content' => $data['content'],
            // 'direction' => 'outbound',
            'status' => 'sent',
        ]);

        // Send message via Twilio
        $result = $this->twilioService->sendWhatsAppMessage(
            $customer->phone = '+201094321637',
            $data['content']
        );

        // Update message with Twilio SID if successful
        if ($result['success'] && isset($result['message_sid'])) {
            $message->twilio_message_sid = $result['message_sid'];
            $message->save();
        } else {
            // Log error but still return the message (it's stored in DB)
            // Frontend can handle the error if needed
            Log::warning('Failed to send message via Twilio', [
                'message_id' => $message->id,
                'error' => $result['error'] ?? 'Unknown error',
            ]);
        }

        // Load relationships for response
        $message->load(['customer', 'sender']);

        return response()->json($message, 201);
    }

    /**
     * Mark a single message as read.
     */
    public function markRead(Request $request, Message $message)
    {
        $user = $request->user();

        if ($message->receiver_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $message->status = 'read';
        $message->save();

        return response()->json($message);
    }

    /**
     * Mark all messages in a conversation as read for the current agent.
     */
    public function markConversationRead(Request $request, Customer $customer)
    {
        $user = $request->user();

        Message::where('customer_id', $customer->id)
            ->where('receiver_id', $user->id)
            ->whereIn('status', ['sent', 'received'])
            ->update(['status' => 'read']);

        return response()->json(['message' => 'Conversation marked as read']);
    }
}
