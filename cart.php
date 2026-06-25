<?php
require_once 'config/db.php';
require_once 'config/helpers.php';

if (isset($_GET['remove'])) {
    unset($_SESSION['cart'][(int) $_GET['remove']]);
    redirect('cart.php');
}

if (isset($_POST['ajax_update_qty'])) {
    header('Content-Type: application/json; charset=utf-8');

    $id = (int) ($_POST['id'] ?? 0);
    $qty = max(0, (int) ($_POST['qty'] ?? 0));
    $cart = $_SESSION['cart'] ?? [];

    if (!$id || !isset($cart[$id])) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy món trong giỏ.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($qty === 0) {
        unset($_SESSION['cart'][$id]);
    } else {
        $_SESSION['cart'][$id]['quantity'] = $qty;
    }

    $item = $_SESSION['cart'][$id] ?? null;
    $lineTotal = $item ? ($item['price'] * $item['quantity']) : 0;

    echo json_encode([
        'success' => true,
        'removed' => $qty === 0,
        'empty' => empty($_SESSION['cart']),
        'quantity' => $item['quantity'] ?? 0,
        'line_total' => money_vnd($lineTotal),
        'cart_total' => money_vnd(cart_total()),
        'cart_count' => cart_count()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_POST['update_qty'])) {
    foreach ($_POST['qty'] ?? [] as $id => $q) {
        $q = max(0, (int) $q);
        if ($q === 0) {
            unset($_SESSION['cart'][$id]);
        } else {
            $_SESSION['cart'][$id]['quantity'] = $q;
        }
    }
    redirect('cart.php');
}

if (isset($_POST['save_order'])) {
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) {
        redirect('index.php');
    }

    $type = $_POST['order_type'] === 'bank_transfer' ? 'bank_transfer' : 'cash';
    $total = cart_total();
    $code = 'AX' . date('YmdHis');
    $note = $_POST['note'] ?? '';

    $stmt = $conn->prepare("INSERT INTO orders(order_code,order_type,total_amount,note) VALUES(?,?,?,?)");
    $stmt->bind_param('ssis', $code, $type, $total, $note);
    $stmt->execute();
    $oid = $stmt->insert_id;

    $stmt2 = $conn->prepare("INSERT INTO order_items(order_id,product_id,product_name,price,quantity,subtotal) VALUES(?,?,?,?,?,?)");
    foreach ($cart as $it) {
        $sub = $it['price'] * $it['quantity'];
        $stmt2->bind_param('iisiii', $oid, $it['id'], $it['name'], $it['price'], $it['quantity'], $sub);
        $stmt2->execute();
    }

    $_SESSION['cart'] = [];
    redirect('success.php?id=' . $oid);
}

include_once 'header.php'; ?>

<div class="date">📋 Chi tiết đơn hàng</div>

<?php if (empty($_SESSION['cart'])): ?>
    <div class="card" id="cart-empty-state" style="text-align:center;padding:35px">
        Chưa có món nào.<br><br>
        <a class="btn btn-red" href="index.php">+ Chọn món</a>
    </div>
<?php else: ?>

    <div id="cart-shell">
        <div id="cart-list"><?php foreach ($_SESSION['cart'] as $it): ?>
                <div class="cart-item"><img src="<?= h($it['image_url']) ?>" alt="<?= h($it['name']) ?>" class="cart-image">
                    <div><b><?= h($it['name']) ?></b>
                        <div class="price"><?= money_vnd($it['price']) ?></div><a class="small danger"
                            href="?remove=<?= $it['id'] ?>">Xóa</a>
                    </div>
                    <div class="cart-qty-box" data-id="<?= $it['id'] ?>" data-price="<?= $it['price'] ?>">
                        <div class="cart-qty-control"><button class="cart-qty-btn" type="button" data-action="decrease"
                                aria-label="Giảm số lượng">-</button><input class="cart-qty-input" type="number" min="0"
                                name="qty[<?= $it['id'] ?>]" value="<?= $it['quantity'] ?>" inputmode="numeric" readonly><button
                                class="cart-qty-btn" type="button" data-action="increase" aria-label="Tăng số lượng">+</button>
                        </div>
                        <div class="right small cart-line-total"><?= money_vnd($it['price'] * $it['quantity']) ?></div>
                    </div>
                </div><?php endforeach; ?>
        </div>
        <form method="post" class="card" id="checkout-form">
            <div class="section-title">Hình thức</div>
            <div class="choice">
                <label class="btn btn-red">
                    <input type="radio" name="order_type" value="cash" checked>
                    <?= h(order_type_icon('cash')) ?> <?= h(order_type_label('cash')) ?>
                </label>
                <label class="btn btn-red">
                    <input type="radio" name="order_type" value="bank_transfer">
                    <?= h(order_type_icon('bank_transfer')) ?> <?= h(order_type_label('bank_transfer')) ?>
                </label>
            </div>
            <input class="input" name="note" placeholder="Ghi chú nếu có"><br><br>
            <div class="between"><b>Tổng tiền</b>
                <div class="big-total js-cart-total"><?= money_vnd(cart_total()) ?></div>
            </div>
            <br><button class="btn btn-red full" name="save_order" value="1">Xác nhận & Lưu đơn</button>
        </form>
    </div>
