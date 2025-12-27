<?php

namespace App\Console\Commands;

use App\Jobs\DailySalesReport;
use Illuminate\Console\Command;

class SendDailySalesReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:daily-sales';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily sales report to admin';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Dispatching daily sales report job...');
        
        // Dispatch the job to the queue for background processing
        DailySalesReport::dispatch();
        
        $this->info('✓ Daily sales report job has been queued successfully!');
        $this->comment('The report will be processed in the background and emailed to the admin.');
        
        return Command::SUCCESS;
    }
}

