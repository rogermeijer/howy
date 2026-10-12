<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Accounts\CreateAccount;
use App\Actions\Accounts\DeleteAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroyAccountRequest;
use App\Http\Requests\Admin\StoreAccountRequest;
use App\Http\Requests\Admin\UpdateAccountRequest;
use App\Models\Account;
use App\Models\User;
use App\Services\Admin\AiUsageFilters;
use App\Services\Admin\AiUsageLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accounts across the platform. Account is the tenant itself, not tenant-owned,
 * so it is read and bound here without any bypass.
 */
class AccountController extends Controller
{
    public function index(Request $request, AiUsageLog $usage): Response
    {
        $search = trim((string) $request->query('q', ''));

        $costs = collect($usage->breakdown(AiUsageFilters::lastDays(30), 'account', limit: null))->keyBy('key');

        $accounts = Account::query()
            ->when($search !== '', fn ($query) => $query->whereLike('name', "%{$search}%"))
            ->withCount('users')
            ->with(['users' => fn ($query) => $query->wherePivot('is_admin', true)->select(['users.id', 'users.name'])])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'locale' => $account->locale->value,
                'timezone' => $account->timezone,
                'members_count' => (int) $account->getAttribute('users_count'),
                'admins' => $account->users->pluck('name')->all(),
                'ai_calls' => $costs->get((string) $account->id)['calls'] ?? 0,
                'ai_cost_micros' => $costs->get((string) $account->id)['cost_micros'] ?? 0,
                'created_at' => $account->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/accounts/index', [
            'accounts' => $accounts,
            'filters' => ['q' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/accounts/create', [
            'locales' => Options::locales(),
            'timezones' => Options::timezones(),
            'defaultTimezone' => (string) config('app.timezone'),
        ]);
    }

    public function store(StoreAccountRequest $request, CreateAccount $create): RedirectResponse
    {
        $owner = User::query()->where('email', $request->string('owner_email'))->sole();

        $account = $create->handle(
            $owner,
            $request->string('name')->toString(),
            $request->string('locale')->toString(),
            $request->string('timezone')->toString(),
            activate: false,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account created.')]);

        return to_route('admin.accounts.show', $account);
    }

    public function show(Account $account, AiUsageLog $usage): Response
    {
        $filters = AiUsageFilters::lastDays(30, $account->id);

        return Inertia::render('admin/accounts/show', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'locale' => $account->locale->value,
                'timezone' => $account->timezone,
                'created_at' => $account->created_at?->toIso8601String(),
            ],
            'members' => $account->users()->orderBy('name')->get()->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => (bool) $user->getAttribute('pivot')->is_admin,
                'is_super_admin' => $user->isSuperAdmin(),
                'joined_at' => $user->getAttribute('pivot')->created_at?->toIso8601String(),
            ])->all(),
            'usage' => [
                'totals' => $usage->totals($filters),
                'steps' => $usage->breakdown($filters, 'step'),
            ],
            'locales' => Options::locales(),
            'timezones' => Options::timezones(),
        ]);
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $account->fill($request->validated())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account updated.')]);

        return to_route('admin.accounts.show', $account);
    }

    public function destroy(DestroyAccountRequest $request, Account $account, DeleteAccount $delete): RedirectResponse
    {
        $delete->handle($account);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account deleted.')]);

        return to_route('admin.accounts.index');
    }
}
