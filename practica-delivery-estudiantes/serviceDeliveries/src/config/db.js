const { Pool } = require('pg');

const pool = new Pool({
    host: process.env.PG_HOST || 'localhost',
    port: Number(process.env.PG_PORT) || 5432,
    user: process.env.PG_USER || 'delivery',
    password: process.env.PG_PASSWORD || '',
    database: process.env.PG_DATABASE || 'bd_entregas',
    max: 10
});

// Comprobar la conexión
pool.connect()
    .then(client => {
        console.log(
            `[deliveries] conectado a PostgreSQL en ${process.env.PG_HOST}:${process.env.PG_PORT}`
        );
        client.release();
    })
    .catch(error => {
        console.error('[deliveries] error al conectar a PostgreSQL:', error.message);
    });

pool.on('error', error => {
    console.error('[deliveries] error inesperado en PostgreSQL:', error.message);
});

module.exports = pool;