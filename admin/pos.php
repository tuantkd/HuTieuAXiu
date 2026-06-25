<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

require_admin();

$page_title = 'POS bán hàng';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderType = $_POST['order_type'] ?? 'cash';
    $note = trim($_POST['note'] ?? '');
    $cartInput = json_decode($_POST['items_json'] ?? '[]', true);
    $orderType = in_array($orderType, ['cash', 'bank_transfer'], true) ? $orderType : 'cash';

    if (!is_array($cartInput) || empty($cartInput)) {
        $errorMessage = 'Vui lòng chọn ít nhất một món trước khi lưu đơn.';
    } else {
        $validatedItems = [];
        $grandTotal = 0;

        foreach ($cartInput as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            if ($productId <= 0) {
                continue;
            }

            $product = admin_fetch_one(
                'SELECT p.id, p.name, p.price, p.unit, p.image_url, c.name AS category_name
                 FROM products p
                 INNER JOIN categories c ON c.id = p.category_id
                 WHERE p.id = ? AND p.is_active = 1
                 LIMIT 1',
                'i',
                [$productId]
            );

            if (!$product) {
                continue;
            }

            $lineTotal = (int) $product['price'] * $quantity;
            $validatedItems[] = [
                'product_id' => (int) $product['id'],
                'product_name' => $product['name'],
                'quantity' => $quantity,
                'price' => (int) $product['price'],
                'subtotal' => $lineTotal,
            ];
            $grandTotal += $lineTotal;
        }

        if (empty($validatedItems)) {
            $errorMessage = 'Không có món hợp lệ để lưu đơn.';
        } else {
            $conn->begin_transaction();

            try {
                $orderCode = admin_generate_order_code();
                $orderStmt = admin_prepare(
                    'INSERT INTO orders (user_id, order_code, order_type, total_amount, note, created_at)
                     VALUES (?, ?, ?, ?, ?, NOW())'
                );
                $userId = admin_user_id();
                $orderStmt->bind_param('issis', $userId, $orderCode, $orderType, $grandTotal, $note);
                if (!$orderStmt->execute()) {
                    throw new RuntimeException($orderStmt->error);
                }
                $orderId = $orderStmt->insert_id;
                $orderStmt->close();

                $itemStmt = admin_prepare(
                    'INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                foreach ($validatedItems as $item) {
                    $itemStmt->bind_param(
                        'iisiii',
                        $orderId,
                        $item['product_id'],
                        $item['product_name'],
                        $item['price'],
                        $item['quantity'],
                        $item['subtotal']
                    );
                    if (!$itemStmt->execute()) {
                        throw new RuntimeException($itemStmt->error);
                    }
                }
                $itemStmt->close();

                $conn->commit();
                admin_flash_set('Lưu đơn ' . $orderCode . ' thành công.', 'success');
                header('Location: pos.php');
                exit;
            } catch (Throwable $exception) {
                $conn->rollback();
                $errorMessage = 'Không thể lưu đơn. Vui lòng thử lại.';
            }
        }
    }
}

$products = admin_fetch_all(
    'SELECT p.id, p.name, p.price, p.unit, p.image_url, c.name AS category_name, c.slug
     FROM products p
     INNER JOIN categories c ON c.id = p.category_id
     WHERE p.is_active = 1
     ORDER BY c.sort_order, p.id'
);
$today = admin_today();

if (is_staff()) {
    $quickOrders = admin_fetch_all(
        'SELECT id, order_code, order_type, total_amount, created_at
         FROM orders
         WHERE DATE(created_at) = ? AND user_id = ?
         ORDER BY id DESC
         LIMIT 10',
        'si',
        [$today, admin_user_id()]
    );
} else {
    $quickOrders = admin_fetch_all(
        'SELECT o.id, o.order_code, o.order_type, o.total_amount, o.created_at, u.full_name, u.username
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         WHERE DATE(o.created_at) = ?
         ORDER BY o.id DESC
         LIMIT 10',
        's',
        [$today]
    );
}

