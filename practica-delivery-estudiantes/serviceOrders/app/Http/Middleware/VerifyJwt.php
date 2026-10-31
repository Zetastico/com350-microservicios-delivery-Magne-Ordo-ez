<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
/**
* Valida el token JWT (HS256) emitido por el microservicio USERS (Node.js).
*
* Los cuatro microservicios comparten el mismo JWT_SECRET, por eso este servicio
* puede verificar la firma SIN llamar al servicio de usuarios. La verificacion se
* hace "a mano" para mostrar como funciona un JWT: header.payload.firma en base64url.
*/
class VerifyJwt
{
    public function handle(Request $request, Closure $next): Response
    {
    $header = $request->header('Authorization', '');
    if (! str_starts_with($header, 'Bearer ')) {
    return response()->json(['auth' => false, 'message' => 'Token no proporcionado'],
    401);
    }
    $payload = $this->decode(substr($header, 7));
    if ($payload === null) {
    return response()->json(['auth' => false, 'message' => 'Token invalido o expirado'],
    401);
    }
    // Dejamos el usuario autenticado disponible para los controladores
    $request->attributes->set('auth_user', $payload); 
    return $next($request);
    }

    private function decode(string $token): ?array
    {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
    return null;
    }
    [$header64, $payload64, $signature64] = $parts;
    $header = json_decode($this->base64UrlDecode($header64), true);
    if (($header['alg'] ?? null) !== 'HS256') {
    return null; // solo aceptamos el algoritmo que usa el servicio users
    }
    // Recalcular la firma con el secreto compartido y comparar en tiempo constante
    $expected = hash_hmac('sha256', "$header64.$payload64", config('delivery.jwt_secret'),
    true);
    if (! hash_equals($expected, $this->base64UrlDecode($signature64))) {
    return null;
    }
    $payload = json_decode($this->base64UrlDecode($payload64), true);
    if (! is_array($payload) || (isset($payload['exp']) && $payload['exp'] < time())) {
    return null; // expirado
    }
    return $payload;
    }
    private function base64UrlDecode(string $data): string
    {
    $remainder = strlen($data) % 4;
    if ($remainder) {
    $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}