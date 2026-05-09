<?php

namespace App\Providers;

use App\Listeners\Schedule\ReleaseLocksOnPresenceLeave;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Laravel\Reverb\Events\ConnectionPruned;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ConnectionPruned::class => [
            ReleaseLocksOnPresenceLeave::class,
        ],
    ];
}

