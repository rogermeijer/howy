<?php

namespace App\Http\Controllers;

use App\Models\BetaAccessRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BetaAccessRequestController extends Controller
{
    /**
     * Put an email address on the private beta list.
     *
     * Asking twice is fine and looks the same as asking once, so the form never
     * tells anyone whether an address was already on the list.
     */
    public function store(Request $request): RedirectResponse
    {
        $email = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ])['email'];

        BetaAccessRequest::query()->firstOrCreate(
            ['email' => Str::lower($email)],
            ['locale' => app()->getLocale()],
        );

        return back();
    }
}
