<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords {
        sendResetFailedResponse as sendLaravelResetFailedResponse;
    }

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * Security SEC-11: an email with no account gets the same reply as a wrong or expired token, so this form
     * can't be used to check which emails have accounts.
     */
    protected function sendResetFailedResponse(Request $request, string $response): RedirectResponse
    {
        if ($response === Password::INVALID_USER) {
            $response = Password::INVALID_TOKEN;
        }

        return $this->sendLaravelResetFailedResponse($request, $response);
    }
}
