<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /**
     * Security SEC-19: reset-link requests go through the "password-reset" limiter (see AppServiceProvider).
     */
    public function __construct()
    {
        $this->middleware('throttle:password-reset')->only('sendResetLinkEmail');
    }

    /**
     * Security SEC-20: only active accounts get a reset email. A deactivated one is treated like an unknown email, so
     * it gets the same reply (SEC-11).
     *
     * @return array<string, mixed>
     */
    protected function credentials(Request $request): array
    {
        return [...$request->only('email'), 'is_active' => true];
    }

    /**
     * Security SEC-11: an email with no account, or a repeat request inside the throttle window, gets the same
     * reply as a sent link, so this form can't be used to check which emails have accounts.
     */
    protected function sendResetLinkFailedResponse(Request $request, string $response): RedirectResponse|JsonResponse
    {
        return $this->sendResetLinkResponse($request, Password::RESET_LINK_SENT);
    }
}
