<?php

use App\Facades\Tenancy;
use App\Models\Mailbox;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whom Howy reads becomes what it may do per sender: learn, answer, both or
     * neither. One access for the mailbox's own domain, and the senders listed
     * on top, each an address or a whole domain in a row of its own; everyone
     * else is ignored. Whether Howy replies is no switch of its own any more:
     * it follows from each sender's access.
     *
     * The policy and its lists map onto that: the whitelist's entries are
     * learned from and answered, the blacklist's ignored, and with replies off
     * nobody is answered. The list a policy ignored counted for nothing and
     * goes. "Everyone" cannot be said any more: those mailboxes keep their own
     * domain and their blacklist, and read no one else.
     */
    public function up(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->string('domain_access', 20)->default('learn_answer')->after('import_batch_id');
        });

        Schema::create('mailbox_senders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            // An address (jan@klant.nl) or a whole domain (klant.nl), lowercased.
            $table->string('entry');
            $table->string('access', 20);
            $table->timestamps();

            $table->unique(['account_id', 'mailbox_id', 'entry']);
        });

        // Every account's mailboxes, on purpose: a migration has no tenant.
        Tenancy::withoutTenancy(function (): void {
            Mailbox::query()->each(function (Mailbox $mailbox): void {
                $policy = (string) $mailbox->getRawOriginal('send_policy');
                $replies = (bool) $mailbox->getRawOriginal('replies_enabled');
                $list = $policy === 'whitelist' ? 'send_whitelist' : 'send_blacklist';
                $access = fn (string $access): string => $replies ? $access : match ($access) {
                    'learn_answer' => 'learn',
                    'answer' => 'ignore',
                    default => $access,
                };

                $entries = [];

                foreach ((array) json_decode((string) $mailbox->getRawOriginal($list), true) as $entry) {
                    $entry = ltrim(strtolower(trim((string) $entry)), '@');

                    if ($entry !== '') {
                        $entries[$entry] = true;
                    }
                }

                // The table, not a model: mailbox_senders has since been replaced
                // by mailbox_rules (2026_11_11_100000_create_colleagues_and_mailbox_rules).
                foreach (array_keys($entries) as $entry) {
                    DB::table('mailbox_senders')->insert([
                        'account_id' => $mailbox->getRawOriginal('account_id'),
                        'mailbox_id' => $mailbox->id,
                        'entry' => $entry,
                        'access' => $access($policy === 'whitelist' ? 'learn_answer' : 'ignore'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $mailbox->forceFill([
                    'domain_access' => $access($policy === 'whitelist' ? 'ignore' : 'learn_answer'),
                ])->saveQuietly();
            });
        });

        Schema::table('mailboxes', function (Blueprint $table) {
            $table->dropColumn(['send_policy', 'send_whitelist', 'send_blacklist', 'replies_enabled']);
        });
    }

    public function down(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->string('send_policy', 20)->default('domain')->after('import_batch_id');
            $table->json('send_whitelist')->nullable()->after('send_policy');
            $table->json('send_blacklist')->nullable()->after('send_whitelist');
            $table->boolean('replies_enabled')->default(true)->after('send_blacklist');
        });

        Tenancy::withoutTenancy(function (): void {
            Mailbox::query()->each(function (Mailbox $mailbox): void {
                $senders = DB::table('mailbox_senders')->where('mailbox_id', $mailbox->id)->orderBy('id')->get();
                $entries = fn (bool $ignored): array => $senders
                    ->filter(fn (object $sender): bool => ($sender->access === 'ignore') === $ignored)
                    ->map(fn (object $sender): string => $sender->entry)
                    ->values()
                    ->all();

                $mailbox->forceFill([
                    'send_policy' => $mailbox->getRawOriginal('domain_access') !== 'ignore' ? 'domain' : 'whitelist',
                    'send_whitelist' => json_encode($entries(false)),
                    'send_blacklist' => json_encode($entries(true)),
                ])->saveQuietly();
            });
        });

        Schema::dropIfExists('mailbox_senders');

        Schema::table('mailboxes', function (Blueprint $table) {
            $table->dropColumn('domain_access');
        });
    }
};
