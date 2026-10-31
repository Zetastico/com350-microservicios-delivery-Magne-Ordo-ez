<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\RestaurantsClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private RestaurantsClient $restaurants)
    {
    }

    /**
     * GET /api/v1/orders?status=pendiente (admin/repartidor: todos; cliente: solo los suyos)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $query = Order::with('items')->orderByDesc('created_at');

        if ($user['role'] === 'cliente') {
            $query->where('user_id', $user['id']);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->get());
    }

    /** 
     * GET /api/v1/orders/{id} 
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $order = Order::with('items')->find($id);

        if (! $order) {
            return response()->json(['message' => 'Pedido no encontrado'], 404);
        }

        if ($user['role'] === 'cliente' && $order->user_id !== $user['id']) {
            return response()->json(['message' => 'Este pedido no es suyo'], 403);
        }

        return response()->json($order);
    }

    /**
     * POST /api/v1/orders
     * { restaurant_id, delivery_address, notes?, items: [ { dish_id, quantity } ] }
     *
     * El precio NO lo envía el cliente: se consulta al microservicio RESTAURANTS
     * para que nadie pueda "inventar" precios. Todo se guarda en una transacción.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $bearer = $request->header('Authorization');

        logger()->info('DEBUG DATABASE HTTP', [
            'getenv_DB_HOST' => getenv('DB_HOST'),
            'config_DB_HOST' => config('database.connections.pgsql.host'),
            'default_connection' => config('database.default'),
            'connection_DB_HOST' => DB::connection('pgsql')->getConfig('host'),
            'database' => config('database.connections.pgsql.database'),
        ]);

        $data = $request->validate([
            'restaurant_id' => ['required', 'integer', 'min:1'],
            'delivery_address' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.dish_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        // 1) Validar el restaurante contra el servicio restaurants
        $restaurant = $this->restaurants->restaurant($data['restaurant_id'], $bearer);

        if (! $restaurant) {
            return response()->json(['message' => 'El restaurante no existe'], 422);
        }

        if (! $restaurant['active']) {
            return response()->json(['message' => 'El restaurante no está activo'], 422);
        }

        // 2) Validar cada plato y calcular subtotales con el precio REAL
        $items = [];
        $total = 0;

        foreach ($data['items'] as $line) {
            $dish = $this->restaurants->dish($line['dish_id'], $bearer);

            if (! $dish) {
                return response()->json(['message' => "El plato {$line['dish_id']} no existe"], 422);
            }

            if ((int) $dish['restaurant_id'] !== (int) $data['restaurant_id']) {
                return response()->json(['message' => "El plato '{$dish['name']}' no pertenece a este restaurante"], 422);
            }

            if (! $dish['available']) {
                return response()->json(['message' => "El plato '{$dish['name']}' no está disponible"], 422);
            }

            $subtotal = round($dish['price'] * $line['quantity'], 2);
            $total += $subtotal;

            $items[] = [
                'dish_id' => $dish['id'],
                'dish_name' => $dish['name'],
                'unit_price' => $dish['price'],
                'quantity' => $line['quantity'],
                'subtotal' => $subtotal,
            ];
        }

        // 3) Guardar pedido + detalle en una transacción (PostgreSQL)
        $order = DB::transaction(function () use ($data, $user, $restaurant, $items, $total) {
            $order = Order::create([
                'user_id' => $user['id'],
                'user_name' => $user['name'] ?? 'cliente',
                'restaurant_id' => $restaurant['id'],
                'restaurant_name' => $restaurant['name'],
                'delivery_address' => $data['delivery_address'],
                'notes' => $data['notes'] ?? null,
                'status' => 'pendiente',
                'total' => $total,
            ]);

            $order->items()->createMany($items);

            return $order->load('items');
        });

        return response()->json($order, 201);
    }

    /**
     * PATCH /api/v1/orders/{id}/status { status }
     * - cliente: solo puede cancelar su pedido mientras esté "pendiente"
     * - admin: confirmado, en_preparacion, cancelado
     * - repartidor (o el servicio deliveries con su token): en_camino, entregado
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $statuses = config('delivery.statuses');

        $data = $request->validate([
            'status' => ['required', Rule::in($statuses)],
        ]);

        $new = $data['status'];
        $order = Order::with('items')->find($id);

        if (! $order) {
            return response()->json(['message' => 'Pedido no encontrado'], 404);
        }

        $allowed = match ($user['role']) {
            'admin' => ['confirmado', 'en_preparacion', 'en_camino', 'entregado', 'cancelado'],
            'repartidor' => ['en_camino', 'entregado'],
            default => ['cancelado'],
        };

        if (! in_array($new, $allowed, true)) {
            return response()->json(['message' => "El rol {$user['role']} no puede poner el estado '{$new}'"], 403);
        }

        if ($user['role'] === 'cliente' && ($order->user_id !== $user['id'] || $order->status !== 'pendiente')) {
            return response()->json(['message' => 'Solo puede cancelar sus pedidos pendientes'], 403);
        }

        if (in_array($order->status, ['entregado', 'cancelado'], true)) {
            return response()->json(['message' => "El pedido ya está {$order->status}"], 409);
        }

        // No se puede retroceder en el flujo (excepto cancelar)
        if ($new !== 'cancelado' && array_search($new, $statuses) <= array_search($order->status, $statuses)) {
            return response()->json(['message' => "No se puede pasar de '{$order->status}' a '{$new}'"], 409);
        }

        $order->update(['status' => $new]);

        return response()->json($order);
    }

    /** 
     * DELETE /api/v1/orders/{id} (solo admin) 
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        if ($user['role'] !== 'admin') {
            return response()->json(['message' => 'Se requiere rol: admin'], 403);
        }

        $order = Order::find($id);

        if (! $order) {
            return response()->json(['message' => 'Pedido no encontrado'], 404);
        }

        $order->delete(); // los items se borran en cascada

        return response()->json([
            'success' => true, 
            'message' => 'Pedido eliminado', 
            'id' => $id,
        ]);
    }
}