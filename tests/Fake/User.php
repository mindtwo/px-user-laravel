<?php

namespace mindtwo\PxUserLaravel\Tests\Fake;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as AuthUser;
use mindtwo\PxUserLaravel\Contracts\PxUser;
use mindtwo\PxUserLaravel\Traits\HasPxUser;

class User extends AuthUser implements PxUser
{
    use HasFactory,
        HasPxUser;

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