define('ADMIN_APP', true);
include __DIR__ . '/layout/header.php';
?>
<?php if ($errorMessage): ?>
    <div class="admin-alert error"><?= admin_h($errorMessage) ?></div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <div>
            <h3>Bán hàng nhanh</h3>
            <div class="helper-text">Chọn món, chỉnh số lượng, chọn ăn tại quán hoặc mang đi rồi lưu đơn ngay.</div>
        </div>
        <div class="meta-pill"><?= count($products) ?> món đang bán</div>
    </div>

    <div class="split-layout">
        <div>
            <div class="product-search">
                <input id="productSearch" type="text" placeholder="Tìm món ăn, nước uống...">
            </div>

            <div class="product-grid" id="productGrid">
                <?php foreach ($products as $product): ?>
                    <button
                        type="button"
                        class="product-card"
                        data-product-id="<?= (int) $product['id'] ?>"
                        data-product-name="<?= admin_h($product['name']) ?>"
                        data-product-price="<?= (int) $product['price'] ?>"
                        data-search="<?= admin_h(function_exists('mb_strtolower') ? mb_strtolower($product['name'] . ' ' . $product['category_name'], 'UTF-8') : strtolower($product['name'] . ' ' . $product['category_name'])) ?>"
                    >
                        <div class="product-thumb">
                            <img src="<?= admin_h(admin_media_url($product['image_url'])) ?>" alt="<?= admin_h($product['name']) ?>">
                        </div>
                        <div class="product-info">
                            <div class="product-name"><?= admin_h($product['name']) ?></div>
                            <div class="product-unit"><?= admin_h($product['category_name']) ?> • <?= admin_h($product['unit'] ?: 'phần') ?></div>
                            <div class="product-price"><?= admin_money($product['price']) ?></div>
                        </div>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pos-sidebar">
            <form method="post" id="posForm" class="card soft">
                <div class="section-head">
                    <h4>Giỏ hàng</h4>
                    <button type="button" class="button light small" id="clearCartButton">Xóa hết</button>
                </div>

                <div id="cartList" class="cart-list">
                    <div class="empty-state">Chưa có món nào trong giỏ.</div>
                </div>

                <div class="field" style="margin-top:18px;">
                    <label>Loại đơn</label>
                    <div class="order-type-grid">
                        <label class="order-type-option">
                            <input type="radio" name="order_type" value="cash" checked>
                            <span>Ăn tại quán</span>
                        </label>
                        <label class="order-type-option">
                            <input type="radio" name="order_type" value="bank_transfer">
                            <span>Mang đi</span>
                        </label>
                    </div>
                </div>

                <div class="field" style="margin-top:18px;">
                    <label for="note">Ghi chú đơn hàng</label>
                    <textarea id="note" name="note" placeholder="Ví dụ: ít đá, bàn số 5, khách mang đi..."></textarea>
                </div>

                <div class="total-box" style="margin-top:18px;">
                    <span>Tổng tiền</span>
                    <strong id="cartTotal">0đ</strong>
                </div>

                <input type="hidden" name="items_json" id="itemsJson">

                <div class="actions" style="margin-top:18px;">
                    <button type="button" class="button" id="saveOrderButton">Lưu đơn</button>
                </div>

                <div class="section-head" style="margin-top:22px;">
                    <div>
                        <h4>Đơn vừa bán hôm nay</h4>
                        <div class="helper-text">
                            <?= is_staff() ? 'Bạn chỉ xem được đơn do chính mình bán.' : 'Admin xem được toàn bộ đơn trong ngày, kể cả đơn chưa gán nhân viên.' ?>
                        </div>
                    </div>
                </div>

                <div class="quick-order-list">
                    <?php if (empty($quickOrders)): ?>
                        <div class="empty-state">Hôm nay chưa có đơn nào.</div>
                    <?php else: ?>
                        <?php foreach ($quickOrders as $order): ?>
                            <div class="quick-order-card">
                                <div>
                                    <strong><?= admin_h($order['order_code'] ?: ('#' . $order['id'])) ?></strong>
                                    <div class="product-meta">
                                        <?= admin_h(admin_order_type_label($order['order_type'])) ?> • <?= admin_h(date('H:i', strtotime($order['created_at']))) ?>
                                        <?php if (!is_staff()): ?>
                                            • <?= admin_h(admin_order_seller_label($order)) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="actions">
                                    <span class="meta-pill"><?= admin_money($order['total_amount']) ?></span>
                                    <a class="button light small" href="order_detail.php?id=<?= (int) $order['id'] ?>">Xem</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const cart = [];
