<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBetaInviteRequest;
use App\Mail\BetaInvite;
use App\Models\BetaAccessRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The private beta list. Every request already has its code; sending the
 * invite is the only thing that tells the person.
 */
class BetaAccessController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'invited', 'registered'])],
        ])['status'] ?? null;

        $invites = BetaAccessRequest::query()
            ->with('user:id,name')
            ->when($status === 'pending', fn ($query) => $query->whereNull('invited_at')->whereNull('redeemed_at'))
            ->when($status === 'invited', fn ($query) => $query->whereNotNull('invited_at')->whereNull('redeemed_at'))
            ->when($status === 'registered', fn ($query) => $query->whereNotNull('redeemed_at'))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (BetaAccessRequest $invite): array => [
                'id' => $invite->id,
                'email' => $invite->email,
                'locale' => $invite->locale,
                'code' => $invite->code,
                'status' => $invite->status(),
                'created_at' => $invite->created_at?->toIso8601String(),
                'invited_at' => $invite->invited_at?->toIso8601String(),
                'redeemed_at' => $invite->redeemed_at?->toIso8601String(),
                'user' => $invite->user === null ? null : ['id' => $invite->user->id, 'name' => $invite->user->name],
            ]);

        return Inertia::render('admin/beta/index', [
            'invites' => $invites,
            'counts' => [
                'all' => BetaAccessRequest::query()->count(),
                'pending' => BetaAccessRequest::query()->whereNull('invited_at')->whereNull('redeemed_at')->count(),
                'invited' => BetaAccessRequest::query()->whereNotNull('invited_at')->whereNull('redeemed_at')->count(),
                'registered' => BetaAccessRequest::query()->whereNotNull('redeemed_at')->count(),
            ],
            'filters' => ['status' => $status],
            'locales' => Options::locales(),
        ]);
    }

    /**
     * Add someone to the list by hand, e.g. a team met in person. They get a
     * code like everyone else; sending it is a separate step.
     */
    public function store(StoreBetaInviteRequest $request): RedirectResponse
    {
        $invite = BetaAccessRequest::submit($request->string('email')->toString(), $request->string('locale')->toString());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $invite->wasRecentlyCreated
                ? __(':email added with code :code.', ['email' => $invite->email, 'code' => $invite->code])
                : __(':email was already on the list.', ['email' => $invite->email]),
        ]);

        return back();
    }

    public function send(BetaAccessRequest $invite): RedirectResponse
    {
        if (! $invite->isRedeemable()) {
            return back()->withErrors(['invite' => __('This code has already been used.')]);
        }

        Mail::to($invite->email)->locale($invite->locale ?? 'en')->send(new BetaInvite($invite));

        $invite->forceFill(['invited_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invite sent to :email.', ['email' => $invite->email])]);

        return back();
    }

    public function destroy(BetaAccessRequest $invite): RedirectResponse
    {
        $invite->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request deleted.')]);

        return back();
    }
}
