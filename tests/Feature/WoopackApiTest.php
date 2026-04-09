<?php

use App\Models\Invitation;
use App\Models\PackingStatus;
use App\Models\User;
use App\Models\WooCommerceConnection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'app.url' => 'http://localhost',
    ]);

    Carbon::setTestNow('2026-04-08 15:30:00');
});

function woocommerceConnection(User $user, array $attributes = []): WooCommerceConnection
{
    return WooCommerceConnection::query()->create([
        'user_id' => $user->id,
        'store_url' => 'https://store.test',
        'consumer_key' => 'ck_test',
        'consumer_secret' => 'cs_test',
        ...$attributes,
    ]);
}

function makeOrder(int $id, string $status, float $total, string $dateCreated = '2026-04-08T10:00:00'): array
{
    return [
        'id' => $id,
        'status' => $status,
        'total' => number_format($total, 2, '.', ''),
        'date_created' => $dateCreated,
        'billing' => [
            'first_name' => 'Maria',
            'last_name' => 'Silva',
            'email' => 'maria@example.com',
            'phone' => '11999999999',
        ],
        'shipping' => [
            'first_name' => 'Maria',
            'last_name' => 'Silva',
            'address_1' => 'Rua A',
            'address_2' => '',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'postcode' => '01000-000',
        ],
        'customer_note' => '',
        'line_items' => [
            [
                'id' => $id * 10,
                'name' => 'Produto Teste',
                'sku' => 'SKU-TESTE',
                'quantity' => 1,
                'total' => number_format($total, 2, '.', ''),
                'image' => [
                    'src' => 'https://example.com/image.jpg',
                ],
            ],
        ],
    ];
}

afterEach(function (): void {
    Carbon::setTestNow();
});

it('logs in with a valid invited user account', function (): void {
    $user = User::factory()->create([
        'email' => 'operator@example.com',
        'password' => 'secret-pass',
        'is_admin' => false,
    ]);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'secret-pass',
    ])
        ->assertOk()
        ->assertJsonPath('authenticated', true)
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('has_integration', false);

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid login credentials', function (): void {
    User::factory()->create([
        'email' => 'operator@example.com',
        'password' => 'secret-pass',
    ]);

    $this->postJson('/api/login', [
        'email' => 'operator@example.com',
        'password' => 'wrong-pass',
    ])
        ->assertStatus(401)
        ->assertJson(['error' => 'Credenciais invalidas.']);
});

it('logs out and clears the authenticated session', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/logout')
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertGuest();
});

it('requires authentication on protected routes', function (): void {
    $this->getJson('/api/orders')
        ->assertStatus(401)
        ->assertJson(['error' => 'Unauthorized']);
});

it('serves public legal pages for the meta app setup', function (): void {
    $this->get('/politica-de-privacidade')
        ->assertOk()
        ->assertSee('Politica de Privacidade');

    $this->get('/termos-de-servico')
        ->assertOk()
        ->assertSee('Termos de Servico');

    $this->get('/exclusao-de-dados')
        ->assertOk()
        ->assertSee('Exclusao de Dados do Usuario');
});

it('provides meta oauth configuration for an authenticated user', function (): void {
    config([
        'woopack.meta_app_id' => '1262833955826800',
        'woopack.meta_graph_version' => 'v25.0',
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->getJson('/api/meta/connect/config')
        ->assertOk()
        ->assertJsonPath('app_id', '1262833955826800')
        ->assertJsonPath('redirect_uri', route('meta.callback'));

    expect($response->json('state'))->not->toBeEmpty();
    expect($response->json('auth_url'))->toContain('facebook.com/v25.0/dialog/oauth');
});

it('accepts a valid meta oauth callback', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/meta/connect/config');

    $state = session('meta_oauth.state');

    $this->get("/auth/meta/callback?state={$state}&code=test-code")
        ->assertOk()
        ->assertSee('Retorno recebido com sucesso')
        ->assertSee('Autorizacao recebida com sucesso');

    expect(session('meta_oauth_result.status'))->toBe('success');
    expect(session('meta_oauth_result.code'))->toBe('test-code');
});

it('rejects a meta oauth callback with invalid state', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/meta/connect/config');

    $this->get('/auth/meta/callback?state=invalid&code=test-code')
        ->assertOk()
        ->assertSee('Nao foi possivel concluir a autorizacao');

    expect(session('meta_oauth_result.status'))->toBe('error');
});

