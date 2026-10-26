<?php

namespace mindtwo\PxUserLaravel\Tests\Fake;

use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Laravel\Scout\Searchable;

class SearchableUser extends User
{
    use Searchable;

    protected $table = 'users';

    public function searchableUsing(): Engine
    {
        return app(EngineManager::class)->engine('px-user');
    }
}
