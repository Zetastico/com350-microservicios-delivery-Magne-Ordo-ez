const pool = require('../config/db');

const Delivery = {

    // Listar entregas con filtros opcionales
    async findAll({ status, courier_id } = {}) {
        let sql = 'SELECT * FROM deliveries WHERE 1=1';
        const params = [];

        if (status) {
            params.push(status);
            sql += ` AND status = $${params.length}`;
        }

        if (courier_id) {
            params.push(courier_id);
            sql += ` AND courier_id = $${params.length}`;
        }

        sql += ' ORDER BY assigned_at DESC';

        const result = await pool.query(sql, params);
        return result.rows;
    },

    // Buscar entrega por ID
    async findById(id) {
        const result = await pool.query(
            'SELECT * FROM deliveries WHERE id = $1',
            [id]
        );

        return result.rows[0] || null;
    },

    // Buscar entrega por ID de pedido
    async findByOrderId(orderId) {
        const result = await pool.query(
            'SELECT * FROM deliveries WHERE order_id = $1',
            [orderId]
        );

        return result.rows[0] || null;
    },

    // Crear una entrega
    async create(data) {
        const result = await pool.query(
            `INSERT INTO deliveries
                (order_id, courier_id, courier_name, notes)
             VALUES ($1, $2, $3, $4)
             RETURNING *`,
            [
                data.order_id,
                data.courier_id,
                data.courier_name,
                data.notes || null
            ]
        );

        return result.rows[0];
    },

    // Actualizar el estado
    async updateStatus(id, status) {
        const result = await pool.query(
            `UPDATE deliveries
             SET status = $1,
                 delivered_at = CASE
                     WHEN $1 = 'entregado' THEN NOW()
                     ELSE delivered_at
                 END
             WHERE id = $2
             RETURNING *`,
            [status, id]
        );

        return result.rows[0] || null;
    },

    // Eliminar una entrega
    async remove(id) {
        const result = await pool.query(
            'DELETE FROM deliveries WHERE id = $1 RETURNING *',
            [id]
        );

        return result.rows[0] || null;
    }
};

module.exports = Delivery;