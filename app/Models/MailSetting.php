<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    protected $fillable = [
        'host', 'port', 'username', 'password', 'encryption',
        'from_address', 'from_name', 'enabled',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'enabled' => 'boolean',
            'port' => 'integer',
        ];
    }
}
