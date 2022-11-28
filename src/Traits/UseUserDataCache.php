<?php

namespace mindtwo\PxUserLaravel\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;
use mindtwo\PxUserLaravel\Helper\UserDataCache;

/**
 * Use UserDataCache to get data from non-persistent cache
 */
trait UseUserDataCache
{
    public function getCacheAccessibleValues()
    {
        return [
            'email',
            'firstname',
            'lastname',
            'is_enabled',
            'is_confirmed',
            'roles',
            'products',
        ];
    }

    /**
     * Get attribute.
     *
     * @param  mixed  $name
     * @return mixed
     */
    public function getAttribute($name)
    {
        if (in_array($name, $this->getCacheAccessibleValues()) && ! method_exists($this, Str::camel($name))) {
            $value = $this->dataCache;

            if ($value && array_key_exists($name, $value)) {
                return $value[$name];
            }
        }

        return parent::getAttribute($name);
    }

    public function dataCache(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => isset($value) ? $value : UserDataCache::getUserData($this->{config('px-user.px_user_id')}),
        );
    }
}
