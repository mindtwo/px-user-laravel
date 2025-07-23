<?php

use Illuminate\Support\Facades\Http;
use mindtwo\PxUserLaravel\Tests\TestCase;

uses(TestCase::class)->in(__DIR__)
    ->beforeEach(function () {
        // Set up any necessary preconditions for the tests
        Http::preventStrayRequests();
    });
