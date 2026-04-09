<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PackingStatus;
use App\Models\User;
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
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $orders = $this->wooCommerce->getOrders($user, [
                'status' => ($validated['status'] ?? null) === 'any' ? null : ($validated['status'] ?? null),
                'page' => $validated['page'] ?? 1,
                'per_page' => $validated['per_page'] ?? 10,
            ]);

            return response()->json($this->annotateOrders($user, $orders)->values()->all());
        } catch (WooCommerceException $exception) {
            return response()->json(['error' => $exception->getMessage()], $exception->status());
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $order = $this->wooCommerce->getOrder($user, $id);

            return response()->json($this->annotateOrder($user, $order));
        } catch (WooCommerceException $exception) {
            return response()->json(['error' => $exception->getMessage()], $exception->status());
        }
    }

    public function pack(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'packed' => ['required', 'boolean'],
        ]);

        if ($validated['packed']) {
            $user->packingStatuses()->updateOrCreate(
                ['woo_order_id' => $id],
                ['packed_at' => now()]
            );
        } else {
            $user->packingStatuses()->where('woo_order_id', $id)->delete();
        }

        return response()->json(['success' => true]);
    }

    public function stats(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $orders = collect($this->wooCommerce->getOrders($user, [
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

    private function annotateOrders(User $user, array $orders): Collection
    {
        $packedIds = $user->packingStatuses()
            ->whereIn('woo_order_id', collect($orders)->pluck('id')->filter()->all())
            ->pluck('woo_order_id')
            ->flip();

        return collect($orders)->map(function (array $order) use ($packedIds): array {
            $order['is_packed'] = $packedIds->has($order['id'] ?? null);

            return $order;
        });
    }

    private function annotateOrder(User $user, array $order): array
    {
        $order['is_packed'] = $user->packingStatuses()
            ->where('woo_order_id', $order['id'] ?? 0)
            ->exists();

        return $order;
    }
}
