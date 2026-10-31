const router = require('express').Router();

const Delivery = require('../models/delivery');
const { verifyToken, requireRole } = require('./verifyToken');

const {
    getOrder,
    updateOrderStatus
} = require('../config/ordersClient');

const VALID_STATUSES = [
    'asignado',
    'recogido',
    'en_camino',
    'entregado'
];

function validId(id) {
    return Number.isInteger(Number(id)) && Number(id) > 0;
}

// GET /api/v1/deliveries?status=
// Admin ve todas; repartidor solamente las suyas.
router.get('/', verifyToken, async (req, res) => {
    try {
        const user = req.user;

        if (!['admin', 'repartidor'].includes(user.role)) {
            return res.status(403).json({
                message: 'Se requiere rol: admin o repartidor'
            });
        }

        const { status } = req.query;

        if (status && !VALID_STATUSES.includes(status)) {
            return res.status(400).json({
                message: 'Estado de entrega invalido'
            });
        }

        const filters = {
            status,
            courier_id: user.role === 'repartidor'
                ? user.id
                : undefined
        };

        const deliveries = await Delivery.findAll(filters);

        res.json(deliveries);

    } catch (error) {
        console.error('[deliveries] error al listar:', error.message);

        res.status(500).json({
            message: 'Error al listar entregas'
        });
    }
});


// GET /api/v1/deliveries/order/:orderId
// Buscar una entrega por el ID de pedido.
router.get('/order/:orderId', verifyToken, async (req, res) => {
    try {
        const { orderId } = req.params;

        if (!validId(orderId)) {
            return res.status(400).json({
                message: 'ID de pedido invalido'
            });
        }

        const delivery = await Delivery.findByOrderId(orderId);

        if (!delivery) {
            return res.status(404).json({
                message: 'Entrega no encontrada para este pedido'
            });
        }

        const order = await getOrder(
            orderId,
            req.headers.authorization
        );

        res.json({
            ...delivery,
            order
        });

    } catch (error) {
        console.error('[deliveries] error al consultar pedido:', error.message);

        res.status(error.status || 502).json({
            message: 'No se pudo consultar el pedido asociado',
            error: error.message
        });
    }
});


// GET /api/v1/deliveries/:id
// Devuelve la entrega y el pedido asociado.
router.get('/:id', verifyToken, async (req, res) => {
    try {
        const { id } = req.params;

        if (!validId(id)) {
            return res.status(400).json({
                message: 'ID de entrega invalido'
            });
        }

        const delivery = await Delivery.findById(id);

        if (!delivery) {
            return res.status(404).json({
                message: 'Entrega no encontrada'
            });
        }

        const order = await getOrder(
            delivery.order_id,
            req.headers.authorization
        );

        res.json({
            ...delivery,
            order
        });

    } catch (error) {
        console.error('[deliveries] error al consultar:', error.message);

        res.status(error.status || 502).json({
            message: 'No se pudo consultar la entrega',
            error: error.message
        });
    }
});


// POST /api/v1/deliveries
// Solo admin puede asignar una entrega.
router.post('/', verifyToken, requireRole('admin'), async (req, res) => {
    try {
        const {
            order_id,
            courier_id,
            courier_name,
            notes
        } = req.body;

        // Validaciones básicas
        if (
            !validId(order_id) ||
            !courier_id ||
            !courier_name
        ) {
            return res.status(400).json({
                message: 'order_id, courier_id y courier_name son obligatorios'
            });
        }

        if (
            typeof courier_id !== 'string' ||
            typeof courier_name !== 'string' ||
            typeof notes === 'object'
        ) {
            return res.status(400).json({
                message: 'Datos de entrega invalidos'
            });
        }

        // Comprobar que el pedido existe en Orders
        const order = await getOrder(
            order_id,
            req.headers.authorization
        );

        // Solo pedidos confirmados o en preparación pueden asignarse.
        if (!['confirmado', 'en_preparacion'].includes(order.status)) {
            return res.status(409).json({
                message: `No se puede asignar una entrega a un pedido en estado '${order.status}'`
            });
        }

        // Comprobar que no tenga una entrega previa.
        const existing = await Delivery.findByOrderId(order_id);

        if (existing) {
            return res.status(409).json({
                message: 'El pedido ya tiene una entrega asignada'
            });
        }

        // Crear entrega.
        const delivery = await Delivery.create({
            order_id,
            courier_id: courier_id.trim(),
            courier_name: courier_name.trim(),
            notes: notes || null
        });

        res.status(201).json(delivery);

    } catch (error) {
        console.error('[deliveries] error al asignar:', error.message);

        // PostgreSQL: violación de restricción UNIQUE.
        if (error.code === '23505') {
            return res.status(409).json({
                message: 'El pedido ya tiene una entrega asignada'
            });
        }

        res.status(error.status || 502).json({
            message: 'No se pudo asignar la entrega',
            error: error.message
        });
    }
});


