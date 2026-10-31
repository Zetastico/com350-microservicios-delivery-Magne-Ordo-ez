require('dotenv').config();

const express = require('express');
const pool = require('./config/db');

const deliveryRoutes = require('./routes/deliveryRoutes');

const app = express();

app.set('port', process.env.PORT || 3004);

// Middleware para recibir JSON.
app.use(express.json());

// Registro de solicitudes.
app.use((req, res, next) => {
    console.log(`[deliveries] ${req.method} ${req.originalUrl}`);
    next();
});

// Comprobar el estado del servicio y PostgreSQL.
app.get('/up', async (req, res) => {
    try {
        await pool.query('SELECT 1');

        res.json({
            service: 'deliveries',
            database: 'PostgreSQL',
            status: 'healthy'
        });

    } catch (error) {
        res.status(503).json({
            service: 'deliveries',
            status: 'unhealthy',
            message: error.message
        });
    }
});

// Rutas del microservicio.
app.use('/api/v1/deliveries', deliveryRoutes);

// Ruta no encontrada.
app.use((req, res) => {
    res.status(404).json({
        message: 'Ruta no encontrada en deliveries'
    });
});

// Iniciar servidor.
app.listen(app.get('port'), () => {
    console.log(
        `[deliveries] servidor escuchando en el puerto ${app.get('port')}`
    );
});