<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('assignment_group')->nullable()->after('assigned_to');
            $table->string('channel')->default('portal')->after('assignment_group');
            $table->string('impact')->default('medium')->after('channel');
            $table->string('urgency')->default('medium')->after('impact');
            $table->string('ingestion_reference')->nullable()->after('urgency');
            $table->timestamp('sla_due_at')->nullable()->after('status');
            $table->boolean('is_sla_breached')->default(false)->after('sla_due_at');
            $table->timestamp('response_due_at')->nullable()->after('is_sla_breached');
            $table->timestamp('first_response_at')->nullable()->after('response_due_at');
            $table->timestamp('resolved_at')->nullable()->after('first_response_at');
            $table->timestamp('closed_at')->nullable()->after('resolved_at');
            $table->timestamp('escalated_at')->nullable()->after('closed_at');

            $table->index(['status', 'assignment_group']);
            $table->index('sla_due_at');
            $table->index('channel');
        });

        Schema::table('ticket_status_histories', function (Blueprint $table) {
            $table->string('event_type')->default('status_change')->after('ticket_id');
            $table->json('metadata')->nullable()->after('note');
        });

        $statusMap = [
            'open' => 'intake',
            'in_progress' => 'in_progress',
            'resolved' => 'resolved',
            'done' => 'resolved',
            'closed' => 'closed',
        ];

        foreach ($statusMap as $from => $to) {
            DB::table('tickets')->where('status', $from)->update(['status' => $to]);
            DB::table('ticket_status_histories')->where('from_status', $from)->update(['from_status' => $to]);
            DB::table('ticket_status_histories')->where('to_status', $from)->update(['to_status' => $to]);
        }
    }

    public function down(): void
    {
        Schema::table('ticket_status_histories', function (Blueprint $table) {
            $table->dropColumn(['event_type', 'metadata']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['status', 'assignment_group']);
            $table->dropIndex(['sla_due_at']);
            $table->dropIndex(['channel']);

            $table->dropColumn([
                'assignment_group',
                'channel',
                'impact',
                'urgency',
                'ingestion_reference',
                'sla_due_at',
                'is_sla_breached',
                'response_due_at',
                'first_response_at',
                'resolved_at',
                'closed_at',
                'escalated_at',
            ]);
        });
    }
};
