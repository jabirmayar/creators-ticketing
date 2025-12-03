<?php

namespace daacreators\CreatorsTicketing\Database\Seeders;

use daacreators\CreatorsTicketing\Models\TicketStatus;
use Illuminate\Database\Seeder;

class TicketStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            [
                'name' => 'Open',
                'slug' => 'open',
                'color' => '#3b82f6', // Blue
                'is_default_for_new' => true,
                'is_closing_status' => false,
                'order_column' => 1,
            ],
            [
                'name' => 'In Progress',
                'slug' => 'in-progress',
                'color' => '#f59e0b', // Amber
                'is_default_for_new' => false,
                'is_closing_status' => false,
                'order_column' => 2,
            ],
            [
                'name' => 'Answered',
                'slug' => 'answered',
                'color' => '#22c55e', // Green
                'is_default_for_new' => false,
                'is_closing_status' => false,
                'order_column' => 3,
            ],
            [
                'name' => 'Pending',
                'slug' => 'pending',
                'color' => '#8b5cf6', // Purple
                'is_default_for_new' => false,
                'is_closing_status' => false,
                'order_column' => 4,
            ],
            [
                'name' => 'Resolved',
                'slug' => 'resolved',
                'color' => '#10b981', // Green
                'is_default_for_new' => false,
                'is_closing_status' => false,
                'order_column' => 5,
            ],
            [
                'name' => 'Closed',
                'slug' => 'closed',
                'color' => '#6b7280', // Gray
                'is_default_for_new' => false,
                'is_closing_status' => true,
                'order_column' => 6,
            ],
        ];

        foreach ($statuses as $status) {
            TicketStatus::query()->updateOrCreate(
                ['slug' => $status['slug']],
                $status
            );
        }
    }
}
