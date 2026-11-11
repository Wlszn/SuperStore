<?php
/** Inventory dashboard. From ProductController::index: $products, $lowStock, $csrfToken. */
$pageTitle = 'Inventory';
$active = 'products';

$totalUnits = array_sum(array_map(fn($p) => max(0, $p['in_stock']), $products));
$lowCount = count(array_filter($products, fn($p) => $p['low_stock']));
$categories = array_values(array_unique(array_map(fn($p) => $p['category'], $products)));
sort($categories);

require APP_ROOT . '/app/views/partials/header.php';
?>
<main class="page">
    <div class="page-title">
        <div>
            <p class="eyebrow">PRODUCT MANAGEMENT</p>
            <h1>Inventory</h1>
            <p>Every product with the quantity in stock (received minus sold).</p>
        </div>
        <div class="title-actions">
            <a class="button secondary" href="/products/receive">Receive stock</a>
            <a class="button" href="/products/create">+ Add product</a>
        </div>
    </div>

    <section class="stats">
        <div class="stat"><span>Products</span><strong><?= count($products) ?></strong></div>
        <div class="stat"><span>Units in stock</span><strong><?= $totalUnits ?></strong></div>
        <div class="stat <?= $lowCount > 0 ? 'warning' : '' ?>"><span>Low stock (≤ <?= (int) $lowStock ?>)</span><strong><?= $lowCount ?></strong></div>
    </section>

    <div class="toolbar">
        <input type="text" id="search" placeholder="Search by name, producer, UPC or EPC..." aria-label="Search products">
        <select id="categoryFilter" aria-label="Filter by category">
            <option value="">All categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= htmlspecialchars($category, ENT_QUOTES) ?>"><?= htmlspecialchars($category) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="inline-check"><input type="checkbox" id="lowOnly"> Low stock only</label>
    </div>

    <?php if ($products === []): ?>
        <div class="empty">
            <p>No products yet.</p>
            <a class="button" href="/products/create">Add the first product</a>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table" id="productTable">
            <thead>
            <tr>
                <th>Product</th><th>Category</th><th class="num">Price</th>
                <th>UPC</th><th>EPC</th><th class="num">In stock</th><th class="actions-col">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $product): ?>
                <tr data-id="<?= $product['id'] ?>"
                    data-category="<?= htmlspecialchars($product['category'], ENT_QUOTES) ?>"
                    data-low="<?= $product['low_stock'] ? '1' : '0' ?>"
                    data-search="<?= htmlspecialchars(strtolower($product['name'] . ' ' . $product['producer'] . ' ' . $product['upc'] . ' ' . $product['epc']), ENT_QUOTES) ?>">
                    <td>
                        <div class="product-cell">
                            <?php if ($product['image_url']): ?>
                                <img src="<?= htmlspecialchars($product['image_url'], ENT_QUOTES) ?>" alt="" class="thumb" loading="lazy">
                            <?php else: ?>
                                <span class="thumb placeholder"><?= htmlspecialchars(mb_substr($product['name'], 0, 1)) ?></span>
                            <?php endif; ?>
                            <div>
                                <a href="/products/show?id=<?= $product['id'] ?>" class="product-name"><?= htmlspecialchars($product['name']) ?></a>
                                <small><?= htmlspecialchars($product['producer']) ?></small>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($product['category']) ?></td>
                    <td class="num">$<?= number_format($product['price'], 2) ?></td>
                    <td class="code"><?= htmlspecialchars($product['upc'] ?? '—') ?></td>
                    <td class="code" title="<?= htmlspecialchars($product['epc'] ?? '', ENT_QUOTES) ?>"><?= $product['epc'] ? htmlspecialchars(substr($product['epc'], 0, 8)) . '…' . htmlspecialchars(substr($product['epc'], -4)) : '—' ?></td>
                    <td class="num">
                        <span class="badge <?= $product['in_stock'] <= 0 ? 'danger' : ($product['low_stock'] ? 'warning' : 'ok') ?>">
                            <?= $product['in_stock'] ?>
                        </span>
                    </td>
                    <td class="actions-col">
                        <a class="link-button" href="/products/receive?id=<?= $product['id'] ?>">Receive</a>
                        <a class="link-button" href="/products/edit?id=<?= $product['id'] ?>">Edit</a>
                        <button type="button" class="link-button danger" data-delete="<?= $product['id'] ?>" data-name="<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="empty-filter" id="noMatch" hidden>No product matches your search.</p>
    </div>
    <?php endif; ?>
</main>

<div class="modal-backdrop" id="deleteModal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="deleteTitle">
        <h2 id="deleteTitle">Delete product?</h2>
        <p id="deleteText"></p>
        <p class="muted">Its reception history is deleted too. Past receipts keep the product's name and price.</p>
        <div class="modal-actions">
            <button type="button" class="secondary" id="cancelDelete">Cancel</button>
            <button type="button" class="danger" id="confirmDelete">Delete</button>
        </div>
    </div>
</div>

<?php require APP_ROOT . '/app/views/partials/footer.php'; ?>
<script>
    const csrfToken = <?= json_encode($csrfToken) ?>;
    const rows = Array.from(document.querySelectorAll('#productTable tbody tr'));
    const search = document.getElementById('search');
    const categoryFilter = document.getElementById('categoryFilter');
    const lowOnly = document.getElementById('lowOnly');

    function applyFilters() {
        const term = search.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(function (row) {
            const show = (term === '' || row.dataset.search.includes(term))
                && (categoryFilter.value === '' || row.dataset.category === categoryFilter.value)
                && (!lowOnly.checked || row.dataset.low === '1');
            row.hidden = !show;
            if (show) visible++;
        });
        const noMatch = document.getElementById('noMatch');
        if (noMatch) noMatch.hidden = visible > 0;
    }
    [search, categoryFilter, lowOnly].forEach(function (el) {
        el.addEventListener('input', applyFilters);
        el.addEventListener('change', applyFilters);
    });

    // A barcode scanned into the search box jumps straight to that product.
    wireScanInput(search, async function (code) {
        search.value = code;
        applyFilters();
        try {
            const product = await lookupProduct(code);
            if (product) {
                window.location.href = '/products/show?id=' + product.id;
            } else {
                notify('No product has the code ' + code + '.', 'error');
            }
        } catch (error) {
            notify('Could not search right now.', 'error');
        }
    });

    // Delete with a confirmation dialog.
    const modal = document.getElementById('deleteModal');
    let pendingDelete = null;
    document.querySelectorAll('[data-delete]').forEach(function (button) {
        button.addEventListener('click', function () {
            pendingDelete = button;
            document.getElementById('deleteText').textContent = 'Delete "' + button.dataset.name + '"?';
            modal.hidden = false;
            document.getElementById('cancelDelete').focus();
        });
    });
    document.getElementById('cancelDelete').addEventListener('click', function () { modal.hidden = true; });
    document.getElementById('confirmDelete').addEventListener('click', async function () {
        const body = new FormData();
        body.append('id', pendingDelete.dataset.delete);
        body.append('csrf_token', csrfToken);
        try {
            const data = await (await fetch('/products/delete', { method: 'POST', body: body })).json();
            notify(data.message, data.status === 'success' ? 'success' : 'error');
            if (data.status === 'success') {
                pendingDelete.closest('tr').remove();
            }
        } catch (error) {
            notify('Could not reach the server. Please try again.', 'error');
        }
        modal.hidden = true;
    });
</script>
</body>
</html>
