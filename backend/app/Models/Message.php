<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'sender_id',
        'receiver_id',
        'content',
        'direction',
        'status',
        'twilio_message_sid',
    ];

    /**
     * Check if message is inbound (from customer).
     * 
     * @return bool
     */
    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    /**
     * Check if message is outbound (to customer).
     * 
     * @return bool
     */
    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}

