<?php

namespace mindtwo\PxUserLaravel\Tests\Fake;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as AuthUser;
use mindtwo\PxUserLaravel\Traits\HasRefreshableApiTokens;

class User extends AuthUser
{
    use HasFactory,
        HasRefreshableApiTokens;

    protected $fillable = [
        'px_user_id',
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<static>
     */
    protected static function newFactory()
    {
        return new UserFactory;
    }
}
