<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppService
{
    private ?string $phoneNumberId = null;
    private ?string $accessToken = null;
    private string $metaVersion = 'v21.0';

    public function __construct()
    {
        $this->phoneNumberId = env('META_PHONE_NUMBER_ID');
        $this->accessToken = env('META_ACCESS_TOKEN');
        
        if (empty($this->phoneNumberId) || empty($this->accessToken)) {
            Log::warning('Meta WhatsApp credentials are missing.');
        }
    }

    /**
     * Send a text message via Meta WhatsApp Cloud API
     */
    public function sendMessage(string $to, string $message): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Meta WhatsApp not configured'];
        }

        try {
            // Format phone number (remove + if present, ensure code)
            $to = ltrim($to, '+');

            $response = Http::withToken($this->accessToken)
                ->post("https://graph.facebook.com/{$this->metaVersion}/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $message
                    ]
                ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Meta WhatsApp message sent', [
                    'to' => $to,
                    'message_id' => $data['messages'][0]['id'] ?? null
                ]);

                return [
                    'success' => true,
                    'message_sid' => $data['messages'][0]['id'] ?? null, // Meta uses 'id' not 'sid'
                    'status' => 'sent' // Meta doesn't return status in send response immediately
                ];
            } else {
                Log::error('Meta WhatsApp send failed', [
                    'to' => $to,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return [
                    'success' => false, 
                    'error' => $response->body()
                ];
            }
        } catch (\Exception $e) {
            Log::error('Meta WhatsApp exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a template message
     */
    public function sendTemplateMessage(string $to, string $templateName, string $languageCode = 'en_US', array $components = []): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Meta WhatsApp not configured'];
        }

        try {
            $to = ltrim($to, '+');

            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => $languageCode
                    ]
                ]
            ];

            if (!empty($components)) {
                $payload['template']['components'] = $components;
            }

            $response = Http::withToken($this->accessToken)
                ->post("https://graph.facebook.com/{$this->metaVersion}/{$this->phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Meta WhatsApp template sent', [
                    'to' => $to,
                    'template' => $templateName,
                    'message_id' => $data['messages'][0]['id'] ?? null
                ]);

                return [
                    'success' => true,
                    'message_sid' => $data['messages'][0]['id'] ?? null,
                    'status' => 'sent'
                ];
            } else {
                Log::error('Meta WhatsApp template send failed', [
                    'to' => $to,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return ['success' => false, 'error' => $response->body()];
            }

        } catch (\Exception $e) {
            Log::error('Meta WhatsApp exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->phoneNumberId) && !empty($this->accessToken);
    }
}
