# PRD: Two-factor sign-in (SEC-06)

Status: Draft 1, 2026-10-23. Not approved. No code exists.
Source: security audit finding SEC-06 (the audit report is confidential and not in this repository).

## 1. Problem

A password is the only protection for every account. Each role can see private data:
- An admin sees and exports every child's record.
- A teacher sees the rosters of the assigned divisions.
- A parent sees a child's attendance and results.
- A student sees personal records.

One stolen or reused password gives access to that data.

## 2. Goal

Every account needs a second factor at sign-in. The second factor is a code from an authenticator app (TOTP). The user can use a one-time recovery code instead.

The app also asks an admin for the password again before three sensitive actions.

## 3. Decisions made (2026-10-23)

| Topic | Decision |
|---|---|
| Package | `pragmarx/google2fa`. It makes and checks the codes. The app has its own pages and controllers. |
| Roles | All four roles (admin, teacher, student, parent) must use 2FA. |
| Student method | Authenticator app, the same as the other roles. |
| Lost phone | The user signs in with a recovery code. If the user has no code, the reset is by command line (any role) or by an admin (non-admin roles only). |
| Password again | CSV export, account deactivation, and setting a password for another person. |

## 4. Non-goals

- NG1: Email codes, SMS codes, hardware keys and passkeys.
- NG2: "Trust this device" or "skip 2FA for 30 days".
- NG3: A self-service reset by email for a lost 2FA device.
- NG4: The audit log of 2FA events (SEC-13).
- NG5: The Remember Me policy (SEC-21) and remember-token rotation (SEC-23). They are separate items.
- NG6: A Change Password page and forced password change (SEC-08).
- NG7: Custom roles. The rule applies to the four fixed roles.

## 5. Users and flows

### 5.1 First sign-in after the feature is on
1. The user enters email and password. The password is correct.
2. The user has no 2FA yet. The app sends the user to a set-up page. No other page is open.
3. The page shows a QR code and the secret as text.
4. The user scans the code and enters a 6-digit code from the app.
5. The app checks the code. If it is correct, the app turns on 2FA and shows 8 recovery codes once.
6. The user confirms that the codes are saved. The user goes to the dashboard.

### 5.2 Normal sign-in
1. The user enters email and password. The password is correct.
2. The app asks for a 6-digit code. The user is not yet signed in.
3. The user enters a correct code. The app signs the user in.
4. The user can enter a recovery code instead. Each recovery code works once.

### 5.3 Password again (admin only)
1. An admin starts a CSV export, deactivates an account, or sets a password for another person.
2. The app asks for the admin's password if it was not confirmed in the last 15 minutes.
3. After the correct password, the action continues.

### 5.4 Lost device
- The user has a recovery code: the user signs in with it. The app tells the user to make new codes.
- The user has no code, and the user is a teacher, student or parent: an admin resets the 2FA of that account. The admin enters the admin password first.
- The user is an admin and has no code: a server operator runs an artisan command. No admin can reset another admin.
- After a reset, the user sets up 2FA again at the next sign-in (flow 5.1).

## 6. Functional requirements