it('returns auth payload for the active user', function (): void {
    $user = User::factory()->admin()->create();
    woocommerceConnection($user);

    $this->actingAs($user)
        ->getJson('/api/auth/check')
        ->assertOk()
        ->assertJsonPath('authenticated', true)
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('has_integration', true)
        ->assertJsonPath('is_admin', true);
});

it('allows a user to save and update only their own woocommerce connection', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    woocommerceConnection($otherUser, [
        'store_url' => 'https://other-store.test',
        'consumer_key' => 'ck_other',
        'consumer_secret' => 'cs_other',
    ]);

    $this->actingAs($owner)
        ->putJson('/api/integration', [
            'store_url' => 'store-a.test',
            'consumer_key' => 'ck_owner',
            'consumer_secret' => 'cs_owner',
        ])
        ->assertOk()
        ->assertJsonPath('connection.store_url', 'store-a.test')
        ->assertJsonPath('connection.masked_consumer_key', '***_owner')
        ->assertJsonPath('connection.masked_consumer_secret', '***_owner');

    $this->actingAs($owner)
        ->putJson('/api/integration', [
            'store_url' => 'https://updated-store.test',
            'consumer_key' => '',
            'consumer_secret' => '',
        ])
        ->assertOk()
        ->assertJsonPath('connection.store_url', 'https://updated-store.test');

    expect($owner->refresh()->wooCommerceConnection?->store_url)->toBe('https://updated-store.test');
    expect($owner->wooCommerceConnection?->consumer_key)->toBe('ck_owner');
    expect($otherUser->refresh()->wooCommerceConnection?->store_url)->toBe('https://other-store.test');
});

it('shows masked credentials for an existing integration', function (): void {
    $user = User::factory()->create();
    woocommerceConnection($user, [
        'consumer_key' => 'ck_1234567890',
        'consumer_secret' => 'cs_abcdefghij',
    ]);

    $this->actingAs($user)
        ->getJson('/api/integration')
        ->assertOk()
        ->assertJsonPath('connection.masked_consumer_key', '***567890')
        ->assertJsonPath('connection.masked_consumer_secret', '***efghij');
});

it('tests an existing integration without requiring the user to resend saved keys', function (): void {
    $user = User::factory()->create();
    woocommerceConnection($user, [
        'store_url' => 'https://store-a.test',
        'consumer_key' => 'ck_saved',
        'consumer_secret' => 'cs_saved',
    ]);

    Http::fake([
        'https://store-a.test/wp-json/wc/v3/orders*' => Http::response([]),
    ]);

    $this->actingAs($user)
        ->postJson('/api/integration/test', [
            'store_url' => 'https://store-a.test',
            'consumer_key' => '',
            'consumer_secret' => '',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    Http::assertSent(function ($request): bool {
        return str_starts_with($request->url(), 'https://store-a.test/wp-json/wc/v3/orders')
            && $request['consumer_key'] === 'ck_saved'
            && $request['consumer_secret'] === 'cs_saved';
    });
});

it('returns a predictable error when the authenticated user has no integration', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/orders')
        ->assertStatus(400)
        ->assertJson(['error' => 'WooCommerce connection not configured']);
});

it('uses the authenticated users woocommerce connection and merges user scoped packing status', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    woocommerceConnection($user, [
        'store_url' => 'https://store-a.test',
        'consumer_key' => 'ck_a',
        'consumer_secret' => 'cs_a',
    ]);

    woocommerceConnection($otherUser, [
        'store_url' => 'https://store-b.test',
        'consumer_key' => 'ck_b',
        'consumer_secret' => 'cs_b',
    ]);

    PackingStatus::query()->create([
        'user_id' => $user->id,
        'woo_order_id' => 101,
        'packed_at' => now(),
    ]);

    PackingStatus::query()->create([
        'user_id' => $otherUser->id,
        'woo_order_id' => 102,
        'packed_at' => now(),
    ]);

    Http::fake([
        'https://store-a.test/wp-json/wc/v3/orders*' => Http::response([
            makeOrder(101, 'processing', 150.90),
            makeOrder(102, 'on-hold', 89.50),
        ]),
        'https://store-b.test/wp-json/wc/v3/orders*' => Http::response([
            makeOrder(201, 'processing', 55.00),
        ]),
    ]);

    $this->actingAs($user)
        ->getJson('/api/orders?status=processing')
        ->assertOk()
        ->assertJsonPath('0.id', 101)
        ->assertJsonPath('0.is_packed', true)
        ->assertJsonPath('1.id', 102)
        ->assertJsonPath('1.is_packed', false);

    Http::assertSent(function ($request): bool {
        return str_starts_with($request->url(), 'https://store-a.test/wp-json/wc/v3/orders')
            && $request['consumer_key'] === 'ck_a'
            && $request['consumer_secret'] === 'cs_a';
    });
});