const productCards = document.querySelectorAll('.product-card');
const productSearch = document.getElementById('productSearch');
const cartList = document.getElementById('cartList');
const cartTotal = document.getElementById('cartTotal');
const itemsJson = document.getElementById('itemsJson');
const posForm = document.getElementById('posForm');

function formatMoney(value) {
    return new Intl.NumberFormat('vi-VN').format(value) + 'đ';
}

function addToCart(productId, productName, productPrice) {
    const existing = cart.find(item => item.product_id === productId);
    if (existing) {
        existing.quantity += 1;
    } else {
        cart.push({
            product_id: productId,
            name: productName,
            price: Number(productPrice),
            quantity: 1
        });
    }
    renderCart();
}

function changeQuantity(index, delta) {
    if (!cart[index]) {
        return;
    }
    cart[index].quantity = Math.max(1, cart[index].quantity + delta);
    renderCart();
}

function removeItem(index) {
    cart.splice(index, 1);
    renderCart();
}

function clearCart() {
    cart.length = 0;
    renderCart();
}

function renderCart() {
    if (cart.length === 0) {
        cartList.innerHTML = '<div class="empty-state">Chưa có món nào trong giỏ.</div>';
        cartTotal.textContent = '0đ';
        itemsJson.value = '[]';
        return;
    }

    let total = 0;
    cartList.innerHTML = '';

    cart.forEach((item, index) => {
        total += item.price * item.quantity;
        const wrapper = document.createElement('div');
        wrapper.className = 'cart-item';
        wrapper.innerHTML = `
            <div>
                <h4>${item.name}</h4>
                <div class="product-meta">${formatMoney(item.price)} x ${item.quantity}</div>
                <div class="product-price">${formatMoney(item.price * item.quantity)}</div>
            </div>
            <div class="qty-box">
                <button type="button" class="button light small" data-action="minus" data-index="${index}">-</button>
                <span>${item.quantity}</span>
                <button type="button" class="button light small" data-action="plus" data-index="${index}">+</button>
                <button type="button" class="button danger small" data-action="remove" data-index="${index}">X</button>
            </div>
        `;
        cartList.appendChild(wrapper);
    });

    cartTotal.textContent = formatMoney(total);
    itemsJson.value = JSON.stringify(cart.map(item => ({
        product_id: item.product_id,
        quantity: item.quantity
    })));
}

productCards.forEach(card => {
    card.addEventListener('click', () => {
        addToCart(
            Number(card.dataset.productId),
            card.dataset.productName,
            Number(card.dataset.productPrice)
        );
    });
});

productSearch.addEventListener('input', () => {
    const keyword = productSearch.value.trim().toLowerCase();
    productCards.forEach(card => {
        const visible = card.dataset.search.includes(keyword);
        card.style.display = visible ? 'flex' : 'none';
    });
});

cartList.addEventListener('click', event => {
    const button = event.target.closest('button[data-action]');
    if (!button) {
        return;
    }

    const index = Number(button.dataset.index);
    const action = button.dataset.action;

    if (action === 'minus') {
        changeQuantity(index, -1);
    } else if (action === 'plus') {
        changeQuantity(index, 1);
    } else if (action === 'remove') {
        removeItem(index);
    }
});

document.getElementById('clearCartButton').addEventListener('click', clearCart);
document.getElementById('saveOrderButton').addEventListener('click', () => {
    if (cart.length === 0) {
        alert('Giỏ hàng đang trống.');
        return;
    }
    posForm.submit();
});

renderCart();
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
