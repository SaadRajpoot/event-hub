<?php

use App\Jobs\ExpireOrdersJob;
use App\Jobs\RetryWebhookDeliveriesJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ExpireOrdersJob)->everyMinute();
Schedule::job(new RetryWebhookDeliveriesJob)->everyFiveMinutes();
