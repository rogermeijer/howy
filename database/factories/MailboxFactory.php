<?php

namespace Database\Factories;

use App\Enums\MailboxProvider;
use App\Enums\MailboxStatus;
use App\Enums\SenderAccess;
use App\Models\Colleague;
use App\Models\Mailbox;
use App\Models\MailboxRule;
use App\Services\Mailboxes\MailboxRuleEditor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * account_id is deliberately absent: BelongsToAccount fills it from the context.
 *
 * @extends Factory<Mailbox>
 */
class MailboxFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => MailboxProvider::Gmail,
            'email_address' => fake()->unique()->userName().'@'.fake()->domainName(),
            'provider_user_id' => (string) fake()->randomNumber(9),
            'access_token' => 'ya29.test-access-token',
            'refresh_token' => '1//test-refresh-token',
            'token_expires_at' => now()->addHour(),
            'status' => MailboxStatus::Active,
            'history_id' => '1000',
            'watch_expires_at' => now()->addDays(7),
        ];
    }

    /**
     * Like a mailbox just connected: its own domain learned from and
     * answered, unless rules() says otherwise.
     */
    public function configure(): static
    {
        return $this->rules();
    }

    /**
     * Made, the mailbox knows its rules without storing them; created, they
     * are stored with it, an address as a colleague's own rule.
     *
     * @param  SenderAccess  $domain  for the mailbox's own domain, when it is not a public one
     * @param  array<string, SenderAccess>  $senders  address or domain => access
     */
    public function rules(SenderAccess $domain = SenderAccess::LearnAndAnswer, array $senders = []): static
    {
        $senders = array_map(
            fn (string $entry, SenderAccess $access): array => ['entry' => ltrim(strtolower($entry), '@'), 'access' => $access],
            array_keys($senders),
            array_values($senders),
        );

        return $this
            ->afterMaking(function (Mailbox $mailbox) use ($domain, $senders): void {
                $rules = $mailbox->isOnPublicDomain() ? [] : [$mailbox->domain() => new MailboxRule([
                    'domain' => $mailbox->domain(), 'access' => $domain, 'creates_colleagues' => true,
                ])];

                foreach ($senders as $sender) {
                    $rules[$sender['entry']] = str_contains($sender['entry'], '@')
                        ? (new MailboxRule(['access' => $sender['access']]))->setRelation('colleague', new Colleague(['email' => $sender['entry']]))
                        : new MailboxRule(['domain' => $sender['entry'], 'access' => $sender['access']]);
                }

                $mailbox->setRelation('rules', new Collection(array_map(
                    fn (MailboxRule $rule): MailboxRule => $rule->relationLoaded('colleague') ? $rule : $rule->setRelation('colleague', null),
                    array_values($rules),
                )));
            })
            ->afterCreating(fn (Mailbox $mailbox) => app(MailboxRuleEditor::class)->apply($mailbox, $domain, $senders));
    }

    public function needsReauth(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MailboxStatus::NeedsReauth,
        ]);
    }

    public function disconnected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MailboxStatus::Disconnected,
            'access_token' => null,
            'refresh_token' => null,
            'watch_expires_at' => null,
        ]);
    }
}
