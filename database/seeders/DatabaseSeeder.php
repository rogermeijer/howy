<?php

namespace Database\Seeders;

use App\Actions\Accounts\CreateAccount;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    // No WithoutModelEvents: BelongsToAccount fills account_id in a model event,
    // and the knowledge base seed relies on that (CLAUDE.md, tenancy rule 14).

    /**
     * Seed the application's database.
     *
     * Two accounts in different languages, and a user who belongs to both, so the
     * account switcher and the locale fallback are both visible without any setup.
     */
    public function run(): void
    {
        $createAccount = app(CreateAccount::class);

        $roger = User::factory()->create([
            'name' => 'Roger Meijer',
            'email' => 'roger@getcc.ai',
            'password' => Hash::make('admin'),
            'is_admin' => true,
        ]);

        $cc = $createAccount->handle($roger, 'cc:', 'nl', 'Europe/Amsterdam');

        $devkids = User::factory()->create([
            'name' => 'Roger Meijer',
            'email' => 'rogermeijer@gmail.com',
            'password' => Hash::make('admin'),
        ]);

        $devkidsAccount = $createAccount->handle($devkids, 'DevKids', 'en', 'UTC');

        // Roger sits in both accounts, as a plain member of the second one, so
        // switching visibly changes the language and the admin-versus-member
        // distinction is represented in the seed data.
        $devkidsAccount->users()->attach($roger, ['is_admin' => false]);

        $roger->active_account_id = $cc->id;
        $roger->save();

        // Processed knowledge bases exported with `php artisan knowledge:export-seed`.
        $this->call(KnowledgeSeeder::class);
    }
}
