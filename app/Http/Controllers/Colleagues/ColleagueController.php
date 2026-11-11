<?php

namespace App\Http\Controllers\Colleagues;

use App\Enums\EmailIntent;
use App\Enums\InterpretationOutcome;
use App\Enums\OutboundMailStatus;
use App\Enums\SenderAccess;
use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Colleagues\ColleagueAccessRequest;
use App\Http\Requests\Colleagues\StoreColleagueRequest;
use App\Http\Requests\Colleagues\UpdateColleagueRequest;
use App\Models\Colleague;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\MailboxRule;
use App\Models\OutboundMail;
use App\Services\Adoption\FlowSender;
use App\Services\Adoption\FollowUps;
use App\Services\Adoption\Welcomes;
use App\Services\Colleagues\Colleagues;
use App\Services\Mail\Interpretation\InterpretationPresenter;
use App\Services\Mailboxes\MailboxRuleEditor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The people who mail Howy, for an account's admins: what Howy may do with
 * their mail, where their Howy mails stand, and what they did. Admins see
 * activity per person; see docs/adoption-mails.md on what mails may promise
 * because of that.
 */
class ColleagueController extends Controller
{
    /**
     * Where someone's Howy mails stand, as the list filters them: unsubscribed,
     * switched off by an admin, ignored by the rules (so none go out), or else
     * welcomed or not yet.
     */
    private const array STATUSES = ['not_welcomed', 'welcomed', 'ignored', 'opted_out', 'off'];

