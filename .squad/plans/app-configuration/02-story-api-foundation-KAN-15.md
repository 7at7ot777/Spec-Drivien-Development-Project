# Story 02 — API foundation: /api/v1, Sanctum, JSON response format, error handling (Story: KAN-15)

## Prerequisites

- Story 01 completed: [01-story-project-setup-KAN-14.md](01-story-project-setup-KAN-14.md). MySQL `sdd_project` is migrated, the repo is on `main` and tracks `origin/main`.
- **Dependency change approved by this story:** installing `laravel/sanctum` (acceptance criterion "Laravel Sanctum token auth installed").
- **Frontend contract:** the JSON envelope below is the contract the frontend team consumes. Share the README "API" section with them once merged. Changing the envelope later is a **breaking change**.

---

## Story Goal

Give every future API endpoint one shared base:

1. All API routes live in `routes/api.php` and are served under **`/api/v1`**.
2. **Laravel Sanctum** is installed. `User` can issue tokens, and `auth:sanctum` protects routes with `Authorization: Bearer <token>`.
3. **One JSON envelope** for every API response, whether success, validation error or other error.
4. **Every exception** on an `api/*` request renders as JSON in that envelope (400, 401, 403, 404, 405, 422, 429, 500, …), **never HTML** and never a redirect.
5. **One pagination format** for every list endpoint, produced by one helper.
6. Two reference endpoints prove the base works: `GET /api/v1/ping` (public) and `GET /api/v1/me` (requires a token).

**Out of scope:**
- Login, logout and token issuing endpoints: **KAN-23**. This story only installs Sanctum and adds `HasApiTokens`.
- Roles and permissions: **KAN-20**.
- Rate limiting and throttle tuning: **KAN-58**. **Do not** add `throttle` middleware here.
- Localized error messages (ar/en): **KAN-16**. Messages here are English literals.
- API docs for the frontend team: **KAN-18**. Only the README section is written here.
- **Web/Inertia routes are untouched.** Their errors keep rendering HTML or Inertia pages.

### Response format (contract)

Success:

```json
{
  "success": true,
  "message": null,
  "data": { "id": 1, "name": "Jane" }
}
```

Paginated success. `data` is always a list, and `meta` and `links` are always present:

```json
{
  "success": true,
  "message": null,
  "data": [ { "id": 1 }, { "id": 2 } ],
  "meta": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3, "from": 1, "to": 15 },
  "links": { "first": "…?page=1", "last": "…?page=3", "prev": null, "next": "…?page=2" }
}
```