<?php endif; ?>

<script>
    function formatVnd(value) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(value);
    }

    function setCartCount(count) {
        document.querySelectorAll('.js-cart-count').forEach(function (node) {
            node.textContent = count;
        });
    }

    function setCartTotal(text) {
        var totalNode = document.querySelector('.js-cart-total');
        if (totalNode) totalNode.textContent = text;
    }

    function updateCartPreview() {
        var total = 0;
        document.querySelectorAll('.cart-qty-box').forEach(function (item) {
            var qtyInput = item.querySelector('.cart-qty-input');
            var price = Number(item.dataset.price || 0);
            var qty = Math.max(0, Number(qtyInput.value || 0));
            total += price * qty;
        });
        setCartTotal(formatVnd(total));
    }

    function renderEmptyCart() {
        var shell = document.getElementById('cart-shell');
        if (!shell) return;
        shell.outerHTML = '<div class="card" id="cart-empty-state" style="text-align:center;padding:35px">Chưa có món nào.<br><br><a class="btn btn-red" href="index.php">+ Chọn món</a></div>';
    }

    function setBoxBusy(box, busy) {
        box.querySelectorAll('.cart-qty-btn').forEach(function (btn) {
            btn.disabled = busy;
        });
    }

    function syncRow(box, qty) {
        var input = box.querySelector('.cart-qty-input');
        var lineTotal = box.querySelector('.cart-line-total');
        var unitPrice = Number(box.dataset.price || 0);
        var safeQty = Math.max(0, Number(qty || 0));
        input.value = safeQty;
        lineTotal.textContent = formatVnd(unitPrice * safeQty);
        updateCartPreview();
    }

    function sendQtyUpdate(box, qty, previousQty) {
        var params = new URLSearchParams();
        params.set('ajax_update_qty', '1');
        params.set('id', box.dataset.id || '0');
        params.set('qty', String(qty));

        setBoxBusy(box, true);

        fetch('cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: params.toString()
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (!data.success) throw new Error(data.message || 'Không thể cập nhật số lượng.');

            setCartCount(data.cart_count || 0);
            setCartTotal(data.cart_total || formatVnd(0));

            if (data.removed) {
                var row = box.closest('.cart-item');
                if (row) row.remove();
                if (data.empty) renderEmptyCart();
                return;
            }

            box.querySelector('.cart-qty-input').value = data.quantity || 0;
            box.querySelector('.cart-line-total').textContent = data.line_total || formatVnd(0);
        }).catch(function (error) {
            syncRow(box, previousQty);
            window.alert(error.message || 'Không thể cập nhật số lượng.');
        }).finally(function () {
            setBoxBusy(box, false);
        });
    }

    document.querySelectorAll('.cart-qty-box').forEach(function (box) {
        var input = box.querySelector('.cart-qty-input');

        box.querySelectorAll('.cart-qty-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var previousQty = Number(input.value || 0);
                var step = btn.dataset.action === 'increase' ? 1 : -1;
                var nextQty = Math.max(0, previousQty + step);

                if (nextQty === previousQty) return;

                syncRow(box, nextQty);
                sendQtyUpdate(box, nextQty, previousQty);
            });
        });
    });
</script>
<?php include_once 'footer.php'; ?>
