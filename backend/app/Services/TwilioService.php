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

            Log::info('WhatsApp message sent', [
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
            Log::error('Failed to send WhatsApp message', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
