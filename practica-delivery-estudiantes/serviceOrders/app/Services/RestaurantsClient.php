<?php



namespace App\Services;
use Illuminate\Support\Facades\Http;
/**
* Cliente HTTP hacia el microservicio RESTAURANTS (Node + MySQL).
* Reenvia el mismo token del usuario: cada microservicio valida el JWT por su cuenta.
*/
class RestaurantsClient
{
private string $baseUrl;
public function __construct()
{
$this->baseUrl = rtrim(config('delivery.restaurants_url'), '/');
}
/** Devuelve el restaurante o null si no existe. Lanza excepcion si el servicio no responde.
*/
public function restaurant(int $id, string $bearer): ?array
{
    $url = "{$this->baseUrl}/restaurants/{$id}";

    logger()->info('DEBUG RESTAURANTS CLIENT', [
        'config_url' => config('delivery.restaurants_url'),
        'environment_url' => getenv('RESTAURANTS_URL'),
        'base_url' => $this->baseUrl,
        'request_url' => $url,
    ]);

    $response = Http::withHeaders(['Authorization' => $bearer])
        ->acceptJson()->timeout(5)
        ->get($url);

    if ($response->status() === 404) {
        return null;
    }

    $response->throw();

    return $response->json();
}
/** Devuelve el plato (con precio real) o null si no existe. */
public function dish(int $id, string $bearer): ?array
{
$response = Http::withHeaders(['Authorization' => $bearer])
->acceptJson()->timeout(5)
->get("{$this->baseUrl}/dishes/{$id}");
if ($response->status() === 404) {
return null;
}
$response->throw();
return $response->json();
}
}