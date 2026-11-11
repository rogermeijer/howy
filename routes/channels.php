<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * One private channel per account, for its members, and no other channel.
 * What goes over it is a topic, never data: a page that hears one reloads its
 * own props through the usual scoped requests. See "Broadcasting" in
 * docs/multi-tenancy.md.
 */
Broadcast::channel('account.{account}', fn (User $user, int $account): bool => $user->belongsToAccount($account));
