<?php

namespace mindtwo\PxUserLaravel\Sanctum;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use mindtwo\PxUserLaravel\Services\SanctumAccessTokenHelper;

class PersonalAccessToken extends SanctumPersonalAccessToken
{

    // public function expiresAt(): Attribute
    // {
    //     return Attribute::make(
    //         get: function ($value) {
    //             if ($value !== null) {
    //                 return $value;
    //             }

    //             $accessTokenHelper = app()->makeWith(SanctumAccessTokenHelper::class, [
    //                 'user' => $this->tokenable,
    //             ]);

    //             $this->expires_at = Carbon::now();
    //             $this->save();

    //             return $accessTokenHelper->accessTokenExpired() ? Carbon::now() : $value;
    //         },
    //     );
    // }

}
