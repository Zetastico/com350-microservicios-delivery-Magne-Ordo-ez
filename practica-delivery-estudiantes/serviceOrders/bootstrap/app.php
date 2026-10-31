<?php
use App\Http\Middleware\VerifyJwt;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
return Application::configure(basePath: dirname(__DIR__))
->withRouting(
web: __DIR__.'/../routes/web.php',
api: __DIR__.'/../routes/api.php', // todo lo de api.php queda bajo el prefijo /api
commands: __DIR__.'/../routes/console.php',
health: '/up',
)
->withMiddleware(function (Middleware $middleware): void {
// Alias para usar ->middleware('jwt') en las rutas
$middleware->alias([
'jwt' => VerifyJwt::class,
]);
})
->withExceptions(function (Exceptions $exceptions): void {
// Este servicio es solo API: SIEMPRE responder errores en JSON
$exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e) => true);
})->create();