Error (all statuses):

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": { "email": ["The email field is required."] }
}
```

`errors` is an object of `field => string[]` for **422 only**. For every other status, `errors` is `null`.

| Status | Exception (after Laravel's `prepareException`) | `message` |
|---|---|---|
| 401 | `Illuminate\Auth\AuthenticationException` | `"Unauthenticated."` |
| 403 | `AccessDeniedHttpException` (from `AuthorizationException`) | the exception message, or `"Forbidden."` when empty or the default "This action is unauthorized." |
| 404 | `NotFoundHttpException` (also from `ModelNotFoundException`) | `"Resource not found."` (**never** the model class name) |
| 405 | `MethodNotAllowedHttpException` | `"Method not allowed."` |
| 422 | `Illuminate\Validation\ValidationException` | `"The given data was invalid."` |
| 429 | `TooManyRequestsHttpException` / `ThrottleRequestsException` | `"Too many requests."` (keeps the `Retry-After` header) |
| other 4xx/5xx `HttpExceptionInterface` | — | the exception message, or the Symfony status text when empty |
| 500 | any other `Throwable` | `"Server error."` when `app.debug` is false; the real message when true |

---

## Context — Read These Files First

1. `bootstrap/app.php` — whole file (32 lines).
   - `withRouting` (**lines 13–17**) registers only `web`, `commands` and `health`.
   - `withMiddleware` (**lines 18–27**) appends web middleware only.
   - `withExceptions` (**lines 28–31**) already calls `shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson())`. **Keep that call**, and add the `render` callback after it.
2. `vendor/laravel/framework/src/Illuminate/Foundation/Configuration/ApplicationBuilder.php` — `withRouting` signature, **lines 152–160**. It takes `string $apiPrefix = 'api'`, and **line 216** wraps the API file in `Route::middleware('api')->prefix($apiPrefix)`. So `apiPrefix: 'api/v1'` is the whole versioning mechanism.
3. `vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php`:
   - `render()`, **lines 705–733**: `prepareException()` (**line 721**) runs **before** `renderViaCallbacks()` (**line 723**). That's why the render callback sees `NotFoundHttpException` instead of `ModelNotFoundException`, and `AccessDeniedHttpException` / `HttpException` instead of `AuthorizationException`.
   - `prepareException()`, **lines 769–785**: the full mapping list, including `TokenMismatchException` → 419 and `RequestExceptionInterface` → 400.
4. `app/Models/User.php` — `use HasFactory, HasTeams, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;` at **line 42**. The imports are at **lines 5–17**, the class docblock at **lines 19–36**, and the `#[Fillable]` / `#[Hidden]` attributes at **lines 37–38**.
5. `app/Http/Responses/` — already holds Fortify response classes (`LoginResponse.php`, `RegisterResponse.php`, …). The new `ApiResponse` and `ApiExceptionRenderer` go here because they build responses. `app/Http/Resources/` does **not** exist yet. Create it, as the Story 01 README conventions require.
6. `app/Http/Middleware/HandleAppearance.php` — middleware style reference: namespace `App\Http\Middleware`, `handle(Request $request, Closure $next): Response`.
7. `tests/Feature/Teams/TeamTest.php` **lines 1–30** — the Pest feature test style: `test('…', function () { … })`, `User::factory()->create()`, `$this->actingAs($user)`. `tests/Pest.php` **lines 17–19** apply `RefreshDatabase` to `Feature`.
8. `phpunit.xml` **lines 21–34** — tests run on in-memory SQLite, so the Sanctum migration runs there too.
9. `phpstan.neon` — level **7** over `app/`, `bootstrap/app.php`, `config/`, `database/` and `routes/`. New code needs full return types and array-shape docblocks.
10. `README.md` (written in Story 01) — add an `## API` section after "Environment Variables".

---

## Implementation tasks

### 1 — Install Sanctum and the API routes file

Run in the project root:

```bash
php artisan install:api --without-migration-prompt --no-interaction
php artisan migrate --no-interaction
```

Expected results:
- `composer.json` `require` gains `laravel/sanctum`.
- `routes/api.php` is created.
- `config/sanctum.php` is published.
- A `create_personal_access_tokens_table` migration lands in `database/migrations/`.
- `bootstrap/app.php` `withRouting` gains `api: __DIR__.'/../routes/api.php',`.

Confirm each with `git status`. If `install:api` did **not** edit `bootstrap/app.php`, task 2 adds the `api:` line.

### 2 — Version prefix and JSON-forcing middleware

