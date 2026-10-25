<?php

use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1/_test')->group(function () {
        Route::post('validate', fn () => request()->validate(['email' => ['required', 'email']]));
        Route::get('forbidden', fn () => throw new AuthorizationException);
        Route::get('missing-user', fn () => User::findOrFail(999999));
        Route::get('boom', fn () => throw new RuntimeException('secret detail'));
        Route::get('users', fn () => ApiResponse::paginated(User::query()->paginate(2), UserResource::class));
    });
});

test('ping returns the success envelope', function () {
    $response = $this->getJson('/api/v1/ping');

    $response->assertOk()
        ->assertExactJson(['success' => true, 'message' => null, 'data' => ['status' => 'ok']]);
});

test('me requires a token', function () {
    $response = $this->get('/api/v1/me');

    $response->assertUnauthorized()
        ->assertJson(['success' => false, 'message' => 'Unauthenticated.', 'errors' => null]);
});

test('me returns the authenticated user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/me');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonMissingPath('data.password');
});

test('validation errors use the envelope', function () {
    $response = $this->post('/api/v1/_test/validate', []);

    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'The given data was invalid.')
        ->assertJsonStructure(['errors' => ['email']]);
});

test('not found uses the envelope', function () {
    $this->getJson('/api/v1/nope')
        ->assertNotFound()
        ->assertJson(['success' => false, 'message' => 'Resource not found.', 'errors' => null]);

    $this->getJson('/api/v1/_test/missing-user')
        ->assertNotFound()
        ->assertJsonPath('message', 'Resource not found.')
        ->assertDontSee('App\\Models\\User', false);
});

test('server errors hide details unless debugging', function () {
    config(['app.debug' => false]);

    $this->getJson('/api/v1/_test/boom')
        ->assertInternalServerError()
        ->assertJson(['success' => false, 'message' => 'Server error.', 'errors' => null])
        ->assertDontSee('secret detail');

    config(['app.debug' => true]);

    $this->getJson('/api/v1/_test/boom')
        ->assertInternalServerError()
        ->assertJsonPath('message', 'secret detail');
});

test('forbidden uses the envelope', function () {
    $this->getJson('/api/v1/_test/forbidden')
        ->assertForbidden()
        ->assertJson(['success' => false, 'message' => 'Forbidden.', 'errors' => null]);
});

test('method not allowed uses the envelope', function () {
    $this->postJson('/api/v1/ping')
        ->assertMethodNotAllowed()
        ->assertJsonPath('message', 'Method not allowed.')
        ->assertHeader('Allow');
});

test('paginated lists share one format', function () {
    User::factory()->count(3)->create();

    $this->getJson('/api/v1/_test/users?page=2')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('links.next', null);
});

test('web routes keep html errors', function () {
    $response = $this->get('/definitely-missing');

    $response->assertNotFound();
    expect($response->headers->get('content-type'))->not->toStartWith('application/json');
});
