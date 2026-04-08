<?php

namespace Tests\Feature;

use App\Models\PackingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WoopackApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'http://localhost',
            'woopack.admin_password' => 'secret-pass',
            'woopack.woocommerce.url' => 'store.test',
            'woopack.woocommerce.key' => 'ck_test',
            'woopack.woocommerce.secret' => 'cs_test',
        ]);
    }

    public function test_login_sets_authenticated_session_flag(): void
    {
        $response = $this->postJson($this->endpoint('/api/login'), [
            'password' => 'secret-pass',
        ]);

        $response
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertSessionHas('woopack_authenticated', true);
    }

    public function test_login_rejects_invalid_password(): void
    {
        $this->postJson($this->endpoint('/api/login'), [
            'password' => 'wrong-pass',
        ])->assertStatus(401)
            ->assertJson(['error' => 'Invalid password']);
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson($this->endpoint('/api/orders'))
            ->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_orders_endpoint_merges_local_packing_status(): void
    {
        PackingStatus::query()->create([
            'woo_order_id' => 101,
            'packed_at' => now(),
        ]);

        Http::fake([
            'https://store.test/wp-json/wc/v3/orders*' => Http::response([
                $this->makeOrder(101, 'processing', 150.90),
                $this->makeOrder(102, 'on-hold', 89.50),
            ]),
        ]);

        $response = $this->withSession(['woopack_authenticated' => true])
            ->getJson($this->endpoint('/api/orders?status=processing'));

        $response->assertOk();
        $response->assertJsonPath('0.id', 101);
        $response->assertJsonPath('0.is_packed', true);
        $response->assertJsonPath('1.id', 102);
        $response->assertJsonPath('1.is_packed', false);
    }

    public function test_single_order_endpoint_returns_is_packed_flag(): void
    {
        PackingStatus::query()->create([
            'woo_order_id' => 222,
            'packed_at' => now(),
        ]);

        Http::fake([
            'https://store.test/wp-json/wc/v3/orders/222*' => Http::response(
                $this->makeOrder(222, 'processing', 49.90)
            ),
        ]);

        $this->withSession(['woopack_authenticated' => true])
            ->getJson($this->endpoint('/api/orders/222'))
            ->assertOk()
            ->assertJsonPath('id', 222)
            ->assertJsonPath('is_packed', true);
    }

    public function test_pack_endpoint_creates_and_removes_packing_status(): void
    {
        $this->withSession(['woopack_authenticated' => true])
            ->postJson($this->endpoint('/api/orders/333/pack'), ['packed' => true])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('packing_statuses', [
            'woo_order_id' => 333,
        ]);

        $this->withSession(['woopack_authenticated' => true])
            ->postJson($this->endpoint('/api/orders/333/pack'), ['packed' => false])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('packing_statuses', [
            'woo_order_id' => 333,
        ]);
    }

    public function test_stats_endpoint_aggregates_orders_from_woocommerce(): void
    {
        Http::fake([
            'https://store.test/wp-json/wc/v3/orders*' => Http::response([
                $this->makeOrder(1, 'processing', 100.00, '2026-04-07T10:00:00'),
                $this->makeOrder(2, 'processing', 50.00, '2026-04-07T14:00:00'),
                $this->makeOrder(3, 'completed', 25.00, '2026-04-08T09:00:00'),
            ]),
        ]);

        $response = $this->withSession(['woopack_authenticated' => true])
            ->getJson($this->endpoint('/api/stats'));

        $response->assertOk();
        $response->assertJsonPath('total_orders', 3);
        $response->assertJsonPath('total_sales', 175);
        $response->assertJsonPath('status_counts.processing', 2);
        $response->assertJsonPath('status_counts.completed', 1);
        $response->assertJsonPath('daily_sales.2026-04-07', 150);
        $response->assertJsonPath('daily_sales.2026-04-08', 25);
    }

    public function test_missing_woocommerce_configuration_returns_400(): void
    {
        config([
            'woopack.woocommerce.url' => '',
        ]);

        $this->withSession(['woopack_authenticated' => true])
            ->getJson($this->endpoint('/api/orders'))
            ->assertStatus(400)
            ->assertJson(['error' => 'WooCommerce URL not configured']);
    }

    private function endpoint(string $path): string
    {
        return "http://localhost{$path}";
    }

    private function makeOrder(int $id, string $status, float $total, string $dateCreated = '2026-04-08T10:00:00'): array
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
}
