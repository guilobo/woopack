<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PackingStatus;
use App\Services\WooCommerceException;
use App\Services\WooCommerceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OrderController extends Controller
{
    public function __construct(private readonly WooCommerceService $wooCommerce)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $orders = $this->wooCommerce->getOrders([
                'status' => ($validated['status'] ?? null) === 'any' ? null : ($validated['status'] ?? null),
                'page' => $validated['page'] ?? 1,
                'per_page' => $validated['per_page'] ?? 10,
            ]);

            return response()->json($this->annotateOrders($orders)->values()->all());
        } catch (WooCommerceException $exception) {
            return response()->json(['error' => $exception->getMessage()], $exception->status());
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $order = $this->wooCommerce->getOrder($id);

            return response()->json($this->annotateOrder($order));
        } catch (WooCommerceException $exception) {
            return response()->json(['error' => $exception->getMessage()], $exception->status());
        }
    }

    public function pack(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'packed' => ['required', 'boolean'],
        ]);

        if ($validated['packed']) {
            PackingStatus::query()->updateOrCreate(
                ['woo_order_id' => $id],
                ['packed_at' => now()]
            );
        } else {
            PackingStatus::query()->where('woo_order_id', $id)->delete();
        }

        return response()->json(['success' => true]);
    }

    public function stats(): JsonResponse
    {
        try {
            $orders = collect($this->wooCommerce->getOrders([
                'per_page' => 50,
            ]));

            return response()->json([
                'total_orders' => $orders->count(),
                'total_sales' => $orders->sum(fn (array $order) => (float) ($order['total'] ?? 0)),
                'status_counts' => $orders
                    ->groupBy(fn (array $order) => $order['status'] ?? 'unknown')
                    ->map(fn (Collection $group) => $group->count())
                    ->all(),
                'daily_sales' => $orders
                    ->groupBy(function (array $order): ?string {
                        $createdAt = $order['date_created'] ?? null;

                        return is_string($createdAt) ? explode('T', $createdAt)[0] : null;
                    })
                    ->filter(fn (Collection $group, ?string $date) => filled($date))
                    ->map(fn (Collection $group) => $group->sum(fn (array $order) => (float) ($order['total'] ?? 0)))
                    ->all(),
            ]);
        } catch (WooCommerceException $exception) {
            return response()->json(['error' => $exception->getMessage()], $exception->status());
        }
    }

    private function annotateOrders(array $orders): Collection
    {
        $packedIds = PackingStatus::query()
            ->whereIn('woo_order_id', collect($orders)->pluck('id')->filter()->all())
            ->pluck('woo_order_id')
            ->flip();

        return collect($orders)->map(function (array $order) use ($packedIds): array {
            $order['is_packed'] = $packedIds->has($order['id'] ?? null);

            return $order;
        });
    }

    private function annotateOrder(array $order): array
    {
        $order['is_packed'] = PackingStatus::query()
            ->where('woo_order_id', $order['id'] ?? 0)
            ->exists();

        return $order;
    }
}
