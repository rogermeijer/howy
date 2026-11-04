<?php

use App\Enums\SendPolicy;
use App\Facades\Tenancy;
use App\Models\Mailbox;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The send policy now says whom Howy reads mail from, not only whom it
     * answers, and replying is a switch of its own. The default is the same
     * domain; on a public domain such as gmail.com that would be everyone, so
     * those mailboxes read from a whitelist that starts with whoever connected
     * them. "Off" becomes the same domain with replies off.
     */
    public function up(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->boolean('replies_enabled')->default(true)->after('send_blacklist');
            $table->string('send_policy', 20)->default('domain')->change();
        });

        // Every account's mailboxes, on purpose: a migration has no tenant.
        Tenancy::withoutTenancy(function (): void {
            // Before any mailbox is read back: "off" no longer casts.
            Mailbox::query()->where('send_policy', 'off')->update(['send_policy' => 'domain', 'replies_enabled' => false]);
            Mailbox::query()->where('send_policy', 'always')->update(['send_policy' => 'domain']);

            Mailbox::query()->with('connectedBy')->where('send_policy', 'domain')->get()
                ->filter(fn (Mailbox $mailbox): bool => $mailbox->isOnPublicDomain())
                ->each(fn (Mailbox $mailbox) => $mailbox->forceFill([
                    'send_policy' => SendPolicy::Whitelist,
                    'send_whitelist' => array_values(array_unique(array_filter([
                        ...($mailbox->send_whitelist ?? []),
                        $mailbox->connectedBy !== null ? strtolower($mailbox->connectedBy->email) : null,
                    ]))),
                ])->save());
        });
    }

    public function down(): void
    {
        Tenancy::withoutTenancy(fn () => Mailbox::query()->where('replies_enabled', false)->update(['send_policy' => 'off']));

        Schema::table('mailboxes', function (Blueprint $table) {
            $table->dropColumn('replies_enabled');
            $table->string('send_policy', 20)->default('always')->change();
        });
    }
};
