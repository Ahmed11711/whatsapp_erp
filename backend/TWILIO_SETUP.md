# Twilio WhatsApp Integration Setup

This document explains how to configure Twilio WhatsApp messaging for this application.

## Environment Variables

Add the following variables to your `.env` file:

```env
# Twilio WhatsApp Configuration
TWILIO_ACCOUNT_SID=your_account_sid_here
TWILIO_AUTH_TOKEN=your_auth_token_here
TWILIO_WHATSAPP_NUMBER=your_whatsapp_number_here
```

### Getting Twilio Credentials

1. **Create a Twilio Account**: Sign up at https://www.twilio.com
2. **Get Account SID and Auth Token**: 
   - Log into Twilio Console
   - Find your Account SID and Auth Token on the dashboard
3. **Set up WhatsApp Sandbox** (for testing):
   - Go to Messaging > Try it out > Send a WhatsApp message
   - Follow instructions to join the sandbox
   - Use the sandbox number provided (format: `+14155238886`)
4. **Get Production WhatsApp Number** (for production):
   - Apply for WhatsApp Business API access
   - Once approved, you'll receive a WhatsApp-enabled phone number

### WhatsApp Number Format

The `TWILIO_WHATSAPP_NUMBER` should be in E.164 format (e.g., `+14155238886`). 
Do NOT include the `whatsapp:` prefix in the environment variable - it's added automatically.

## Webhook Configuration

Twilio needs to send webhooks to your application when customers send messages.

### For Local Development

Use a tool like ngrok to expose your local server:

```bash
ngrok http 8000
```

Then configure the webhook URL in Twilio Console:
- **Incoming Message Webhook**: `https://your-ngrok-url.ngrok.io/webhook/twilio/incoming`
- **Status Callback Webhook**: `https://your-ngrok-url.ngrok.io/webhook/twilio/status`

### For Production

Configure webhooks in Twilio Console:
- **Incoming Message Webhook**: `https://yourdomain.com/webhook/twilio/incoming`
- **Status Callback Webhook**: `https://yourdomain.com/webhook/twilio/status`

## How It Works

### Receiving Messages

1. Customer sends WhatsApp message to your Twilio number
2. Twilio sends POST request to `/webhook/twilio/incoming`
3. `TwilioWebhookController` processes the message:
   - Finds or creates customer by phone number
   - Assigns customer to an agent (if not already assigned)
   - Stores message in database with `direction='inbound'`
4. Message appears in agent's inbox

### Sending Messages

1. Agent types message in React frontend
2. Frontend sends POST to `/api/conversations/{customer}/messages`
3. `MessageController` creates message record
4. `TwilioService` sends message via Twilio API
5. Message status updates via status callback webhook

## Testing

### Test Sending a Message

1. Log in as an agent
2. Select a customer conversation
3. Type and send a message
4. Check Laravel logs for Twilio API calls
5. If Twilio is not configured, message is still saved but not sent

### Test Receiving a Message

1. Send a WhatsApp message to your Twilio number from a test phone
2. Check Laravel logs for webhook received
3. Message should appear in agent's inbox

## Troubleshooting

### Messages Not Sending

- Check that Twilio credentials are set in `.env`
- Verify `TWILIO_WHATSAPP_NUMBER` is correct format
- Check Laravel logs for Twilio API errors
- Ensure Twilio account has sufficient balance

### Messages Not Receiving

- Verify webhook URL is configured in Twilio Console
- Check that webhook URL is publicly accessible (use ngrok for local)
- Check Laravel logs for webhook errors
- Verify phone number format matches Twilio format

### Status Updates Not Working

- Ensure status callback webhook is configured
- Check that webhook endpoint is accessible
- Verify message SID is being stored correctly

## Security Notes

- Webhook routes (`/webhook/twilio/*`) are publicly accessible by design
- Consider adding webhook signature validation for production
- Never commit `.env` file with real credentials
- Use environment-specific credentials for different environments
