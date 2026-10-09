<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class OtpChallenge extends Model
{
    use HasUlids;

    public $timestamps = false;
    protected $table = 'otp_challenges';
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime', 'consumed_at' => 'datetime', 'created_at' => 'datetime'];

    public function newUniqueId(): string
    {
        return strtolower((string) \Illuminate\Support\Str::ulid());
    }
}
