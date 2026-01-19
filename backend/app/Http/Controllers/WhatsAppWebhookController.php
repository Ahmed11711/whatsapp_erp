<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Controller for handling incoming webhooks from Meta WhatsApp Business API.
 * 
 * Meta sends POST requests to this endpoint when customers send
 * WhatsApp messages. This controller processes those messages and
 * stores them in the database.
 * 
 * This route should be publicly accessible (no authentication required)
 * as Meta will call it directly.
 */
class WhatsAppWebhookController extends Controller
{
    /**
     * Handle webhook verification from Meta.
     * 
     * Meta requires webhook verification before sending events.
     * This endpoint handles the verification challenge.
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $verifyToken = env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', '');

        Log::info('WhatsApp webhook verification attempt', [
            'mode' => $mode,
            'token_received' => $token,
            'token_expected' => $verifyToken,
        ]);

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('WhatsApp webhook verified successfully');
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('WhatsApp webhook verification failed', [
            'mode' => $mode,
            'token_match' => $token === $verifyToken,
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Handle incoming WhatsApp messages from Meta.
     * 
     * Meta sends webhook requests with the following structure:
     * {
     *   "object": "whatsapp_business_account",
     *   "entry": [{
     *     "changes": [{
     *       "value": {
     *         "messages": [{
     *           "from": "1234567890",
     *           "id": "wamid.xxx",
     *           "timestamp": "1234567890",
     *           "text": { "body": "message content" },
     *           "type": "text"
     *         }]
     *       }
     *     }]
     *   }]
     * }
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleIncomingMessage(Request $request)
    {
        Log::info('Received WhatsApp webhook', $request->all());

        try {
            $data = $request->all();

            // Verify webhook is from WhatsApp Business Account
            if (!isset($data['object']) || $data['object'] !== 'whatsapp_business_account') {
                Log::warning('Invalid webhook object', ['object' => $data['object'] ?? 'missing']);
                return response()->json(['error' => 'Invalid webhook'], 400);
            }

            // Process each entry
            if (!isset($data['entry']) || !is_array($data['entry'])) {
                Log::warning('Missing entry in webhook');
                return response()->json(['error' => 'Missing entry'], 400);
            }

            foreach ($data['entry'] as $entry) {
                if (!isset($entry['changes']) || !is_array($entry['changes'])) {
                    continue;
                }

                foreach ($entry['changes'] as $change) {
                    // Handle incoming messages
                    if (isset($change['value']['messages']) && is_array($change['value']['messages'])) {
                        foreach ($change['value']['messages'] as $messageData) {
                            $this->processMessage($messageData);
                        }
                    }

                    // Handle status updates
                    if (isset($change['value']['statuses']) && is_array($change['value']['statuses'])) {
                        foreach ($change['value']['statuses'] as $statusData) {
                            $this->processStatusUpdate($statusData);
                        }
                    }
                }
            }

            return response()->json(['message' => 'Webhook processed'], 200);
        } catch (\Exception $e) {
            Log::error('Error processing WhatsApp webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }


    /**
     * Process incoming message from webhook
     * 
     * @param array $messageData
     * @return void
     */
    private function processMessage(array $messageData): void
    {
        try {
            $from = $messageData['from'] ?? '';
            $messageId = $messageData['id'] ?? '';
            $timestamp = $messageData['timestamp'] ?? time();
            $messageType = $messageData['type'] ?? '';

            // Extract message content based on type
            $content = '';
            if ($messageType === 'text' && isset($messageData['text']['body'])) {
                $content = $messageData['text']['body'];
            } elseif ($messageType === 'image' && isset($messageData['image']['caption'])) {
                $content = $messageData['image']['caption'];
            } elseif ($messageType === 'document' && isset($messageData['document']['caption'])) {
                $content = $messageData['document']['caption'];
            } else {
                // For other message types, use a placeholder
                $content = "[{$messageType} message]";
            }

            if (empty($from) || empty($messageId) || empty($content)) {
                Log::warning('WhatsApp message missing required fields', [
                    'from' => $from,
                    'message_id' => $messageId,
                    'type' => $messageType,
                ]);
                return;
            }

            // Format phone number
            $phoneNumber = $this->formatPhoneNumber($from);

            Log::info('Processing WhatsApp message', [
                'from' => $phoneNumber,
                'message_id' => $messageId,
                'type' => $messageType,
                'content_preview' => substr($content, 0, 100),
            ]);

            // Check if message already exists (prevent duplicates)
            $existingMessage = Message::where('whatsapp_message_id', $messageId)->first();
            if ($existingMessage) {
                Log::info('Duplicate WhatsApp message ignored', [
                    'message_id' => $messageId,
                ]);
                return;
            }

            // Find or create customer by phone number
            $customer = Customer::firstOrCreate(
                ['phone' => $phoneNumber],
                [
                    'name' => $this->generateCustomerName($phoneNumber),
                    'assigned_agent_id' => $this->assignToAgent(),
                ]
            );

            // Determine which agent should receive this message
            $receiverId = $customer->assigned_agent_id ?? $this->assignToAgent();

            // If customer was just created, update assigned agent
            if (!$customer->assigned_agent_id && $receiverId) {
                $customer->assigned_agent_id = $receiverId;
                $customer->save();
            }

            // Create inbound message record
            $message = Message::create([
                'customer_id' => $customer->id,
                'sender_id' => null, // Customer is not a user in the system
                'receiver_id' => $receiverId,
                'content' => $content,
                'direction' => 'inbound',
                'status' => 'received',
                'whatsapp_message_id' => $messageId,
            ]);

            Log::info('Incoming WhatsApp message stored', [
                'message_id' => $message->id,
                'customer_id' => $customer->id,
                'receiver_id' => $receiverId,
            ]);
        } catch (\Exception $e) {
            Log::error('Error processing WhatsApp message', [
                'error' => $e->getMessage(),
                'message_data' => $messageData,
            ]);
        }
    }

