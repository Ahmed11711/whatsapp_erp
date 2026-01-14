<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Controller for handling incoming webhooks from Twilio.
 * 
 * Twilio sends POST requests to this endpoint when customers send
 * WhatsApp messages. This controller processes those messages and
 * stores them in the database.
 * 
 * This route should be publicly accessible (no authentication required)
 * as Twilio will call it directly.
 */
class TwilioWebhookController extends Controller
{
    /**
     * Handle incoming WhatsApp messages from Twilio.
     * 
     * Twilio sends webhook requests with the following parameters:
     * - From: Customer's WhatsApp number (e.g., whatsapp:+1234567890)
     * - To: Your Twilio WhatsApp number
     * - Body: Message content
     * - MessageSid: Unique Twilio message identifier
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleIncomingMessage(Request $request)
    {
        Log::info('Received Twilio webhook', $request->all());
        try {
            // Extract data from Twilio webhook
            $from = $request->input('From', '');
            $body = $request->input('Body', '');
            $messageSid = $request->input('MessageSid', '');
            $to = $request->input('To', '');

            // Log incoming webhook for debugging
            Log::info('Twilio webhook received', [
                'from' => $from,
                'to' => $to,
                'body' => substr($body, 0, 100),
                'message_sid' => $messageSid,
            ]);

            // Validate required fields
            if (empty($from) || empty($body)) {
                Log::warning('Twilio webhook missing required fields', [
                    'from' => $from,
                    'body' => $body,
                ]);

                return response()->json([
                    'error' => 'Missing required fields',
                ], 400);
            }

            // Extract phone number from Twilio format (whatsapp:+1234567890 -> +1234567890)
            $phoneNumber = $this->extractPhoneNumber($from);

            if (empty($phoneNumber)) {
                Log::warning('Could not extract phone number from Twilio webhook', [
                    'from' => $from,
                ]);

                return response()->json([
                    'error' => 'Invalid phone number format',
                ], 400);
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
            // Use assigned agent if available, otherwise assign to first available agent
            $receiverId = $customer->assigned_agent_id ?? $this->assignToAgent();

            // If customer was just created, update assigned agent
            if (!$customer->assigned_agent_id && $receiverId) {
                $customer->assigned_agent_id = $receiverId;
                $customer->save();
            }

            // Check if message already exists (prevent duplicates)
            $existingMessage = Message::where('twilio_message_sid', $messageSid)->first();
            if ($existingMessage) {
                Log::info('Duplicate Twilio message ignored', [
                    'message_sid' => $messageSid,
                ]);

                return response()->json([
                    'message' => 'Message already processed',
                ], 200);
            }

            // Create inbound message record
            $message = Message::create([
                'customer_id' => $customer->id,
                'sender_id' => null, // Customer is not a user in the system
                'receiver_id' => $receiverId,
                'content' => $body,
                'direction' => 'inbound',
                'status' => 'received',
                'twilio_message_sid' => $messageSid,
            ]);

            Log::info('Incoming WhatsApp message stored', [
                'message_id' => $message->id,
                'customer_id' => $customer->id,
                'receiver_id' => $receiverId,
            ]);

            // Return TwiML response (Twilio expects XML, but JSON is also acceptable)
            return response()->json([
                'message' => 'Message received',
                'message_id' => $message->id,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error processing Twilio webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Handle status callbacks from Twilio (delivered, read, failed, etc.).
     * 
     * Twilio can send status updates for messages. This endpoint handles
     * those updates and updates the message status in the database.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleStatusCallback(Request $request)
    {
        try {
            $messageSid = $request->input('MessageSid', '');
            $status = $request->input('MessageStatus', '');

            if (empty($messageSid)) {
                return response()->json(['error' => 'Missing MessageSid'], 400);
            }

            // Find message by Twilio SID
            $message = Message::where('twilio_message_sid', $messageSid)->first();

            if (!$message) {
                Log::warning('Status callback for unknown message', [
                    'message_sid' => $messageSid,
                ]);
                return response()->json(['message' => 'Message not found'], 404);
            }

            // Map Twilio status to our status
            $statusMap = [
                'queued' => 'sent',
                'sent' => 'sent',
                'delivered' => 'delivered',
                'read' => 'read',
                'failed' => 'sent', // Keep as sent for failed messages
                'undelivered' => 'sent',
            ];

            $newStatus = $statusMap[$status] ?? $message->status;

            if ($status === 'queued') {
                Log::warning('Message status is queued - WhatsApp number may not be fully activated', [
                    'message_id' => $message->id,
                    'message_sid' => $messageSid,
                    'twilio_status' => $status,
                    'note' => 'Messages stuck in queue usually mean the WhatsApp Business API number is not fully approved/activated. Check Twilio Console > Messaging > Senders.',
                ]);
            }

            if ($status === 'failed' || $status === 'undelivered') {
                $errorCode = $request->input('ErrorCode', '');
                $errorMessage = $request->input('ErrorMessage', '');
                
                Log::error('Message delivery failed', [
                    'message_id' => $message->id,
                    'message_sid' => $messageSid,
                    'twilio_status' => $status,
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
                    'twilio_status' => $status,
                ]);
            }

            return response()->json(['message' => 'Status updated'], 200);
        } catch (\Exception $e) {
            Log::error('Error processing Twilio status callback', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Extract phone number from Twilio format.
     * 
     * @param string $twilioFormat e.g., "whatsapp:+1234567890"
     * @return string e.g., "+1234567890"
     */
    private function extractPhoneNumber(string $twilioFormat): string
    {
        // Remove "whatsapp:" prefix if present
        $phone = str_replace('whatsapp:', '', $twilioFormat);

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