// PUT /api/v1/deliveries/:id/status
// Cambiar estado: recogido -> en_camino -> entregado.
router.put('/:id/status', verifyToken, async (req, res) => {
    try {
        const { id } = req.params;
        const { status } = req.body;
        const user = req.user;

        if (!validId(id)) {
            return res.status(400).json({
                message: 'ID de entrega invalido'
            });
        }

        const allowedStatuses = [
            'recogido',
            'en_camino',
            'entregado'
        ];

        if (!allowedStatuses.includes(status)) {
            return res.status(400).json({
                message: 'Estado de entrega invalido'
            });
        }

        const delivery = await Delivery.findById(id);

        if (!delivery) {
            return res.status(404).json({
                message: 'Entrega no encontrada'
            });
        }

        // El repartidor solo puede modificar sus propias entregas.
        if (
            user.role !== 'admin' &&
            (
                user.role !== 'repartidor' ||
                String(user.id) !== delivery.courier_id
            )
        ) {
            return res.status(403).json({
                message: 'No autorizado para modificar esta entrega'
            });
        }

        // No permitir retroceder estados.
        const currentIndex = VALID_STATUSES.indexOf(delivery.status);
        const newIndex = VALID_STATUSES.indexOf(status);

        if (newIndex <= currentIndex) {
            return res.status(409).json({
                message: `No se puede pasar de '${delivery.status}' a '${status}'`
            });
        }

        // Actualizar primero la entrega en PostgreSQL.
        const updated = await Delivery.updateStatus(id, status);

        // Sincronizar el pedido en Laravel.
        // Recogido y en_camino se traducen a en_camino en Orders.
        const orderStatus = status === 'entregado'
            ? 'entregado'
            : 'en_camino';

        try {
            const order = await updateOrderStatus(
                delivery.order_id,
                orderStatus,
                req.headers.authorization
            );

            return res.json({
                delivery: updated,
                orderSync: {
                    ok: true,
                    status: order.status
                }
            });

        } catch (syncError) {
            console.error(
                '[deliveries] error al sincronizar Orders:',
                syncError.message
            );

            return res.status(502).json({
                message: 'La entrega se actualizo, pero no se pudo sincronizar el pedido',
                delivery: updated,
                orderSync: {
                    ok: false,
                    error: syncError.message
                }
            });
        }

    } catch (error) {
        console.error('[deliveries] error al actualizar:', error.message);

        res.status(500).json({
            message: 'Error al actualizar el estado de la entrega'
        });
    }
});


// DELETE /api/v1/deliveries/:id
// Solo administrador.
router.delete('/:id', verifyToken, requireRole('admin'), async (req, res) => {
    try {
        const { id } = req.params;

        if (!validId(id)) {
            return res.status(400).json({
                message: 'ID de entrega invalido'
            });
        }

        const deleted = await Delivery.remove(id);

        if (!deleted) {
            return res.status(404).json({
                message: 'Entrega no encontrada'
            });
        }

        res.json({
            success: true,
            message: 'Entrega eliminada',
            id: deleted.id
        });

    } catch (error) {
        console.error('[deliveries] error al eliminar:', error.message);

        res.status(500).json({
            message: 'Error al eliminar la entrega'
        });
    }
});

module.exports = router;