it('limits non processing order tabs to the last 30 days', function (): void {
    $user = User::factory()->create();
    woocommerceConnection($user);

    Http::fake([
        'https://store.test/wp-json/wc/v3/orders*' => Http::response([]),
    ]);

    $this->actingAs($user)
        ->getJson('/api/orders?status=completed')
        ->assertOk();

    Http::assertSent(function ($request): bool {
        $url = $request->url();

        return str_starts_with($url, 'https://store.test/wp-json/wc/v3/orders')
            && str_contains($url, 'status=completed')
            && str_contains($url, 'after=')
            && str_contains($url, 'before=');
    });
});

it('keeps processing orders without any date filter', function (): void {
    $user = User::factory()->create();
    woocommerceConnection($user);

    Http::fake([
        'https://store.test/wp-json/wc/v3/orders*' => Http::response([]),
    ]);

    $this->actingAs($user)
        ->getJson('/api/orders?status=processing')
        ->assertOk();

    Http::assertSent(function ($request): bool {
        $url = $request->url();

        return str_starts_with($url, 'https://store.test/wp-json/wc/v3/orders')
            && str_contains($url, 'status=processing')
            && ! str_contains($url, 'after=')
            && ! str_contains($url, 'before=');
    });
});

it('returns a single order with the users own packing flag', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    woocommerceConnection($user);

    PackingStatus::query()->create([
        'user_id' => $otherUser->id,
        'woo_order_id' => 222,
        'packed_at' => now(),
    ]);

    Http::fake([
        'https://store.test/wp-json/wc/v3/orders/222*' => Http::response(
            makeOrder(222, 'processing', 49.90)
        ),
    ]);

    $this->actingAs($user)
        ->getJson('/api/orders/222')
        ->assertOk()
        ->assertJsonPath('id', 222)
        ->assertJsonPath('is_packed', false);
});

it('scopes packing updates by authenticated user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    PackingStatus::query()->create([
        'user_id' => $otherUser->id,
        'woo_order_id' => 333,
        'packed_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson('/api/orders/333/pack', ['packed' => true])
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('packing_statuses', [
        'user_id' => $user->id,
        'woo_order_id' => 333,
    ]);

    $this->assertDatabaseHas('packing_statuses', [
        'user_id' => $otherUser->id,
        'woo_order_id' => 333,
    ]);

    $this->actingAs($user)
        ->postJson('/api/orders/333/pack', ['packed' => false])
        ->assertOk();

    $this->assertDatabaseMissing('packing_statuses', [
        'user_id' => $user->id,
        'woo_order_id' => 333,
    ]);

    $this->assertDatabaseHas('packing_statuses', [
        'user_id' => $otherUser->id,
        'woo_order_id' => 333,
    ]);
});

it('aggregates stats from the authenticated users woocommerce store', function (): void {
    $user = User::factory()->create();
    woocommerceConnection($user);

    Http::fake([
        'https://store.test/wp-json/wc/v3/orders*' => Http::response([
            makeOrder(1, 'processing', 100.00, '2026-04-07T10:00:00'),
            makeOrder(2, 'processing', 50.00, '2026-04-07T14:00:00'),
            makeOrder(3, 'completed', 25.00, '2026-04-08T09:00:00'),
        ]),
    ]);

    $this->actingAs($user)
        ->getJson('/api/stats')
        ->assertOk()
        ->assertJsonPath('total_orders', 3)
        ->assertJsonPath('total_sales', 175)
        ->assertJsonPath('status_counts.processing', 2)
        ->assertJsonPath('status_counts.completed', 1)
        ->assertJsonPath('daily_sales.2026-04-07', 150)
        ->assertJsonPath('daily_sales.2026-04-08', 25);
});

