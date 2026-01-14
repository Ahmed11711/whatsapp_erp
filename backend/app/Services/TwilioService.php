<?php

namespace App\Services;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

class TwilioService
{
    private ?Client $client = null;
    private string $accountSid;
    private string $authToken;
    private string $whatsappNumber;
    private string $templateSid;

    public function __construct()
    {
        $this->accountSid     = env('TWILIO_ACCOUNT_SID', 'AC2b2a7a36eb73925c004793cb37bda491');
        $this->authToken      = env('TWILIO_AUTH_TOKEN', '8ec89754eef76cd8ea457c971cd52f19');
        $this->whatsappNumber = env('TWILIO_WHATSAPP_NUMBER', '+201111483157');
        $this->templateSid    = 'HXb685718b44a1a30a29a45f4b791fc24f';
    }

    private function getClient(): ?Client
    {
        if (empty($this->accountSid) || empty($this->authToken)) {
            return null;
        }

        if ($this->client === null) {
            $this->client = new Client($this->accountSid, $this->authToken);
        }

        return $this->client;
    }

    public function isConfigured(): bool
    {
        return !empty($this->accountSid)
            && !empty($this->authToken)
            && !empty($this->whatsappNumber)
            && !empty($this->templateSid);
    }

    /**
     * Send first WhatsApp message using Template
     */
    public function sendTemplateMessage(string $to, array $variables = []): array
    {
        $client = $this->getClient();
        if (!$client) {
            return ['success' => false, 'error' => 'Twilio not configured'];
        }

        try {
            $twilioMessage = $client->messages->create(
                'whatsapp:' . $to,
                [
                    'from' => 'whatsapp:' . $this->whatsappNumber,
                    'contentSid' => $this->templateSid,
                    'contentVariables' => json_encode($variables),
                    'statusCallback' => null
                ]
            );

            Log::info('WhatsApp template message sent', [
                'to' => $to,
                'message_sid' => $twilioMessage->sid,
                'status' => $twilioMessage->status,
            ]);

            return [
                'success' => true,
                'message_sid' => $twilioMessage->sid,
                'status' => $twilioMessage->status,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp template message', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send normal WhatsApp message after user has replied
     */
    public function sendWhatsAppMessage(string $to, string $message): array
    {
        $client = $this->getClient();
        if (!$client) {
            return ['success' => false, 'error' => 'Twilio not configured'];
        }

        try {
            $twilioMessage = $client->messages->create(
                'whatsapp:' . $to,
                [
                    'from' => 'whatsapp:' . $this->whatsappNumber,
                    'body' => $message,
                    'statusCallback' => null,
                ]
            );

            // Log warning if message is queued (indicates number might not be activated)
            if ($twilioMessage->status === 'queued') {
                Log::warning('WhatsApp message queued - Number may not be fully activated', [
                    'to' => $to,
                    'message_sid' => $twilioMessage->sid,
                    'status' => $twilioMessage->status,
                    'whatsapp_number' => $this->whatsappNumber,
                    'note' => 'Messages in queue usually mean the WhatsApp number is not fully activated. Check Twilio Console > Messaging > Senders to verify number status.',
                ]);
            }

            Log::info('WhatsApp message sent', [
                'to' => $to,
                'message_sid' => $twilioMessage->sid,
                'status' => $twilioMessage->status,
            ]);

            return [
                'success' => true,
                'message_sid' => $twilioMessage->sid,
                'status' => $twilioMessage->status,
                'is_queued' => $twilioMessage->status === 'queued',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp message', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check WhatsApp number status and activation
     * 
     * @return array Status information about the WhatsApp number
     */
    public function checkWhatsAppNumberStatus(): array
    {
        $client = $this->getClient();
        if (!$client) {
            return ['success' => false, 'error' => 'Twilio not configured'];
        }

        try {
            // Remove 'whatsapp:' prefix if present
            $phoneNumber = str_replace('whatsapp:', '', $this->whatsappNumber);
            
            // Get phone number information
            $phoneNumbers = $client->incomingPhoneNumbers->read([
                'phoneNumber' => $phoneNumber
            ]);

            if (empty($phoneNumbers)) {
                return [
                    'success' => false,
                    'error' => 'Phone number not found in Twilio account',
                    'phone_number' => $phoneNumber,
                ];
            }

            $phoneNumberObj = $phoneNumbers[0];
            
            // Check for WhatsApp sender (WhatsApp Business API)
            $senders = $client->messaging->v1->services->read();
            
            $status = [
                'success' => true,
                'phone_number' => $phoneNumber,
                'phone_number_sid' => $phoneNumberObj->sid,
                'phone_number_status' => 'active',
                'capabilities' => [
                    'sms' => $phoneNumberObj->capabilities['sms'] ?? false,
                    'voice' => $phoneNumberObj->capabilities['voice'] ?? false,
                ],
                'note' => 'Check Twilio Console > Messaging > Senders to verify WhatsApp Business API approval status',
            ];

            // Try to get messaging service info
            try {
                $messagingServices = $client->messaging->v1->services->read();
                if (!empty($messagingServices)) {
                    $status['messaging_services'] = count($messagingServices);
                }
            } catch (\Exception $e) {
                // Ignore if messaging services can't be accessed
            }

            Log::info('WhatsApp number status checked', $status);

            return $status;
        } catch (\Exception $e) {
            Log::error('Failed to check WhatsApp number status', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get recent message statuses to check for queued messages
     * 
     * @param int $limit Number of recent messages to check
     * @return array Recent message statuses
     */
    public function getRecentMessageStatuses(int $limit = 10): array
    {
        $client = $this->getClient();
        if (!$client) {
            return ['success' => false, 'error' => 'Twilio not configured'];
        }

        try {
            $options = [
                'from' => 'whatsapp:' . $this->whatsappNumber,
            ];
            $messages = $client->messages->read($options, $limit);

            $statuses = [];
            $queuedCount = 0;

            foreach ($messages as $message) {
                $statuses[] = [
                    'sid' => $message->sid,
                    'to' => $message->to,
                    'status' => $message->status,
                    'date_sent' => $message->dateSent?->format('Y-m-d H:i:s'),
                    'error_code' => $message->errorCode,
                    'error_message' => $message->errorMessage,
                ];

                if ($message->status === 'queued') {
                    $queuedCount++;
                }
            }

            $result = [
                'success' => true,
                'total_checked' => count($statuses),
                'queued_count' => $queuedCount,
                'messages' => $statuses,
            ];

            if ($queuedCount > 0) {
                $result['warning'] = "Found {$queuedCount} queued message(s). This usually indicates the WhatsApp number is not fully activated.";
            }

            Log::info('Recent message statuses checked', $result);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to get recent message statuses', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
