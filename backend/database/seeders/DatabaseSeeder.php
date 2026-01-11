<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Sales Agent
        $agent = User::create([
            'name' => 'Sales Agent',
            'email' => 'agent@example.com',
            'password' => Hash::make('password'),
            'role' => 'agent',
        ]);

        // Create Admin User
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // Create Sample Customers
        $customer1 = Customer::create([
            'name' => 'John Doe',
            'phone' => '+1234567890',
            'assigned_agent_id' => $agent->id,
        ]);

        $customer2 = Customer::create([
            'name' => 'Jane Smith',
            'phone' => '+1234567891',
            'assigned_agent_id' => $agent->id,
        ]);

        $customer3 = Customer::create([
            'name' => 'Bob Johnson',
            'phone' => '+1234567892',
            'assigned_agent_id' => $agent->id,
        ]);

        // Create Sample Messages
        // Outbound messages (agent -> customer)
        Message::create([
            'customer_id' => $customer1->id,
            'sender_id' => $agent->id,
            'receiver_id' => null,
            'content' => 'Hello John! How can I help you today?',
            'direction' => 'outbound',
            'status' => 'read',
        ]);

        Message::create([
            'customer_id' => $customer1->id,
            'sender_id' => $agent->id,
            'receiver_id' => null,
            'content' => 'I wanted to follow up on our previous conversation.',
            'direction' => 'outbound',
            'status' => 'sent',
        ]);

        // Inbound message (customer -> agent)
        Message::create([
            'customer_id' => $customer1->id,
            'sender_id' => null,
            'receiver_id' => $agent->id,
            'content' => 'Hi! I\'m interested in your products.',
            'direction' => 'inbound',
            'status' => 'received',
        ]);

        Message::create([
            'customer_id' => $customer2->id,
            'sender_id' => $agent->id,
            'receiver_id' => null,
            'content' => 'Hi Jane, thanks for reaching out!',
            'direction' => 'outbound',
            'status' => 'read',
        ]);

        // Inbound message (customer -> agent)
        Message::create([
            'customer_id' => $customer2->id,
            'sender_id' => null,
            'receiver_id' => $agent->id,
            'content' => 'When can we schedule a call?',
            'direction' => 'inbound',
            'status' => 'received',
        ]);

        Message::create([
            'customer_id' => $customer3->id,
            'sender_id' => $agent->id,
            'receiver_id' => null,
            'content' => 'Hello Bob, I have some exciting news to share!',
            'direction' => 'outbound',
            'status' => 'sent',
        ]);
    }
}