    /**
     * Process status update from webhook
     * 
     * @param array $statusData
     * @return void
     */
    private function processStatusUpdate(array $statusData): void
    {
        try {
            $messageId = $statusData['id'] ?? '';
            $status = $statusData['status'] ?? '';
            $recipientId = $statusData['recipient_id'] ?? '';

            if (empty($messageId)) {
                Log::warning('WhatsApp status update missing message ID');
                return;
            }

            // Find message by WhatsApp message ID
            $message = Message::where('whatsapp_message_id', $messageId)->first();

            if (!$message) {
                Log::warning('Status update for unknown message', [
                    'message_id' => $messageId,
                ]);
                return;
            }

            // Map Meta status to our status
            $statusMap = [
                'sent' => 'sent',
                'delivered' => 'delivered',
                'read' => 'read',
                'failed' => 'sent', // Keep as sent for failed messages
            ];

            $newStatus = $statusMap[$status] ?? $message->status;

            if ($status === 'failed') {
                $errorCode = $statusData['errors'][0]['code'] ?? null;
                $errorMessage = $statusData['errors'][0]['title'] ?? 'Unknown error';

                Log::error('WhatsApp message delivery failed', [
                    'message_id' => $message->id,
                    'whatsapp_message_id' => $messageId,
                    'status' => $status,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                ]);
            }

            // Only update if status changed
            if ($message->status !== $newStatus) {
                $message->status = $newStatus;
                $message->save();

                Log::info('Message status updated', [
                    'message_id' => $message->id,
                    'old_status' => $message->status,
                    'new_status' => $newStatus,
                    'whatsapp_status' => $status,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error processing WhatsApp status update', [
                'error' => $e->getMessage(),
                'status_data' => $statusData,
            ]);
        }
    }

    /**
     * Format phone number to E.164 format
     * 
     * @param string $phoneNumber
     * @return string
     */
    private function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove any non-digit characters except +
        $phone = preg_replace('/[^\d+]/', '', $phoneNumber);

        // Ensure it starts with +
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    /**
     * Generate a default name for a customer based on phone number.
     * 
     * @param string $phoneNumber
     * @return string
     */
    private function generateCustomerName(string $phoneNumber): string
    {
        // Use last 4 digits as identifier
        $lastFour = substr($phoneNumber, -4);
        return 'Customer ' . $lastFour;
    }

    /**
     * Assign customer to an available agent.
     * 
     * Simple round-robin assignment: finds the agent with the fewest
     * assigned customers. Can be enhanced with more sophisticated logic.
     * 
     * @return int|null Agent ID or null if no agents available
     */
    private function assignToAgent(): ?int
    {
        $agent = User::where('role', 'agent')
            ->withCount('customers')
            ->orderBy('customers_count', 'asc')
            ->first();

        return $agent?->id;
    }
}