    public function index(Request $request): Response
    {
        $search = trim($request->string('q')->toString());
        $status = in_array($request->query('status'), self::STATUSES, true) ? $request->string('status')->toString() : null;
        $access = SenderAccess::tryFrom($request->string('access')->toString());
        $mailboxes = $this->mailboxes();

        $colleagues = Colleague::query()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")))
            ->when($status !== null, fn (Builder $query) => $this->whereStatus($query, (string) $status))
            ->when($access !== null, fn (Builder $query) => $this->whereAccess($query, $access ?? SenderAccess::Ignore))
            ->withCount($this->activity())
            ->orderByRaw('last_mail_at desc nulls last')
            ->orderBy('email')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Colleague $colleague): array => $this->present($colleague, $mailboxes));

        return Inertia::render('colleagues/index', [
            'colleagues' => $colleagues,
            'filters' => ['q' => $search, 'status' => $status, 'access' => $access?->value],
            'total' => Colleague::query()->count(),
            'mailboxes' => $this->mailboxOptions($mailboxes),
            // Live, adding someone offers to send them the welcome.
            'isLive' => Tenancy::account()->isLive(),
        ]);
    }

    public function show(Colleague $colleague, InterpretationPresenter $presenter, Welcomes $welcomes, FollowUps $followUps): Response
    {
        $colleague->loadCount($this->activity())->load('user:id,name,email');
        $mailboxes = $this->mailboxes();

        return Inertia::render('colleagues/show', [
            'colleague' => [
                ...$this->present($colleague, $mailboxes),
                'homeMailboxId' => $colleague->home_mailbox_id,
                'user' => $colleague->user !== null ? ['name' => $colleague->user->name, 'email' => $colleague->user->email] : null,
                'firstSeenAt' => $colleague->first_seen_at?->toIso8601String(),
                'explainedAt' => $colleague->explained_at?->toIso8601String(),
                'firstSavedAt' => $colleague->first_saved_at?->toIso8601String(),
                'nudgedAt' => $colleague->nudged_at?->toIso8601String(),
                'optedOutAt' => $colleague->opted_out_at?->toIso8601String(),
            ],
            'access' => $mailboxes->map(fn (Mailbox $mailbox): array => $this->accessOn($mailbox, $colleague))->values()->all(),
            'emails' => $colleague->emails()
                ->with('interpretation')
                ->orderByRaw('received_at desc nulls last')
                ->limit(25)
                ->get()
                ->map(fn (Email $email): array => [
                    'id' => $email->id,
                    'subject' => $email->subject,
                    'receivedAt' => $email->received_at?->toIso8601String(),
                    'interpretation' => $email->interpretation !== null ? $presenter->brief($email->interpretation) : null,
                ])
                ->all(),
            // Per kind of Howy's own mail, the latest that went or failed: the
            // one to send again, written afresh.
            'resendable' => $colleague->outboundMails()
                ->latest('id')
                ->get(['id', 'kind', 'status', 'colleague_id'])
                ->filter(fn (OutboundMail $mail): bool => FlowSender::resendable($mail))
                ->unique(fn (OutboundMail $mail): string => $mail->kind->value)
                ->mapWithKeys(fn (OutboundMail $mail): array => [$mail->kind->value => $mail->id])
                ->all(),
            // When the follow-ups to the welcome are due, as things stand.
            'planned' => array_map(
                fn (?Carbon $at): ?string => $at?->toIso8601String(),
                $followUps->plannedFor($colleague, $mailboxes->get((int) $colleague->home_mailbox_id), Tenancy::account()),
            ),
            // Howy's own mails to them that are on their way, not sent yet.
            'onTheirWay' => $colleague->outboundMails()
                ->where('status', OutboundMailStatus::Pending)
                ->get(['id', 'kind'])
                ->map(fn (OutboundMail $mail): string => $mail->kind->value)
                ->unique()
                ->values()
                ->all(),
            // What Howy sent them, newest first.
            'sent' => $colleague->outboundMails()
                ->with(SentMailController::loads())
                ->latest('id')
                ->limit(25)
                ->get()
                ->map(fn (OutboundMail $mail): array => SentMailController::present($mail))
                ->all(),
            'mailboxes' => $this->mailboxOptions($mailboxes),
            // The welcome by hand, for someone who may get it and has not.
            'canWelcome' => Tenancy::account()->isLive() && $welcomes->mayReceive($colleague) && $welcomes->awaits($colleague),
            // Not live, nothing of Howy's own goes out.
            'isLive' => Tenancy::account()->isLive(),
            // How many working days after the welcome each follow-up comes.
            'followUpDays' => [
                'how_it_works' => (int) config('adoption.how_it_works_after_workdays'),
                'nudge' => (int) config('adoption.nudge_after_workdays'),
            ],
        ]);
    }

    /**
     * Someone who has not mailed Howy yet, added by hand: they get Howy's own
     * mails unless the admin says not.
     */
    public function store(StoreColleagueRequest $request, Colleagues $colleagues, MailboxRuleEditor $rules, Welcomes $welcomes): RedirectResponse
    {
        $mailbox = Mailbox::query()->findOrFail($request->integer('home_mailbox_id'));

        $colleague = DB::transaction(function () use ($request, $colleagues, $rules, $mailbox): Colleague {
            $colleague = $colleagues->findOrCreate(
                $request->string('email')->toString(),
                $mailbox,
                $request->string('name')->toString(),
                receivesMail: $request->boolean('receives_mail', true),
            );

            if ($request->access() !== null) {
                $rules->setFor($mailbox, $colleague, $request->access());
            }

            return $colleague;
        });

        $welcomed = $request->boolean('send_welcome') && $welcomes->send($colleague);

        Inertia::flash('toast', ['type' => 'success', 'message' => $welcomed
            ? __(':name is a colleague now. The welcome mail is on its way.', ['name' => $colleague->displayName()])
            : __(':name is a colleague now.', ['name' => $colleague->displayName()])]);

        return to_route('colleagues.show', $colleague);
    }

    /**
     * The welcome, sent by hand: to someone who may get it and has not had it.
     */
    public function welcome(Colleague $colleague, Welcomes $welcomes): RedirectResponse
    {
        Inertia::flash('toast', $welcomes->send($colleague)
            ? ['type' => 'success', 'message' => __('The welcome mail is on its way.')]
            : ['type' => 'error', 'message' => __('Howy cannot send them the welcome now.')]);

        return back();
    }

    /**
     * Their name, the switch or home mailbox, and what Howy does with their
     * mail per mailbox: each from where it shows, or together from the dialog.
     */
    public function update(UpdateColleagueRequest $request, Colleague $colleague, MailboxRuleEditor $rules): RedirectResponse
    {
        DB::transaction(function () use ($request, $colleague, $rules): void {
            $colleague->update($request->colleague());

            foreach ($request->accessPerMailbox() as $mailbox => $access) {
                $rules->setFor(Mailbox::query()->findOrFail($mailbox), $colleague, $access);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return back();
    }

    /**
     * What Howy may do with their mail on one mailbox: a rule of their own,
     * or none, so the rule for their domain decides.
     */
    public function updateAccess(ColleagueAccessRequest $request, Colleague $colleague, Mailbox $mailbox, MailboxRuleEditor $rules): RedirectResponse
    {
        $rules->setFor($mailbox, $colleague, $request->access());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return back();
    }

    /**
     * Their mails, the questions among them, and those that added to the
     * knowledge base.
     *
     * @return array<int|string, string|\Closure>
     */
    private function activity(): array
    {
        return [
            'emails',
            'emails as questions_count' => fn (Builder $query) => $query->whereHas('interpretation', fn (Builder $query) => $query->where('intent', EmailIntent::Question)),
            'emails as contributions_count' => fn (Builder $query) => $query->whereHas('interpretation', fn (Builder $query) => $query->where('outcome', InterpretationOutcome::Added)),
        ];
    }

    /**
     * @param  Collection<int, Mailbox>  $mailboxes
     * @return array<string, mixed>
     */
    private function present(Colleague $colleague, Collection $mailboxes): array
    {
        $home = $mailboxes->get((int) $colleague->home_mailbox_id);
        $rule = $home?->ruleFor($colleague->email);
        $access = $rule->access ?? SenderAccess::Ignore;

        return [
            'id' => $colleague->id,
            'email' => $colleague->email,
            'name' => $colleague->name,
            'isMember' => $colleague->user_id !== null,
            'homeMailbox' => $home?->email_address,
            'access' => $access->value,
            'accessFrom' => $rule === null ? 'none' : ($rule->colleague_id !== null ? 'own' : 'domain'),
            'receivesMail' => $colleague->receives_mail,
            'status' => $this->status($colleague, $access),
            'welcomedAt' => $colleague->welcomed_at?->toIso8601String(),
            'lastMailAt' => $colleague->last_mail_at?->toIso8601String(),
            'emailsCount' => (int) $colleague->getAttribute('emails_count'),
            'questionsCount' => (int) $colleague->getAttribute('questions_count'),
            'contributionsCount' => (int) $colleague->getAttribute('contributions_count'),
        ];
    }

    private function status(Colleague $colleague, SenderAccess $access): string
    {
        return match (true) {
            $colleague->opted_out_at !== null => 'opted_out',
            ! $colleague->receives_mail => 'off',
            $access === SenderAccess::Ignore => 'ignored',
            $colleague->welcomed_at === null => 'not_welcomed',
            default => 'welcomed',
        };
    }

    /**
     * @param  Builder<Colleague>  $query
     */
    private function whereStatus(Builder $query, string $status): void
    {
        if ($status === 'opted_out') {
            $query->whereNotNull('opted_out_at');

            return;
        }

        $query->whereNull('opted_out_at');

        if ($status === 'off') {
            $query->where('receives_mail', false);

            return;
        }

        $query->where('receives_mail', true);

        if ($status === 'ignored') {
            $this->whereAccess($query, SenderAccess::Ignore);

            return;
        }

        $query->whereNot(fn (Builder $query) => $this->whereAccess($query, SenderAccess::Ignore))
            ->when($status === 'welcomed', fn (Builder $query) => $query->whereNotNull('welcomed_at'), fn (Builder $query) => $query->whereNull('welcomed_at'));
    }

    /**
     * What Howy may do with their mail on their home mailbox, as
     * Mailbox::access() decides it: their own rule there, else the rule for
     * their domain, else ignored.
     *
     * @param  Builder<Colleague>  $query
     */
    private function whereAccess(Builder $query, SenderAccess $access): void
    {
        $own = fn (Builder $rules): Builder => $rules->whereColumn('mailbox_rules.mailbox_id', 'colleagues.home_mailbox_id');
        $domain = fn (): Builder => MailboxRule::query()
            ->whereColumn('mailbox_rules.mailbox_id', 'colleagues.home_mailbox_id')
            ->whereRaw("mailbox_rules.domain = split_part(colleagues.email, '@', 2)");

        $query->where(fn (Builder $query) => $query
            ->whereHas('rules', fn (Builder $rules) => $own($rules)->where('access', $access))
            ->orWhere(function (Builder $query) use ($own, $domain, $access): void {
                $query->whereDoesntHave('rules', $own);

                $access === SenderAccess::Ignore
                    ? $query->whereNotExists($domain()->where('access', '!=', SenderAccess::Ignore))
                    : $query->whereExists($domain()->where('access', $access));
            }));
    }

    /**
     * Their rule on one mailbox, the rule for their domain there, and what
     * the two come to.
     *
     * @return array<string, mixed>
     */
    private function accessOn(Mailbox $mailbox, Colleague $colleague): array
    {
        $own = $mailbox->rules->first(fn (MailboxRule $rule): bool => $rule->colleague_id === $colleague->id);
        $domain = $mailbox->rules->first(fn (MailboxRule $rule): bool => $rule->domain === $colleague->domain());

        return [
            'mailboxId' => $mailbox->id,
            'emailAddress' => $mailbox->email_address,
            'status' => $mailbox->status->value,
            'own' => $own?->access->value,
            'domain' => $domain !== null ? ['domain' => $domain->domain, 'access' => $domain->access->value] : null,
            'access' => $mailbox->access($colleague->email)->value,
        ];
    }

    /**
     * @return Collection<int, Mailbox>
     */
    private function mailboxes(): Collection
    {
        return Mailbox::query()->with('rules.colleague')->orderBy('email_address')->get()->keyBy('id');
    }

    /**
     * @param  Collection<int, Mailbox>  $mailboxes
     * @return list<array{id: int, emailAddress: string}>
     */
    private function mailboxOptions(Collection $mailboxes): array
    {
        return array_values($mailboxes->map(fn (Mailbox $mailbox): array => ['id' => $mailbox->id, 'emailAddress' => $mailbox->email_address])->all());
    }
}
