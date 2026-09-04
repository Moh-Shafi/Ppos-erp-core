<?php

namespace Database\Seeders;

use App\Models\WebhookEvent;
use Illuminate\Database\Seeder;

class WebhookEventSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            ['slug' => 'sale.created', 'name' => 'Sale Created', 'description' => 'Triggered when a sale is completed at checkout', 'module' => 'pos'],
            ['slug' => 'sale.cancelled', 'name' => 'Sale Cancelled', 'description' => 'Triggered when a sale is cancelled', 'module' => 'pos'],
            ['slug' => 'sale.refunded', 'name' => 'Sale Refunded', 'description' => 'Triggered when a sale is refunded', 'module' => 'pos'],
            ['slug' => 'payment.received', 'name' => 'Payment Received', 'description' => 'Triggered when a payment is recorded', 'module' => 'payments'],
            ['slug' => 'payment.settled', 'name' => 'Payment Settled', 'description' => 'Triggered when a gateway settlement is confirmed', 'module' => 'payments'],
            ['slug' => 'inventory.low_stock', 'name' => 'Inventory Low Stock', 'description' => 'Triggered when stock drops below minimum threshold', 'module' => 'inventory'],
            ['slug' => 'inventory.adjusted', 'name' => 'Inventory Adjusted', 'description' => 'Triggered when a manual stock adjustment is made', 'module' => 'inventory'],
            ['slug' => 'inventory.transferred', 'name' => 'Inventory Transferred', 'description' => 'Triggered when an inter-store transfer completes', 'module' => 'inventory'],
            ['slug' => 'purchase.received', 'name' => 'Purchase Received', 'description' => 'Triggered when a purchase order is received', 'module' => 'purchasing'],
            ['slug' => 'customer.created', 'name' => 'Customer Created', 'description' => 'Triggered when a new customer is registered', 'module' => 'customers'],
            ['slug' => 'customer.updated', 'name' => 'Customer Updated', 'description' => 'Triggered when a customer profile is updated', 'module' => 'customers'],
            ['slug' => 'reservation.created', 'name' => 'Reservation Created', 'description' => 'Triggered when a new reservation is made', 'module' => 'reservations'],
            ['slug' => 'appointment.created', 'name' => 'Appointment Created', 'description' => 'Triggered when a new appointment is booked', 'module' => 'appointments'],
            ['slug' => 'module.enabled', 'name' => 'Module Enabled', 'description' => 'Triggered when a tenant enables a module', 'module' => 'core'],
            ['slug' => 'module.disabled', 'name' => 'Module Disabled', 'description' => 'Triggered when a tenant disables a module', 'module' => 'core'],
        ];

        foreach ($events as $event) {
            WebhookEvent::firstOrCreate(['slug' => $event['slug']], $event);
        }
    }
}
