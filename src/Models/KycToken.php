<?php

namespace Alice\Configuration\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KycToken extends Model
{
    use HasFactory;
    protected $fillable = [
        'login_token',
        'backend_token',
    ];
}
