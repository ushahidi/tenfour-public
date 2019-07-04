<?php

namespace TenFour\Console\Commands;

use Illuminate\Console\Command;
use TenFour\Jobs\SyncSubscriptions as SyncSubscriptionsJob;

class SyncSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Push local changes to subscriptions and addons to ChargeBee';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        SyncSubscriptionsJob::dispatch();
    }
}