it('filters dashboard stats by the selected date range', function (): void {
    $user = User::factory()->create();
    woocommerceConnection($user);

    Http::fake([
        'https://store.test/wp-json/wc/v3/orders*' => Http::response([
            makeOrder(1, 'processing', 100.00, '2026-04-08T10:00:00'),
        ]),
    ]);

    $this->actingAs($user)
        ->getJson('/api/stats?range=today')
        ->assertOk()
        ->assertJsonPath('range', 'today')
        ->assertJsonPath('total_orders', 1);

    Http::assertSent(function ($request): bool {
        $url = $request->url();

        return str_starts_with($url, 'https://store.test/wp-json/wc/v3/orders')
            && str_contains($url, 'after=')
            && str_contains($url, 'before=');
    });
});

it('keeps a real date window when building dashboard filters', function (): void {
    $user = User::factory()->create();
    woocommerceConnection($user);

    Http::fake([
        'https://store.test/wp-json/wc/v3/orders*' => Http::response([]),
    ]);

    $this->actingAs($user)
        ->getJson('/api/stats?range=30d')
        ->assertOk();

    Http::assertSent(function ($request): bool {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

        return is_string($query['after'] ?? null)
            && is_string($query['before'] ?? null)
            && str_starts_with($query['after'], '2026-03-09T15:30:00')
            && str_starts_with($query['before'], '2026-04-08T15:30:00')
            && ($query['after'] !== $query['before']);
    });
});

it('paginates through all woo orders when building dashboard stats', function (): void {
    $user = User::factory()->create();
    woocommerceConnection($user);

    Http::fake([
        'https://store.test/wp-json/wc/v3/orders*' => Http::sequence()
            ->push(array_map(
                fn (int $id) => makeOrder($id, 'processing', 10.00),
                range(1, 100)
            ))
            ->push([
                makeOrder(101, 'completed', 25.00),
            ]),
    ]);

    $this->actingAs($user)
        ->getJson('/api/stats?range=30d')
        ->assertOk()
        ->assertJsonPath('total_orders', 101)
        ->assertJsonPath('status_counts.processing', 100)
        ->assertJsonPath('status_counts.completed', 1);

    Http::assertSentCount(2);
});

it('creates invitations only for administrators', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->postJson('/api/invitations', ['email' => 'invitee@example.com'])
        ->assertCreated()
        ->assertJsonPath('invitation.email', 'invitee@example.com');

    $this->assertDatabaseHas('invitations', [
        'email' => 'invitee@example.com',
        'created_by' => $admin->id,
    ]);
});

it('forbids non admins from creating invitations', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/invitations', ['email' => 'invitee@example.com'])
        ->assertStatus(403)
        ->assertJson(['error' => 'Forbidden']);
});

it('shows and accepts a valid invitation', function (): void {
    $admin = User::factory()->admin()->create();
    $invitation = Invitation::query()->create([
        'email' => 'new.user@example.com',
        'token' => 'valid-token',
        'expires_at' => now()->addDays(2),
        'created_by' => $admin->id,
    ]);

    $this->getJson("/api/invitations/{$invitation->token}")
        ->assertOk()
        ->assertJsonPath('email', 'new.user@example.com');

    $this->postJson('/api/invitations/accept', [
        'token' => $invitation->token,
        'name' => 'Novo Usuario',
        'password' => 'secret-pass',
        'password_confirmation' => 'secret-pass',
    ])
        ->assertCreated()
        ->assertJsonPath('authenticated', true)
        ->assertJsonPath('user.email', 'new.user@example.com')
        ->assertJsonPath('has_integration', false);

    $this->assertDatabaseHas('users', [
        'email' => 'new.user@example.com',
    ]);

    expect($invitation->refresh()->accepted_at)->not->toBeNull();
});

it('creates or updates the initial admin with the artisan command', function (): void {
    Artisan::call('woopack:create-admin', [
        'name' => 'Admin WooPack',
        'email' => 'admin@example.com',
        'password' => 'secret-pass',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'admin@example.com',
        'is_admin' => true,
    ]);
});
