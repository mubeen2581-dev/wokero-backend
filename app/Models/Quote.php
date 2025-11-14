<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Quote extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'client_id',
        'subtotal',
        'tax_amount',
        'total',
        'profit_margin',
        'status',
        'valid_until',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'profit_margin' => 'decimal:2',
        'valid_until' => 'datetime',
    ];

    /**
     * Get the company that owns the quote.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the client that owns the quote.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the items for the quote.
     */
    public function items()
    {
        return $this->hasMany(QuoteItem::class);
    }

    /**
     * Get the jobs created from this quote.
     */
    public function jobs()
    {
        return $this->hasMany(Job::class);
    }
}

