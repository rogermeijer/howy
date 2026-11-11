<?php

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Colleague;
use App\Models\Mailbox;
use App\Models\MailboxRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The people who mail Howy become colleagues, one per address per account,
     * and what Howy may do with their mail becomes rules on a mailbox, each for
     * one colleague or one domain.
     *
     * A listed address becomes a colleague with a rule of their own, a listed
     * domain a domain rule, and the access for the mailbox's own domain a
     * domain rule that creates colleagues. Where that domain was listed as
     * well, the listed row decided and still does. From the start, Howy's own
     * mails go only to colleagues at the company's domain and to members of
     * the account; an admin can switch anyone else on.
     */
    public function up(): void
    {
        Schema::create('colleagues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            // Lowercased.
            $table->string('email');
            $table->string('name')->nullable();
            // A member of the account with this address.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // The mailbox their Howy mails come from: the first they mailed.
            $table->foreignId('home_mailbox_id')->nullable()->constrained('mailboxes')->nullOnDelete();
            $table->boolean('receives_mail')->default(true);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_mail_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->timestamp('welcomed_at')->nullable();
            $table->timestamp('explained_at')->nullable();
            $table->timestamp('first_saved_at')->nullable();
            $table->timestamp('nudged_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'email']);
        });

        Schema::create('mailbox_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            $table->foreignId('colleague_id')->nullable()->constrained()->cascadeOnDelete();
            // Lowercased, without the @.
            $table->string('domain', 253)->nullable();
            $table->string('access', 20);
            // Whether someone this domain rule lets in becomes a colleague.
            $table->boolean('creates_colleagues')->default(false);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE mailbox_rules ADD CONSTRAINT mailbox_rules_colleague_or_domain CHECK ((colleague_id IS NULL) <> (domain IS NULL))');
        DB::statement('CREATE UNIQUE INDEX mailbox_rules_one_per_colleague ON mailbox_rules (account_id, mailbox_id, colleague_id) WHERE colleague_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX mailbox_rules_one_per_domain ON mailbox_rules (account_id, mailbox_id, domain) WHERE domain IS NOT NULL');

        Schema::table('emails', function (Blueprint $table) {
            $table->foreignId('colleague_id')->nullable()->after('mailbox_id')->constrained()->nullOnDelete();
            $table->index('colleague_id');
        });

        Account::query()->orderBy('id')->each(
            fn (Account $account) => Tenancy::for($account, fn () => $this->moveRules($account)),
        );

        Schema::drop('mailbox_senders');

        Schema::table('mailboxes', function (Blueprint $table) {
            $table->dropColumn('domain_access');
        });
    }

    public function down(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->string('domain_access', 20)->default('learn_answer')->after('import_batch_id');
        });

        Schema::create('mailbox_senders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            $table->string('entry');
            $table->string('access', 20);
            $table->timestamps();

            $table->unique(['account_id', 'mailbox_id', 'entry']);
        });

        Account::query()->orderBy('id')->each(fn (Account $account) => Tenancy::for($account, function (): void {
            Mailbox::query()->orderBy('id')->each(function (Mailbox $mailbox): void {
                $own = $mailbox->isOnPublicDomain() ? null : $mailbox->domain();

                foreach ($mailbox->rules()->with('colleague')->oldest('id')->get() as $rule) {
                    if ($rule->domain !== null && $rule->domain === $own) {
                        $mailbox->forceFill(['domain_access' => $rule->access->value])->saveQuietly();

                        continue;
                    }

                    DB::table('mailbox_senders')->insert([
                        'account_id' => $mailbox->account_id,
                        'mailbox_id' => $mailbox->id,
                        'entry' => $rule->domain ?? $rule->colleague?->email,
                        'access' => $rule->access->value,
                        'created_at' => $rule->created_at,
                        'updated_at' => $rule->updated_at,
                    ]);
                }

                if ($own !== null && ! $mailbox->rules()->where('domain', $own)->exists()) {
                    $mailbox->forceFill(['domain_access' => 'ignore'])->saveQuietly();
                }
            });
        }));

        Schema::table('emails', function (Blueprint $table) {
            $table->dropConstrainedForeignId('colleague_id');
        });

        Schema::drop('mailbox_rules');
        Schema::drop('colleagues');
    }

    private function moveRules(Account $account): void
    {
        /** @var array<string, int> $members */
        $members = $account->users()->get(['users.id', 'users.email'])
            ->mapWithKeys(fn ($user): array => [strtolower($user->email) => $user->id])
            ->all();

        Mailbox::query()->orderBy('id')->each(function (Mailbox $mailbox) use ($account, $members): void {
            $own = $mailbox->isOnPublicDomain() ? null : $mailbox->domain();
            $company = array_values(array_filter([$own, $account->domain]));
            // The table, not a model: it is dropped once its rows have moved.
            $listed = DB::table('mailbox_senders')->where('mailbox_id', $mailbox->id)->orderBy('id')->get();

            if ($own !== null) {
                MailboxRule::query()->create([
                    'mailbox_id' => $mailbox->id,
                    'domain' => $own,
                    'access' => $listed->firstWhere('entry', $own)->access ?? $mailbox->getRawOriginal('domain_access'),
                    'creates_colleagues' => true,
                ]);
            }

            foreach ($listed as $row) {
                if ($row->entry === $own) {
                    continue;
                }

                if (! str_contains($row->entry, '@')) {
                    MailboxRule::query()->create([
                        'mailbox_id' => $mailbox->id,
                        'domain' => $row->entry,
                        'access' => $row->access,
                        'creates_colleagues' => in_array($row->entry, $company, true),
                    ]);

                    continue;
                }

                $colleague = Colleague::query()->firstOrCreate(['email' => $row->entry], [
                    'home_mailbox_id' => $mailbox->id,
                    'user_id' => $members[$row->entry] ?? null,
                    'receives_mail' => isset($members[$row->entry])
                        || in_array(substr((string) strrchr($row->entry, '@'), 1), $company, true),
                ]);

                MailboxRule::query()->create([
                    'mailbox_id' => $mailbox->id,
                    'colleague_id' => $colleague->id,
                    'access' => $row->access,
                ]);
            }
        });
    }
};
