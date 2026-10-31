<?php
// Configuracion propia del microservicio de pedidos.
// Los valores vienen del .env (local) o del docker-compose.yml (contenedor).
return [
// Secreto compartido con los demas microservicios para validar el JWT
'jwt_secret' => env('JWT_SECRET', 'deliverySucre2026superSecreto'),
// URL base del microservicio de restaurantes (comunicacion entre servicios)
'restaurants_url' => env('RESTAURANTS_URL', 'http://localhost:3002/api/v1'),
// Estados posibles de un pedido, en orden
'statuses' => ['pendiente', 'confirmado', 'en_preparacion', 'en_camino', 'entregado',
'cancelado'],
];