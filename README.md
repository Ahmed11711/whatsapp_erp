## WhatsApp-like Sales Communication Platform

This repository contains a **Laravel** backend (`backend`) and a **React (Vite)** frontend (`frontend`) for a WhatsApp-style sales communication tool with **full Twilio WhatsApp integration**.

- **Backend**: Laravel API with Twilio WhatsApp integration for receiving and sending messages.
- **Frontend**: Responsive React SPA with login, conversation list, and chat interface.

### Running the backend

1. **Install dependencies**:

   ```bash
   cd backend
   composer install
   ```

2. **Environment & database**:

   - Copy `.env.example` to `.env`.
   - Set your DB connection (for quick testing you can use `DB_CONNECTION=sqlite` and `DB_DATABASE=database/database.sqlite`).
   - **Configure Twilio** (see `backend/TWILIO_SETUP.md` for details):
     ```env
     TWILIO_ACCOUNT_SID=your_account_sid
     TWILIO_AUTH_TOKEN=your_auth_token
     TWILIO_WHATSAPP_NUMBER=your_whatsapp_number
     ```

   ```bash
   php artisan key:generate
   php artisan migrate
   php artisan db:seed  # Optional: creates sample data
   ```

   Optionally, create at least one sales agent user:

   ```php
   // tinker example
   php artisan tinker
   >>> \App\Models\User::create([
   ...   'name' => 'Agent One',
   ...   'email' => 'agent@example.com',
   ...   'password' => bcrypt('password'),
   ...   'role' => 'agent',
   ... ]);
   ```

3. **Serve the API**:

   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```

   The API will be available at `http://localhost:8000/api`.

### Running the frontend

1. **Install dependencies**:

   ```bash
   cd frontend
   npm install
   ```

2. **Configure API URL**:

   Create `frontend/.env.local` (or `.env`) with:

   ```bash
   VITE_API_URL=http://localhost:8000/api
   ```

3. **Start the dev server**:

   ```bash
   npm run dev
   ```

   Visit the URL printed in the console (typically `http://localhost:5173`).

### High-level architecture

- **Backend (Laravel)**
  - Models: `User` (agents/admins), `Customer`, `Message`.
  - Tables:
    - `users`: `id`, `name`, `email`, `password`, `role`, `api_token`, timestamps.
    - `customers`: `id`, `name`, `phone`, `assigned_agent_id`, timestamps.
    - `messages`: `id`, `customer_id`, `sender_id`, `receiver_id`, `content`, `direction`, `status`, `twilio_message_sid`, timestamps.
  - Auth: simple API token-based auth via `/api/auth/login` and `ApiTokenMiddleware`.
  - **Twilio Integration**:
    - `TwilioService`: Handles sending WhatsApp messages via Twilio API.
    - `TwilioWebhookController`: Receives incoming messages and status callbacks from Twilio.
    - Webhook routes: `/webhook/twilio/incoming` and `/webhook/twilio/status` (public, no auth required).
  - Messaging endpoints:
    - `GET /api/messages` – list messages for current agent.
    - `GET /api/conversations` – list conversations grouped by customer.
    - `GET /api/conversations/{customer}` – conversation with a specific customer.
    - `POST /api/conversations/{customer}/messages` – send message (automatically sends via Twilio).
    - `POST /api/messages/{message}/read` – mark a message as read.
    - `POST /api/customers/{customer}/read` – mark all messages in a conversation as read.

- **Frontend (React + Vite)**
  - Uses functional components and hooks.
  - Pages:
    - `LoginPage` – agent login.
    - `DashboardPage` – conversation list + selected conversation chat.
  - Components:
    - `Sidebar` – lists conversations with unread counts.
    - `MessageList` – shows message history with sender name, time, status.
    - `MessageInput` – message composer.
  - API client:
    - `src/services/api.js` – wraps `fetch` and reads `VITE_API_URL` from env.

## Twilio WhatsApp Integration

The platform is fully integrated with Twilio WhatsApp API:

- **Receiving Messages**: Customers send WhatsApp messages → Twilio webhook → stored in database → appears in agent inbox
- **Sending Messages**: Agent sends message → stored in database → sent via Twilio → status updates via webhook
- **Status Tracking**: Message status (sent, delivered, read) is tracked via Twilio status callbacks

See `backend/TWILIO_SETUP.md` for detailed setup instructions.

## Features

✅ Full Twilio WhatsApp integration  
✅ Receive messages from customers via webhook  
✅ Send messages to customers via Twilio API  
✅ Message status tracking (sent, delivered, read)  
✅ Automatic customer creation from incoming messages  
✅ Agent assignment (round-robin)  
✅ Mobile-responsive UI  
✅ Real-time conversation view  
✅ Unread message counts  

This setup provides a production-ready WhatsApp-like sales communication platform with full Twilio integration.

