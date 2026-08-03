<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $activeRoles = DB::table('target_roles')
            ->where('status', 'confirmed')
            ->whereNull('archived_at')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'public_id', 'user_id']);

        foreach ($activeRoles as $targetRole) {
            $familiar = DB::table('user_familiars')
                ->where('user_id', $targetRole->user_id)
                ->whereNull('target_role_id')
                ->orderBy('id')
                ->first();

            $occurredAt = now();

            if ($familiar === null) {
                $familiarId = DB::table('user_familiars')->insertGetId([
                    'public_id' => (string) Str::ulid(),
                    'user_id' => $targetRole->user_id,
                    'target_role_id' => $targetRole->id,
                    'species_key' => 'queue_wyrm',
                    'name' => 'Queue Wyrm',
                    'stage' => 'summoned',
                    'summoned_at' => $occurredAt,
                    'created_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ]);

                DB::table('familiar_progression_events')->insert([
                    'user_familiar_id' => $familiarId,
                    'event_type' => 'familiar_summoned',
                    'metadata' => json_encode(['species_key' => 'queue_wyrm'], JSON_THROW_ON_ERROR),
                    'occurred_at' => $occurredAt,
                    'created_at' => $occurredAt,
                ]);
            } else {
                $familiarId = $familiar->id;

                DB::table('user_familiars')
                    ->where('id', $familiarId)
                    ->whereNull('target_role_id')
                    ->update([
                        'target_role_id' => $targetRole->id,
                        'updated_at' => $occurredAt,
                    ]);
            }

            DB::table('familiar_progression_events')->insert([
                'user_familiar_id' => $familiarId,
                'event_type' => 'target_role_assigned',
                'metadata' => json_encode([
                    'species_key' => 'queue_wyrm',
                    'target_role_public_id' => $targetRole->public_id,
                ], JSON_THROW_ON_ERROR),
                'occurred_at' => $occurredAt,
                'created_at' => $occurredAt,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $eventIds = DB::table('familiar_progression_events')
            ->where('event_type', 'target_role_assigned')
            ->pluck('id');

        DB::table('familiar_progression_events')->whereIn('id', $eventIds)->delete();
        DB::table('user_familiars')->whereNotNull('target_role_id')->update(['target_role_id' => null]);
    }
};
