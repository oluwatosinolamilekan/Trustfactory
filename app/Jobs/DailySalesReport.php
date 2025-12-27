<?php

namespace App\Jobs;

use App\Mail\DailySalesReportMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class DailySalesReport implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $today = now()->format('Y-m-d');
        
        $orders = Order::getOrdersByDate($today);

        $totalRevenue = $orders->sum('total_amount');
        $totalOrders = $orders->count();
        $productsSold = OrderItem::getProductsSoldByDate($today);

        $salesData = [
            'date' => $today,
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue,
            'products_sold' => $productsSold,
        ];

        $admin = User::getFirstAdmin();
        
        if ($admin) {
            Mail::to($admin->email)->send(new DailySalesReportMail($salesData));
        }
    }
}
