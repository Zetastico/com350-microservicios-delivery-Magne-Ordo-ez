const BASE_URL = (
    process.env.ORDERS_URL || 'http://localhost:8000/api/v1'
).replace(/\/+$/, '');

// Consulta un pedido en el microservicio Orders
async function getOrder(orderId, authorization) {
    const response = await fetch(`${BASE_URL}/orders/${orderId}`, {
        method: 'GET',
        headers: {
            'Authorization': authorization,
            'Accept': 'application/json'
        }
    });

    const data = await response.json();

    if (!response.ok) {
        const error = new Error(
            data.message || 'Error al consultar el pedido'
        );
        error.status = response.status;
        throw error;
    }

    return data;
}

// Actualiza el estado del pedido en Orders
async function updateOrderStatus(orderId, status, authorization) {
    const response = await fetch(
        `${BASE_URL}/orders/${orderId}/status`,
        {
            method: 'PATCH',
            headers: {
                'Authorization': authorization,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ status })
        }
    );

    const data = await response.json();

    if (!response.ok) {
        const error = new Error(
            data.message || 'Error al actualizar el pedido'
        );
        error.status = response.status;
        throw error;
    }

    return data;
}

module.exports = {
    getOrder,
    updateOrderStatus
};