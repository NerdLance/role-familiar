<?php

namespace Database\Seeders;

use App\Actions\Familiars\CompletePrototypeQuest;
use App\Actions\Familiars\SummonPrototypeFamiliar;
use App\Enums\PrototypeQuestKey;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $user = User::query()->firstOrCreate([
            'email' => 'mira.moon@example.test',
        ], [
            'name' => 'Mira Moon',
            'password' => 'password',
        ]);

        $familiar = app(SummonPrototypeFamiliar::class)($user);
        $complete = app(CompletePrototypeQuest::class);

        $complete($familiar, PrototypeQuestKey::BehavioralStory);
        $complete($familiar, PrototypeQuestKey::StarResponse);
    }
}
