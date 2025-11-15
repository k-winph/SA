<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $statusMap = [
            'intake' => 'open',
        ];

        foreach ($statusMap as $from => $to) {
            DB::table('tickets')->where('status', $from)->update(['status' => $to]);
            DB::table('ticket_status_histories')->where('from_status', $from)->update(['from_status' => $to]);
            DB::table('ticket_status_histories')->where('to_status', $from)->update(['to_status' => $to]);
        }
    }

    public function down(): void
    {
        $statusMap = [
            'open' => 'intake',
        ];

        foreach ($statusMap as $from => $to) {
            DB::table('tickets')->where('status', $from)->update(['status' => $to]);
            DB::table('ticket_status_histories')->where('from_status', $from)->update(['from_status' => $to]);
            DB::table('ticket_status_histories')->where('to_status', $from)->update(['to_status' => $to]);
        }
    }
};
