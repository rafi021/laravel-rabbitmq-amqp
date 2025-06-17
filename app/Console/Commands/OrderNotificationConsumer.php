<?php

namespace App\Console\Commands;

use App\Services\RabbitMQService;
use Illuminate\Console\Command;

class OrderNotificationConsumer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
     protected $signature = 'order:consume';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Order Notification Consumer';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $mqService = new RabbitMQService();
        $mqService->consumeTopic('notification_queue', 'mail.*');
    }
}
