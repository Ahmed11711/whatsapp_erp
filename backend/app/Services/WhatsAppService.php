<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $phoneNumberId;
    private string $accessToken;
    private string $businessAccountId;
    private string $appId;
    private string $apiVersion;

    public function __construct()
    {
        $this->phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID', '');
        $this->accessToken = env('WHATSAPP_ACCESS_TOKEN', '');
        $this->businessAccountId = env('WHATSAPP_BUSINESS_ACCOUNT_ID', '');
        $this->appId = env('WHATSAPP_APP_ID', '');
        $this->apiVersion = env('WHATSAPP_API_VERSION', 'v21.0');
    }

    /**
     * Check if WhatsApp API is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->phoneNumberId)
            && !empty($this->accessToken)
            && !empty($this->businessAccountId);
    }

    /**
     * Send WhatsApp text message
     * 
     * @param string $to Phone number in E.164 format (e.g., +201234567890)
     * @param string $message Message content
     * @return array
     */
    public function sendWhatsAppMessage(string $to, string $message): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'WhatsApp API not configured'];
        }

        try {
            // Ensure phone number is in correct format
            $to = $this->formatPhoneNumber($to);

            $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

            $response = Http::withToken($this->accessToken)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'body' => $message,
                    ],
                ]);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['messages'][0]['id'])) {
                $messageId = $responseData['messages'][0]['id'];

                Log::info('WhatsApp message sent', [
                    'to' => $to,
                    'message_id' => $messageId,
                    'status' => 'sent',
                ]);

                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'status' => 'sent',
                ];
            } else {
                $error = $responseData['error'] ?? ['message' => 'Unknown error'];
                $errorMessage = $error['message'] ?? 'Failed to send message';

                Log::error('Failed to send WhatsApp message', [
                    'to' => $to,
                    'error' => $errorMessage,
                    'error_code' => $error['code'] ?? null,
                    'error_type' => $error['type'] ?? null,
                    'response' => $responseData,
                ]);

                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'error_code' => $error['code'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Exception while sending WhatsApp message', [
                'to' => $to,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send WhatsApp template message
     * 
     * @param string $to Phone number in E.164 format
     * @param string $templateName Template name (must be approved by Meta)
     * @param string $languageCode Language code (e.g., 'en', 'ar')
     * @param array $parameters Template parameters
     * @return array
     */
    public function sendTemplateMessage(string $to, string $templateName, string $languageCode = 'en', array $parameters = []): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'WhatsApp API not configured'];
        }

        try {
            $to = $this->formatPhoneNumber($to);

            $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => $languageCode,
                    ],
                ],
            ];

            // Add template parameters if provided
            if (!empty($parameters)) {
                $payload['template']['components'] = [
                    [
                        'type' => 'body',
                        'parameters' => array_map(function ($param) {
                            return ['type' => 'text', 'text' => $param];
                        }, $parameters),
                    ],
                ];
            }

            $response = Http::withToken($this->accessToken)
                ->post($url, $payload);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['messages'][0]['id'])) {
                $messageId = $responseData['messages'][0]['id'];

                Log::info('WhatsApp template message sent', [
                    'to' => $to,
                    'message_id' => $messageId,
                    'template' => $templateName,
                ]);

                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'status' => 'sent',
                ];
            } else {
                $error = $responseData['error'] ?? ['message' => 'Unknown error'];
                $errorMessage = $error['message'] ?? 'Failed to send template message';

                Log::error('Failed to send WhatsApp template message', [
                    'to' => $to,
                    'template' => $templateName,
                    'error' => $errorMessage,
                ]);

                return [
                    'success' => false,
                    'error' => $errorMessage,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Exception while sending WhatsApp template message', [
                'to' => $to,
                'template' => $templateName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Mark message as read
     * 
     * @param string $messageId WhatsApp message ID
     * @return array
     */
    public function markMessageAsRead(string $messageId): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'WhatsApp API not configured'];
        }

        try {
            $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

            $response = Http::withToken($this->accessToken)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'status' => 'read',
                    'message_id' => $messageId,
                ]);

            if ($response->successful()) {
                return ['success' => true];
            } else {
                $error = $response->json()['error'] ?? ['message' => 'Unknown error'];
                return [
                    'success' => false,
                    'error' => $error['message'] ?? 'Failed to mark message as read',
                ];
            }
        } catch (\Exception $e) {
            Log::error('Exception while marking message as read', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check WhatsApp Business Account status
     * 
     * @return array
     */
    public function checkWhatsAppStatus(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'WhatsApp API not configured',
                'is_configured' => false,
            ];
        }

        try {
            // Get phone number details
            $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}";
            
            $response = Http::withToken($this->accessToken)
                ->get($url, [
                    'fields' => 'verified_name,display_phone_number,quality_rating,code_verification_status',
                ]);

            if ($response->successful()) {
                $phoneData = $response->json();

                return [
                    'success' => true,
                    'is_configured' => true,
                    'phone_number_id' => $this->phoneNumberId,
                    'verified_name' => $phoneData['verified_name'] ?? null,
                    'display_phone_number' => $phoneData['display_phone_number'] ?? null,
                    'quality_rating' => $phoneData['quality_rating'] ?? null,
                    'code_verification_status' => $phoneData['code_verification_status'] ?? null,
                ];
            } else {
                $error = $response->json()['error'] ?? ['message' => 'Unknown error'];
                return [
                    'success' => false,
                    'error' => $error['message'] ?? 'Failed to check status',
                    'is_configured' => true,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Exception while checking WhatsApp status', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'is_configured' => true,
            ];
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
}



