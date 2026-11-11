<?php
/** Add / edit a product. From ProductController::create|edit: $product (null when adding), $csrfToken. */
$editing = $product !== null;
$pageTitle = $editing ? 'Edit ' . $product['name'] : 'Add product';
$active = 'products';
$value = fn(string $field) => htmlspecialchars((string) ($product[$field] ?? ''), ENT_QUOTES);

require APP_ROOT . '/app/views/partials/header.php';
?>
<main class="page narrow">
    <a class="back-link" href="<?= $editing ? '/products/show?id=' . $product['id'] : '/products' ?>">← Back</a>
    <div class="page-title">
        <div>
            <p class="eyebrow">PRODUCT MANAGEMENT</p>
            <h1><?= $editing ? 'Edit product' : 'Add a product' ?></h1>
            <p>Scan the barcode into the UPC field and read the RFID tag into the EPC field.</p>
        </div>
    </div>

    <div id="message" class="form-message" hidden></div>

    <form id="productForm" class="card form-grid" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= $product['id'] ?>">
        <?php endif; ?>

        <div class="field span-2">
            <label for="name">Product name</label>
            <input type="text" id="name" name="name" maxlength="100" value="<?= $value('name') ?>" placeholder="Coca-Cola 500ml" required>
            <p class="field-error" id="error-name"></p>
        </div>

        <div class="field">
            <label for="category">Category</label>
            <input type="text" id="category" name="category" maxlength="50" value="<?= $value('category') ?>" placeholder="Beverage" list="categoryList" required>
            <datalist id="categoryList">
                <option value="Beverage"><option value="Snacks"><option value="Dairy"><option value="Bakery">
                <option value="Produce"><option value="Frozen"><option value="Household"><option value="Personal care">
            </datalist>
            <p class="field-error" id="error-category"></p>
        </div>

        <div class="field">
            <label for="price">Price ($)</label>
            <input type="text" id="price" name="price" inputmode="decimal" value="<?= $editing ? number_format($product['price'], 2, '.', '') : '' ?>" placeholder="2.49" required>
            <p class="field-error" id="error-price"></p>
        </div>

        <div class="field">
            <label for="upc">UPC (barcode)</label>
            <input type="text" id="upc" name="upc" maxlength="13" inputmode="numeric" value="<?= $value('upc') ?>" placeholder="Click here, then scan the barcode" autocomplete="off">
            <p class="field-hint">8 to 13 digits. A 12-digit UPC is saved with a leading 0.</p>
            <p class="field-error" id="error-upc"></p>
        </div>

        <div class="field">
            <label for="epc">EPC (RFID tag)</label>
            <div class="input-with-button">
                <input type="text" id="epc" name="epc" maxlength="24" value="<?= $value('epc') ?>" placeholder="Up to 24 hex characters" autocomplete="off">
                <button type="button" class="secondary" id="readTag">Read tag</button>
            </div>
            <p class="field-hint" id="tagHint">Click "Read tag", then place the tag on the reader.</p>
            <p class="field-error" id="error-epc"></p>
        </div>

        <div class="field span-2">
            <label for="producer">Producer company</label>
            <input type="text" id="producer" name="producer" maxlength="100" value="<?= $value('producer') ?>" placeholder="The Coca-Cola Company" required>
            <p class="field-error" id="error-producer"></p>
        </div>

        <div class="field span-2">
            <label for="image_url">Image address (optional)</label>
            <input type="text" id="image_url" name="image_url" value="<?= $value('image_url') ?>" placeholder="https://...">
            <p class="field-error" id="error-image_url"></p>
        </div>

        <div class="form-actions span-2">
            <a class="button secondary" href="<?= $editing ? '/products/show?id=' . $product['id'] : '/products' ?>">Cancel</a>
            <button type="submit" id="submitBtn"><?= $editing ? 'Save changes' : 'Add product' ?></button>
        </div>
    </form>
</main>

<?php require APP_ROOT . '/app/views/partials/footer.php'; ?>
<script>
    const form = document.getElementById('productForm');
    const message = document.getElementById('message');
    const submitBtn = document.getElementById('submitBtn');
    const editing = <?= $editing ? 'true' : 'false' ?>;

    // Scanners press Enter after the code: go to the next field instead of submitting.
    function enterGoesTo(fieldId, nextId) {
        document.getElementById(fieldId).addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                document.getElementById(nextId).focus();
            }
        });
    }
    enterGoesTo('upc', 'epc');
    enterGoesTo('epc', 'producer');

    // "Read tag": the next tag the RFID reader sees fills the EPC field.
    const readTag = document.getElementById('readTag');
    const tagHint = document.getElementById('tagHint');
    let tagTimeout = null;
    const feed = createRfidFeed(function (epcs) {
        document.getElementById('epc').value = epcs[0];
        stopReading('Tag read: ' + epcs[0]);
        notify('RFID tag read.', 'success');
    }, 500);

    function stopReading(hint) {
        feed.stop();
        clearTimeout(tagTimeout);
        readTag.disabled = false;
        readTag.textContent = 'Read tag';
        tagHint.textContent = hint;
    }

    readTag.addEventListener('click', function () {
        feed.start();
        readTag.disabled = true;
        readTag.textContent = 'Waiting...';
        tagHint.textContent = 'Place the tag on the reader now (scripts/rfid.py must be running).';
        tagTimeout = setTimeout(function () {
            stopReading('No tag was read. Is scripts/rfid.py running? You can also type the EPC.');
        }, 20000);
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        hideMessage(message);
        clearFormErrors(form);
        submitBtn.disabled = true;

        try {
            const data = await postForm(editing ? 'products/update' : 'products', form);
            if (data.status === 'success') {
                notifyAfterRedirect(data.message);
                window.location.href = '/products/show?id=' + data.product.id;
                return;
            }
            showFormErrors(form, data.errors || {});
            showMessage(message, data.message, false);
            notify(data.message, 'error');
        } catch (error) {
            showMessage(message, 'Could not reach the server. Please check your connection and try again.', false);
        }
        submitBtn.disabled = false;
    });
</script>
</body>
</html>
