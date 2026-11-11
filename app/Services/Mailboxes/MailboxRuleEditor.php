<?php

namespace App\Services\Mailboxes;

use App\Enums\SenderAccess;
use App\Models\Colleague;
use App\Models\Mailbox;
use App\Models\MailboxRule;
use App\Services\Colleagues\Colleagues;
use Illuminate\Support\Facades\DB;

/**
 * Writes a mailbox's rules. Every change is aimed: a rule is added, changed or
 * removed because someone said so, never because a list posted back lacked
 * it. Two admins, or an admin and the colleagues page, cannot wipe out each
 * other's rules.
 */
class MailboxRuleEditor
{
    public function __construct(private readonly Colleagues $colleagues) {}

    /**
     * What the rules panel saves: the access for the mailbox's own domain,
     * every address and domain set, and those removed. An address is a
     * colleague's own rule, made a colleague if they are not one yet. The own
     * domain set among the rest is the own domain's rule.
     *
     * @param  list<array{entry: string, access: SenderAccess|string, creates_colleagues?: bool|null}>  $rules
     * @param  list<string>  $removed
     */
    public function apply(Mailbox $mailbox, SenderAccess|string $domainAccess, array $rules, array $removed = []): void
    {
        $own = $mailbox->isOnPublicDomain() ? null : $mailbox->domain();
        $domainAccess = self::access($domainAccess);

        DB::transaction(function () use ($mailbox, $own, $domainAccess, $rules, $removed): void {
            $existing = $mailbox->rules()->with('colleague')->get();

            foreach ($removed as $entry) {
                if ($entry !== $own) {
                    $existing->first(fn (MailboxRule $rule): bool => $rule->entry() === $entry)?->delete();
                }
            }

            foreach ($rules as $rule) {
                $access = self::access($rule['access']);

                if ($rule['entry'] === $own) {
                    $domainAccess = $access;
                } elseif (str_contains($rule['entry'], '@')) {
                    $this->setFor($mailbox, $this->colleagues->findOrCreate($rule['entry'], $mailbox), $access);
                } else {
                    $this->setForDomain($mailbox, $rule['entry'], $access, $rule['creates_colleagues'] ?? null);
                }
            }

            if ($own !== null) {
                $this->setForDomain($mailbox, $own, $domainAccess, createsColleagues: true);
            }
        });

        $mailbox->unsetRelation('rules');
    }

    /**
     * A colleague's own rule on a mailbox. Null removes it, so the rule for
     * their domain decides again.
     */
    public function setFor(Mailbox $mailbox, Colleague $colleague, ?SenderAccess $access): void
    {
        if ($access === null) {
            $mailbox->rules()->where('colleague_id', $colleague->id)->delete();
        } else {
            $mailbox->rules()->updateOrCreate(['colleague_id' => $colleague->id], ['access' => $access]);
        }

        $mailbox->unsetRelation('rules');
    }

    /**
     * The rule for a domain on a mailbox.
     *
     * @param  bool|null  $createsColleagues  null keeps what the rule says; a new rule creates none
     */
    public function setForDomain(Mailbox $mailbox, string $domain, SenderAccess $access, ?bool $createsColleagues = null): void
    {
        $rule = $mailbox->rules()->firstOrNew(['domain' => $domain]);
        $rule->access = $access;

        if ($createsColleagues !== null) {
            $rule->creates_colleagues = $createsColleagues;
        }

        $rule->save();

        $mailbox->unsetRelation('rules');
    }

    private static function access(SenderAccess|string $access): SenderAccess
    {
        return $access instanceof SenderAccess ? $access : SenderAccess::from($access);
    }
}
