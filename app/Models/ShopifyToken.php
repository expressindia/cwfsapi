<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyToken extends Model
{
    protected $fillable = [
        'shop_domain',
        'access_token',
        'scope',
        'associated_user_id',
        'associated_user_email',
        'associated_user_first_name',
        'associated_user_last_name',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Never expose the access token when the model
     * is converted to an array or JSON response.
     */
    protected $hidden = [
        'access_token',
    ];
}