**Create file: `app/Http/Middleware/ForceJsonResponse.php`**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    /**
     * Treat every API request as expecting JSON, so validation and auth never redirect.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
```

**File: `bootstrap/app.php`**

- Make the `withRouting` arguments (lines 13–17) exactly:

```php
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
```

- In `withMiddleware` (lines 18–27), after the `$middleware->web(append: [...])` call, add:

```php
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);
```

- Add `use App\Http\Middleware\ForceJsonResponse;` to the imports (lines 3–10), keeping them alphabetical.

### 3 — Response helper

**Create file: `app/Http/Responses/ApiResponse.php`**

```php
<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    /**
     * @param  array<array-key, mixed>|JsonResource|null  $data
     * @param  array<string, string|array<int, string>>  $headers
     */
    public static function success(array|JsonResource|null $data = null, ?string $message = null, int $status = 200, array $headers = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data instanceof JsonResource ? $data->resolve(request()) : $data,
        ], $status, $headers);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  class-string<JsonResource>  $resource
     */
    public static function paginated(LengthAwarePaginator $paginator, string $resource, ?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $resource::collection($paginator->getCollection())->resolve(request()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * @param  array<string, array<int, string>>|null  $errors
     * @param  array<string, string|array<int, string>>  $headers
     */
    public static function error(string $message, int $status, ?array $errors = null, array $headers = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status, $headers);
    }
}
```

- **`paginated()` accepts `LengthAwarePaginator` only.** Every list endpoint uses `->paginate()`, not `simplePaginate()` or cursor pagination, so `meta.total` and `meta.last_page` always exist. That's the "consistent pagination" criterion.
- If PHPStan level 7 reports a generic mismatch on `LengthAwarePaginator<int, mixed>`, use exactly the type PHPStan names in its error. **Do not** lower the PHPStan level or add ignores.

### 4 — Exception rendering for API requests

**Create file: `app/Http/Responses/ApiExceptionRenderer.php`**

```php
<?php

namespace App\Http\Responses;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class ApiExceptionRenderer
{
    /**
     * Render any exception on an API request as the standard JSON error envelope.
     * Returns null for non-API requests so Laravel's default (HTML / Inertia) rendering is used.
     */
    public function __invoke(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') || $exception instanceof HttpResponseException) {
            return null;
        }

        return match (true) {
            $exception instanceof ValidationException => ApiResponse::error('The given data was invalid.', 422, $exception->errors()),
            $exception instanceof AuthenticationException => ApiResponse::error('Unauthenticated.', 401),
            $exception instanceof AccessDeniedHttpException => ApiResponse::error($this->forbiddenMessage($exception), 403),
            $exception instanceof NotFoundHttpException => ApiResponse::error('Resource not found.', 404),
            $exception instanceof MethodNotAllowedHttpException => ApiResponse::error('Method not allowed.', 405, headers: $exception->getHeaders()),
            $exception instanceof TooManyRequestsHttpException => ApiResponse::error('Too many requests.', 429, headers: $exception->getHeaders()),
            $exception instanceof HttpExceptionInterface => ApiResponse::error(
                $exception->getMessage() !== '' ? $exception->getMessage() : (Response::$statusTexts[$exception->getStatusCode()] ?? 'Error.'),
                $exception->getStatusCode(),
                headers: $exception->getHeaders(),
            ),
            default => ApiResponse::error(config('app.debug') ? $exception->getMessage() : 'Server error.', 500),
        };
    }

    private function forbiddenMessage(AccessDeniedHttpException $exception): string
    {
        $message = $exception->getMessage();

        return $message === '' || $message === 'This action is unauthorized.' ? 'Forbidden.' : $message;
    }
}
```

- `ThrottleRequestsException` extends `TooManyRequestsHttpException`, so the 429 arm covers throttling once KAN-58 adds it.
- `HttpResponseException` returns `null`: it already carries a response the developer built on purpose.

**File: `bootstrap/app.php`** — inside `withExceptions` (lines 28–31), **after** the existing `shouldRenderJsonWhen(...)` call, add:

```php
        $exceptions->render(new ApiExceptionRenderer);
```

Add `use App\Http\Responses\ApiExceptionRenderer;` to the imports.

Reporting is **unchanged**: a render callback does not stop Laravel from logging the 500. **Do not** add `dontReport`.

### 5 — `HasApiTokens` on User

**File: `app/Models/User.php`**

- Add `use Laravel\Sanctum\HasApiTokens;` and `use Laravel\Sanctum\PersonalAccessToken;` to the imports (lines 5–17). They go in alphabetical order, after the `Laravel\Fortify\...` imports.
- Change line 42 to:

```php
    use HasApiTokens, HasFactory, HasTeams, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;
