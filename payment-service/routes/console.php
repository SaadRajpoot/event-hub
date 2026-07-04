<?php

use App\Jobs\ExpirePaymentsJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ExpirePaymentsJob)->everyMinute();
