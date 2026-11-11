<?php
/** Receive stock. From ProductController::receiveForm: $products, $selectedId, $today, $csrfToken. */
$pageTitle = 'Receive stock';
$active = 'receive';

require APP_ROOT . '/app/views/partials/header.php';
?>
<main class="page narrow">
    <div class="page-title">
        <div>
            <p class="eyebrow">INVENTORY</p>
            <h1>Receive stock</h1>
            <p>Scan a product (barcode or RFID tag) or pick it from the list, then enter the quantity received.</p>
        </div>
    </div>

    <?php if ($products === []): ?>
        <div class="empty"><p>Add a product before receiving stock.</p><a class="button" href="/products/create">Add product</a></div>
    <?php else: ?>
    <section class="card scan-card">
        <label for="scanBox">Scan a product</label>
        <input type="text" id="scanBox" placeholder="Scan a barcode here, or place an RFID tag on the reader" autocomplete="off" autofocus>
    </section>

    <div id="message" class="form-message" hidden></div>

    <form id="receiveForm" class="card form-grid" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

        <div class="field span-2">
            <label for="product_id">Product</label>
            <select id="product_id" name="product_id" required>
                <option value="">Choose a product...</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= $product['id'] ?>" data-name="<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>"
                        <?= $product['id'] === $selectedId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($product['name']) ?> (in stock: <?= $product['in_stock'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="field-error" id="error-product_id"></p>
        </div>

        <div class="field">
            <label for="quantity">Quantity received</label>
            <input type="number" id="quantity" name="quantity" min="1" max="100000" step="1" placeholder="24" required>
            <p class="field-error" id="error-quantity"></p>
        </div>

        <div class="field">
            <label for="received_at">Date received</label>
            <input type="date" id="received_at" name="received_at" value="<?= htmlspecialchars($today, ENT_QUOTES) ?>" max="<?= htmlspecialchars($today, ENT_QUOTES) ?>">
            <p class="field-error" id="error-received_at"></p>
        </div>

        <div class="form-actions span-2">
            <button type="submit" id="submitBtn">Record reception</button>
        </div>
    </form>

    <section class="card" id="historyCard" hidden>
        <h2>Received just now</h2>
        <ul class="plain-list" id="history"></ul>
    </section>
    <?php endif; ?>
</main>

<?php require APP_ROOT . '/app/views/partials/footer.php'; ?>
<script>
    const form = document.getElementById('receiveForm');
    if (form) {
        const message = document.getElementById('message');
        const select = document.getElementById('product_id');
        const quantity = document.getElementById('quantity');
        const scanBox = document.getElementById('scanBox');

        async function selectByCode(code) {
            try {
                const product = await lookupProduct(code);
                if (!product) {
                    notify('No product has the code ' + code + '. Add it first.', 'error');
                    return;
                }
                select.value = String(product.id);
                notify(product.name + ' selected.', 'info');
                quantity.focus();
            } catch (error) {
                notify('Could not look up the product right now.', 'error');
            }
        }

        // Barcode scanner (or RFID reader in keyboard mode) types into the scan box.
        wireScanInput(scanBox, selectByCode);

        // RFID reader through scripts/rfid.py.
        createRfidFeed(async function (epcs) { await selectByCode(epcs[0]); }, 700).start();

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            hideMessage(message);
            clearFormErrors(form);
            const button = document.getElementById('submitBtn');
            button.disabled = true;
            try {
                const data = await postForm('receptions', form);
                if (data.status === 'success') {
                    notify(data.message, 'success');
                    showMessage(message, data.message, true);
                    const option = select.querySelector('option[value="' + data.product.id + '"]');
                    option.textContent = data.product.name + ' (in stock: ' + data.product.in_stock + ')';
                    const item = document.createElement('li');
                    item.textContent = '+' + data.reception.quantity + ' × ' + data.product.name + ' (' + data.reception.received_at + ') → ' + data.product.in_stock + ' in stock';
                    document.getElementById('history').prepend(item);
                    document.getElementById('historyCard').hidden = false;
                    quantity.value = '';
                    scanBox.focus();
                } else {
                    showFormErrors(form, data.errors || {});
                    showMessage(message, data.message, false);
                    notify(data.message, 'error');
                }
            } catch (error) {
                showMessage(message, 'Could not reach the server. Please try again.', false);
            }
            button.disabled = false;
        });
    }
</script>
</body>
</html>