```

- Add `* @property-read Collection<int, PersonalAccessToken> $tokens` to the class docblock, after the `$teams` line (line 35).

### 6 — User resource

Run `php artisan make:resource UserResource --no-interaction`, then replace `toArray` in **`app/Http/Resources/UserResource.php`**:

```php
    /**
     * @return array{id: int, name: string, email: string, email_verified_at: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
        ];
    }
```

Add `@mixin User` to the class docblock, with `use App\Models\User;`, so PHPStan resolves `$this->id` and the other fields.

### 7 — Reference routes

**File: `routes/api.php`** — replace the generated contents with:

```php
<?php

use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('ping', fn () => ApiResponse::success(['status' => 'ok']))->name('api.ping');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', fn (Request $request) => ApiResponse::success(new UserResource($request->user())))->name('api.me');
});
```

- **Paths are relative.** `apiPrefix: 'api/v1'` turns them into `/api/v1/ping` and `/api/v1/me`. **Do not** add `prefix('v1')` here.
- Route names use the **`api.`** prefix so they never clash with web route names (`home`, `dashboard`, …).
- Regenerate Wayfinder types after adding routes: `php artisan wayfinder:generate --no-interaction`. The output in `resources/js/routes` and `resources/js/actions` is git-ignored (`.gitignore` lines 10–12).

### 8 — README "API" section

**File: `README.md`** — insert a `## API` section after "Environment Variables". It covers:
- **Base URL:** `{APP_URL}/api/v1`.
- **Auth:** `Authorization: Bearer <token>` via Sanctum. Token issuing arrives in KAN-23.
- **Response format:** copy the three JSON examples and the status table from "Response format (contract)" above.
- **Adding an endpoint:** the route goes in `routes/api.php` (relative path, `api.` name prefix). Respond through `ApiResponse::success()` / `ApiResponse::paginated()`, map models through a resource in `app/Http/Resources/`, validate with a Form Request. **Never** return raw models or call `response()->json()` directly.

### 9 — Format and static analysis

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --no-progress
```

PHPStan must report **0 errors**. Fix types instead of adding ignores.

### 10 — Commit and push

```bash
git add -A
git status --short | grep -E "(^|/)\.env$|secrets\.yaml|\.sqlite" && echo "STOP: secret file staged" || echo "clean"
git commit -m "feat(api): /api/v1 foundation — Sanctum, JSON envelope, API exception rendering (KAN-15)"
git push
```

The guard **must** print `clean`. Plain `git push` only. **No** force push.

---

## Edge Cases & Failure Modes

- **Validation on an API route without an `Accept` header.** `FormRequest` would redirect back (302) by default. `ForceJsonResponse` (task 2) sets `Accept: application/json`, and `ApiExceptionRenderer` returns 422 regardless. Test 4 covers it.
- **`auth:sanctum` guest without an `Accept` header.** The `Authenticate` middleware computes a redirect to Fortify's `login` route and throws `AuthenticationException`. The renderer's `api/*` check returns a 401 envelope before Laravel can redirect. Test 2 covers it.
- **`ModelNotFoundException` leaks the model class.** `prepareException` (`Handler.php` line 773) turns it into a `NotFoundHttpException` whose message is "No query results for model [App\Models\User] …". The renderer always returns `"Resource not found."`. Test 5 asserts the class name is absent.
- **Unknown API URL** (`/api/v1/nope`). The router throws `NotFoundHttpException`, and the response is a 404 envelope, not the HTML 404 page. Test 5 covers it.
- **Wrong HTTP method** (`POST /api/v1/ping`). That gives a 405 envelope with the `Allow` header preserved, through `MethodNotAllowedHttpException::getHeaders()`. Test 8 covers it.
- **500 in production.** With `app.debug=false`, the message is `"Server error."` and no stack trace or exception class is shown. With `app.debug=true` the real message is shown for local debugging. The exception is still **reported** to the log. Test 6 covers both cases.
- **Unversioned path** (`/api/ping`). Nothing is served there. It returns a 404 envelope because `api/ping` still matches `api/*`. That's intended: there is only one version.
- **Web routes stay HTML.** `ApiExceptionRenderer` returns `null` when `! $request->is('api/*')`. The existing `shouldRenderJsonWhen` (lines 29–31) still renders JSON for web requests that send `Accept: application/json` (Inertia XHR), using Laravel's default JSON shape, **not** the envelope. Test 10 asserts a web 404 is not JSON.
- **CSRF / 419.** The `api` middleware group has no CSRF check, so it doesn't apply to token-auth API calls. If a 419 does surface, it hits the generic `HttpExceptionInterface` arm.
- **`HttpResponseException`.** It's returned untouched (`null` from the renderer), so endpoints that build a custom response on purpose keep it.
- **`install:api` writes `bootstrap/app.php` differently than expected.** Task 1 confirms the file afterwards, and task 2 states its exact final `withRouting` arguments. Overwrite the arguments to match.
- **Sanctum migration across databases.** `personal_access_tokens` uses `morphs('tokenable')`, which works on both SQLite (tests) and MySQL (local). Task 1's `php artisan migrate` checks MySQL.
- **Page beyond the last page** (`?page=999`). `paginated()` returns `data: []` with `meta.current_page: 999` and `links.next: null`. That's valid; don't treat it as a 404.

---

## Test Plan

**Create file: `tests/Feature/Api/ApiFoundationTest.php`** with `php artisan make:test --pest Api/ApiFoundationTest --no-interaction`. Follow the style of `tests/Feature/Teams/TeamTest.php`. Register test-only routes in `beforeEach` with `Route::middleware('api')->prefix('api/v1/_test')->group(...)` so production `routes/api.php` stays clean. The routes are:
- `POST validate`, which runs `request()->validate(['email' => ['required', 'email']])`.
- `GET forbidden`, which throws `new \Illuminate\Auth\Access\AuthorizationException`.
- `GET missing-user`, which runs `User::findOrFail(999999)`.
- `GET boom`, which throws `new RuntimeException('secret detail')`.
- `GET users`, which returns `ApiResponse::paginated(User::query()->paginate(2), UserResource::class)`.

Feature tests:

1. **`ping returns the success envelope`** — `getJson('/api/v1/ping')` gives 200 and `assertExactJson(['success' => true, 'message' => null, 'data' => ['status' => 'ok']])`.
2. **`me requires a token`** — `get('/api/v1/me')` (**no** JSON `Accept` header) gives 401, `assertJson(['success' => false, 'message' => 'Unauthenticated.', 'errors' => null])`, and **not** a redirect.
3. **`me returns the authenticated user`** — `Sanctum::actingAs(User::factory()->create())`, then `getJson('/api/v1/me')`. It gives 200 with `data.id` and `data.email` present and `assertJsonMissingPath('data.password')`.
4. **`validation errors use the envelope`** — `post('/api/v1/_test/validate', [])` (no JSON header) gives 422, `success` false, `message` "The given data was invalid.", and an `errors.email` array.
5. **`not found uses the envelope`** — `getJson('/api/v1/nope')` gives a 404 envelope. `getJson('/api/v1/_test/missing-user')` gives 404 with message "Resource not found.", and `assertDontSee('App\\Models\\User', false)`.
6. **`server errors hide details unless debugging`** — `config(['app.debug' => false])`, then `getJson('/api/v1/_test/boom')` gives 500 with message "Server error." and `assertDontSee('secret detail')`. Then `config(['app.debug' => true])` and the same request has message "secret detail".
7. **`forbidden uses the envelope`** — `getJson('/api/v1/_test/forbidden')` gives 403 with message "Forbidden.".
8. **`method not allowed uses the envelope`** — `postJson('/api/v1/ping')` gives 405 with message "Method not allowed." and `assertHeader('Allow')`.
9. **`paginated lists share one format`** — `User::factory()->count(3)->create()`, then `getJson('/api/v1/_test/users?page=2')`. It gives 200, `assertJsonCount(1, 'data')`, `meta.current_page` 2, `meta.total` 3, `meta.last_page` 2, and `links.next` null.
10. **`web routes keep html errors`** — `get('/definitely-missing')` gives 404, and `expect($response->headers->get('content-type'))->not->toStartWith('application/json')`.

**Regression (existing, unchanged):** `tests/Feature/ExampleTest.php`, `tests/Feature/Auth/*` and `tests/Feature/Teams/*` must stay green. They prove the web/Inertia flows are unaffected.

---

## Migration / Rollback

- **Schema:** adds the `personal_access_tokens` table only (Sanctum migration). Rollback: `php artisan migrate:rollback --step=1` drops it. No existing table changes.
- **Dependency:** to roll back, run `composer remove laravel/sanctum` and delete `config/sanctum.php`, `routes/api.php` and the Sanctum migration.
- **Half-applied state:** if `install:api` succeeds but task 2 is skipped, routes serve at `/api/ping` instead of `/api/v1/ping`, and the API contract is wrong. Verification step 2 catches it.

---

## Verification Steps

1. **Backend builds:** in the project root, `vendor/bin/phpstan analyse --no-progress` reports 0 errors, and `vendor/bin/pint --dirty --format agent` passes.
2. **Routes registered:** `php artisan route:list --path=api` shows `GET|HEAD api/v1/ping` (`api.ping`) and `GET|HEAD api/v1/me` (`api.me`, with `auth:sanctum`), and **no** route at `api/ping`.
3. **Migrations:** `php artisan migrate:status` shows the `personal_access_tokens` migration as `Ran` on MySQL.
4. **Tests pass:** `php artisan test --compact tests/Feature/Api/ApiFoundationTest.php` is green (10 tests).
5. **Manual smoke:** with `composer dev` running, `curl -s http://localhost:8000/api/v1/ping` prints `{"success":true,"message":null,"data":{"status":"ok"}}`, and `curl -s -i http://localhost:8000/api/v1/me` shows status `401` with the error envelope, not a `302`.
6. **Regression:** `php artisan test --compact tests/Feature/ExampleTest.php tests/Feature/Auth tests/Feature/Teams` is green. Then ask the user to run the full suite with `php artisan test --compact`.

---

## Done Criteria

- [ ] All API routes are registered from `routes/api.php` under `/api/v1` (`apiPrefix: 'api/v1'` in `bootstrap/app.php`).
- [ ] `laravel/sanctum` is installed, the `personal_access_tokens` migration runs, `User` uses `HasApiTokens`, and `GET /api/v1/me` is protected by `auth:sanctum`.
- [ ] One JSON envelope (`success`, `message`, `data` | `errors`), produced only through `App\Http\Responses\ApiResponse`.
- [ ] `ApiExceptionRenderer` returns JSON envelopes for 401, 403, 404, 405, 422, 429 and 500 on `api/*`. HTML is never returned, and 500 hides details when `app.debug` is false.
- [ ] `ApiResponse::paginated()` is the single pagination format (`data` list plus `meta` and `links`).
- [ ] `tests/Feature/Api/ApiFoundationTest.php` is green (10 tests), and the existing Auth/Teams/Example tests are still green.
- [ ] The README "API" section documents the base URL, auth, response format and how to add an endpoint.
- [ ] Committed and pushed with no force push and no secrets staged.
- [ ] `00-overview.md` lists this story.

**STOP HERE. Report to the user and wait for confirmation before proceeding to Story 03.**