| ID | Requirement |
|---|---|
| FR-2FA-01 | The system stores a TOTP secret for each login. The secret is encrypted in the database. |
| FR-2FA-02 | The system stores recovery codes as hashes. It shows them in clear text one time only. |
| FR-2FA-03 | A signed-in user with no 2FA can open only the set-up page and logout. |
| FR-2FA-04 | The system turns on 2FA only after the user enters one correct code. |
| FR-2FA-05 | After a correct password, a user with 2FA is not signed in until a correct code or recovery code is entered. |
| FR-2FA-06 | A TOTP code works once. The system rejects a code that it already accepted. |
| FR-2FA-07 | A recovery code works once. The system marks it as used. |
| FR-2FA-08 | The system limits wrong codes: 5 a minute for each account and IP address. The reply to the user does not say whether the code or the account was wrong. |
| FR-2FA-09 | The code page works only with the "pending sign-in" state in the session. The state ends after 10 minutes. |
| FR-2FA-10 | The password-reset flow does not sign the user in. After a reset, the user must still pass the code step. |
| FR-2FA-11 | A user can make a new set of recovery codes after the user enters the password. The old codes stop working. |
| FR-2FA-12 | An admin can reset the 2FA of a teacher, student or parent account after the admin enters the admin password. The system refuses to reset an admin account. |
| FR-2FA-13 | An artisan command resets the 2FA of any account by email. It does not print secrets. |
| FR-2FA-14 | The system asks an admin for the password before CSV export, account deactivation, and setting a password for another person. It uses the Laravel `password.confirm` middleware. |
| FR-2FA-15 | The `create-admin` command still prints a generated password one time. The new admin sets up 2FA at first sign-in. |
| FR-2FA-16 | A reset of 2FA (by admin or by command) ends all sessions of that account. |
| FR-2FA-17 | A deactivated account cannot sign in, with or without 2FA. This rule does not change. |
| FR-2FA-19 | A config switch `TWO_FACTOR_REQUIRED` (default true) turns the 2FA requirement on or off. When `APP_ENV=production`, the system ignores a false value and keeps 2FA on. |
| FR-2FA-20 | A password confirmation for the sensitive actions in FR-2FA-14 and FR-2FA-12 is valid for 15 minutes. |
| FR-2FA-21 | The set-up page shows help text that says a parent or a school device can hold the authenticator app. |
| FR-2FA-22 | When a user has fewer than 3 unused recovery codes, the user's dashboard shows a text notice with a link to make new codes. |
| FR-2FA-18 | The new pages follow the UI conventions in `CLAUDE.md` (labels, headings, Title Case, dark mode, phone layout, CSP, no outside servers). The QR code is made on the server with no outside request. |

## 7. Edge cases

- The user enters the correct password but closes the browser at the code step. No session is created.
- The phone clock is wrong. The system accepts a code from one 30-second step before or after the current step.
- Two users share one device. The pending state belongs to one session only.
- An admin opens a confirm-protected action by a direct URL. The app redirects to the password page, then back.
- A user with Remember Me opens the app after the feature is on. The remember cookie must not skip the code step for the first sign-in on a new session. Check in the technical design (SEC-21 changes the policy later).
- A parent has many children. The parent has one login, so the parent has one 2FA.
- A user has 2FA on and the admin deactivates the account, then activates it again. The 2FA stays on.

## 8. Security requirements

- Do not keep the secret, the code or recovery codes in logs, sessions after use, or form input flashed back (see the log redaction and session rules in `CLAUDE.md`). The code field name must be in `dontFlash`.
- Never show the secret again after set-up.
- All new routes need `auth` and a `role:` middleware, except the code page. The code page is a public route on purpose. Add it to the list in `RouteProtectionTest` on purpose.
- Use constant-time comparison for codes.

## 9. Acceptance criteria (product level)

- A user of each role cannot reach any page after sign-in until 2FA is set up.
- A user with 2FA cannot reach any page with only the password.
- A wrong code, a used code, and a used recovery code do not sign the user in.
- The sixth wrong code in one minute gets the "too many attempts" reply.
- A non-admin account is reset by an admin. An admin account is not.
- CSV export, deactivation, and setting a password for another person each redirect to the password page when the password is not confirmed.
- A Pest test covers each item above. The full suite and the axe checks pass.
- No page contacts an outside server.

## 10. Open questions (all closed on 2026-10-23)

| ID | Question | Decision |
|---|---|---|
| OQ-01 | Does the demo data (`LoginSeeder`, AWS demo, `tests/e2e`) need 2FA? | Add a config switch `TWO_FACTOR_REQUIRED`, default true. Turn it off only for local demo and e2e runs. It must be impossible to turn off when `APP_ENV=production`. See FR-2FA-19. |
| OQ-02 | How long does a password confirmation stay valid? | 15 minutes for these actions. See FR-2FA-20. |
| OQ-03 | Does the admin reset of a teacher, student or parent need the admin's password? | Yes. See FR-2FA-12. |
| OQ-04 | Does the code step need a design for children under 13? | Same flow. The set-up page shows help text that a parent or a school device can hold the app. See FR-2FA-21. |
| OQ-05 | Does the system warn when few recovery codes remain? | Yes. A text notice shows on the dashboard when fewer than 3 remain. See FR-2FA-22. |

## 11. Out of scope for this PRD, but linked

SEC-08 (Change Password page), SEC-13 (audit log), SEC-21 (Remember Me), SEC-23 (remember-token rotation).
