<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ItemModel;
use App\Models\ProductModel;
use App\Models\ReceptionModel;
use App\Models\ScanModel;
use InvalidArgumentException;
use RuntimeException;

/**
 * Product types, tagged items and inventory. Every page and action is admin only,
 * except the two lookup APIs, which the self-checkout uses.
 *
 *   GET  /products                     inventory dashboard                 (page)
 *   GET  /products/create              empty product form                  (page)
 *   GET  /products/edit?id=5           product form, filled in             (page)
 *   GET  /products/show?id=5           details, tags in stock, deliveries  (page)
 *   GET  /products/receive[?id=5]      receive tagged items                (page)
 *   POST /products                     create a product type               (JSON)
 *   POST /products/update              edit a product type                 (JSON)
 *   POST /products/delete              delete a product type               (JSON)
 *   POST /receptions                   record a delivery of tagged items   (JSON)
 *   GET  /api/products/stock           every product with current stock    (JSON)
 *   GET  /api/products/lookup?code=    product type by barcode (UPC)       (JSON)
 *   GET  /api/items/lookup?epcs=A,B    what each RFID tag is               (JSON)
 */
class ProductController
{
    private ProductModel $products;
    private ReceptionModel $receptions;
    private ItemModel $items;

    public function __construct()
    {
        $this->products = new ProductModel();
        $this->receptions = new ReceptionModel();
        $this->items = new ItemModel();
    }

    /** GET /products: every product with its stock. */
    public function index(): void
    {
        if (!$this->allowPage()) {
            return;
        }

        try {
            $products = $this->products->all();
        } catch (RuntimeException $e) {
            $this->pageError(500, 'Could not load the inventory. Please try again.');
            return;
        }

        $this->render('products/index', [
            'products' => $products,
            'lowStock' => ProductModel::LOW_STOCK,
        ]);
    }

    /** GET /api/products/stock: current inventory for the admin product table. */
    public function stock(): void
    {
        if (!isset($_SESSION['customer_id'])) {
            jsonResponse(401, ['status' => 'error', 'message' => 'Please sign in first.']);
            return;
        }

        if (empty($_SESSION['is_admin'])) {
            jsonResponse(403, ['status' => 'error', 'message' => 'Only store administrators can view inventory.']);
            return;
        }

        try {
            jsonResponse(200, ['status' => 'success', 'products' => $this->products->all()]);
        } catch (RuntimeException $e) {
            $this->serverError();
        }
    }

    /** GET /products/export: download every product and its current stock as CSV. */
    public function export(): void
    {
        if (!$this->allowPage()) {
            return;
        }

        try {
            $products = $this->products->all();
        } catch (RuntimeException $e) {
            $this->pageError(500, 'Could not load the inventory. Please try again.');
            return;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Product', 'Category', 'Barcode', 'Price', 'In stock', 'Status', 'Producer'], ',', '"', '');

        foreach ($products as $product) {
            $stock = (int) $product['in_stock'];
            fputcsv($out, [
                $this->csvCell($product['name']),
                $this->csvCell($product['category']),
                $this->csvCell($product['upc'] ?? ''),
                number_format((float) $product['price'], 2, '.', ''),
                $stock,
                $stock <= 0 ? 'Out of stock' : ($stock <= ProductModel::LOW_STOCK ? 'Low stock' : 'In stock'),
                $this->csvCell($product['producer']),
            ], ',', '"', '');
        }

        fclose($out);
        exit;
    }

    // GET /products/create: the form with no product
    public function create(): void
    {
        if (!$this->allowPage()) {
            return;
        }

        $this->renderForm(null);
    }

    // GET /products/edit?id=5: the same form, filled in
    public function edit(): void
    {
        if (!$this->allowPage()) {
            return;
        }

        $product = $this->productFromQuery();
        if ($product !== null) {
            $this->renderForm($product);
        }
    }

    // GET /products/show?id=5: one product, its tags in stock and every delivery
    public function show(): void
    {
        if (!$this->allowPage()) {
            return;
        }

        $product = $this->productFromQuery();
        if ($product === null) {
            return;
        }

        try {
            $receptions = $this->receptions->forProduct($product['id']);
            $items = $this->items->inStock($product['id']);
        } catch (RuntimeException $e) {
            $this->pageError(500, 'Could not load the reception history. Please try again.');
            return;
        }

        $this->render('products/show', ['product' => $product, 'receptions' => $receptions, 'items' => $items]);
    }

    // GET /products/receive[?id=5]: pick a product (pre-selected when id is given)
    public function receiveForm(): void
    {
        if (!$this->allowPage()) {
            return;
        }

        try {
            $products = $this->products->all();
        } catch (RuntimeException $e) {
            $this->pageError(500, 'Could not load the products. Please try again.');
            return;
        }

        $this->render('products/receive', [
            'products' => $products,
            'selectedId' => $this->id($_GET),
            'today' => date('Y-m-d'),
        ]);
    }

    public function latestReception(): void
    {
        if (empty($_SESSION['customer_id']) || empty($_SESSION['is_admin'])) {
            jsonResponse(403, ['status' => 'error']);
            return;
        }

        $id = $this->id($_GET);
        if ($id === null) {
            jsonResponse(422, ['status' => 'error']);
            return;
        }

        try {
            $reception = $this->receptions->forProduct($id, 1)[0] ?? null;
            jsonResponse(200, ['status' => 'success', 'reception' => $reception]);
        } catch (RuntimeException $e) {
            $this->serverError();
        }
    }

    // POST /products: register a new product
    public function store(): void
    {
        if (!$this->allowAction()) {
            return;
        }

        try {
            [$data, $errors] = $this->products->validate($_POST);
            if ($errors !== []) {
                $this->invalid($errors);
                return;
            }

            $product = $this->products->create($data);
        } catch (RuntimeException $e) {
            $this->serverError();
            return;
        }

        jsonResponse(201, [
            'status' => 'success',
            'message' => sprintf('Product "%s" added successfully.', $product['name']),
            'product' => $product,
        ]);
    }

    /** POST /products/update: change a product (id in the form). */
    public function update(): void
    {
        if (!$this->allowAction()) {
            return;
        }

        $id = $this->id($_POST);
        if ($id === null) {
            jsonResponse(404, ['status' => 'error', 'message' => 'That product does not exist.']);
            return;
        }

        try {
            [$data, $errors] = $this->products->validate($_POST, $id);
            if ($errors !== []) {
                $this->invalid($errors);
                return;
            }

            if (!$this->products->update($id, $data)) {
                jsonResponse(404, ['status' => 'error', 'message' => 'That product does not exist.']);
                return;
            }

            $product = $this->products->find($id);
        } catch (RuntimeException $e) {
            $this->serverError();
            return;
        }

        jsonResponse(200, [
            'status' => 'success',
            'message' => sprintf('Product "%s" updated.', $data['name']),
            'product' => $product,
        ]);
    }

    /** POST /products/delete: remove a product and its receptions (id in the form). */
    public function delete(): void
    {
        if (!$this->allowAction()) {
            return;
        }

        $id = $this->id($_POST);

        try {
            $product = $id === null ? null : $this->products->find($id);
            if ($product === null) {
                jsonResponse(404, ['status' => 'error', 'message' => 'That product does not exist.']);
                return;
            }

            $this->products->delete($id);
        } catch (RuntimeException $e) {
            $this->serverError();
            return;
        }

        jsonResponse(200, [
            'status' => 'success',
            'message' => sprintf('Product "%s" deleted.', $product['name']),
        ]);
    }

    /** POST /receptions: record a delivery (product_id, received_at, epcs[] = the tags on the reader). */
    public function receive(): void
    {
        if (!$this->allowAction()) {
            return;
        }

        [$data, $errors] = $this->receptions->validate($_POST);
        if (isset($errors['epcs'])) {
            // No field to highlight for the tags: the message itself says what to do.
            jsonResponse(422, ['status' => 'error', 'message' => $errors['epcs'], 'errors' => $errors]);
            return;
        }
        if ($errors !== []) {
            $this->invalid($errors);
            return;
        }

        try {
            if ($this->products->find($data['product_id']) === null) {
                $this->invalid(['product_id' => 'That product does not exist.']);
                return;
            }

            $reception = $this->receptions->receive($data);
            $product = $this->products->find($data['product_id']); // fresh stock
        } catch (InvalidArgumentException $e) {
            jsonResponse(422, ['status' => 'error', 'message' => $e->getMessage()]); // a tag already registered
            return;
        } catch (RuntimeException $e) {
            $this->serverError();
            return;
        }

        jsonResponse(201, [
            'status' => 'success',
            'message' => sprintf(
                'Received %d × %s. In stock: %d.',
                $reception['quantity'],
                $product['name'],
                $product['in_stock']
            ),
            'reception' => $reception,
            'product' => $product,
        ]);
    }

    /** GET /api/products/lookup?code=...: the product type behind a scanned barcode. */
    public function lookup(): void
    {
        $code = trim((string) ($_GET['code'] ?? ''));
        if ($code === '') {
            jsonResponse(422, ['status' => 'error', 'message' => 'Add ?code= with a UPC.']);
            return;
        }

        try {
            $product = $this->products->findByCode($code);
        } catch (RuntimeException $e) {
            $this->serverError();
            return;
        }

        if ($product === null) {
            jsonResponse(404, ['status' => 'error', 'message' => 'No product has this code.']);
            return;
        }

        jsonResponse(200, ['status' => 'success', 'product' => $product]);
    }

    /**
     * GET /api/items/lookup?epcs=E280...,E280...: every tag the reader saw at once,
     * each with its status (in_stock, sold or unknown) and product. Used by checkout and receiving.
     */
    public function itemLookup(): void
    {
        [$epcs, $errors] = (new ScanModel())->validate(explode(',', (string) ($_GET['epcs'] ?? '')));
        if ($errors !== []) {
            jsonResponse(422, ['status' => 'error', 'message' => 'Add ?epcs= with up to 100 EPCs separated by commas.']);
            return;
        }

        try {
            jsonResponse(200, ['status' => 'success', 'items' => $this->items->lookup($epcs)]);
        } catch (RuntimeException $e) {
            $this->serverError();
        }
    }

    /** Pages: send guests to the login page and refuse non-admins. */
    private function allowPage(): bool
    {
        if (!isset($_SESSION['customer_id'])) {
            header('Location: /login');
            return false;
        }

        if (empty($_SESSION['is_admin'])) {
            $this->pageError(403, 'Only store administrators can manage products.');
            return false;
        }

        return true;
    }

    /** Form actions: admin session plus the CSRF token from the page. */
    private function allowAction(): bool
    {
        if (!isset($_SESSION['customer_id'])) {
            jsonResponse(401, ['status' => 'error', 'message' => 'Please sign in first.']);
            return false;
        }

        if (empty($_SESSION['is_admin'])) {
            jsonResponse(403, ['status' => 'error', 'message' => 'Only store administrators can manage products.']);
            return false;
        }

        if (!hash_equals($this->csrfToken(), (string) ($_POST['csrf_token'] ?? ''))) {
            jsonResponse(403, ['status' => 'error', 'message' => 'Your session expired. Reload the page and try again.']);
            return false;
        }

        return true;
    }

    /** The product named by ?id=, or null after showing a "not found" page. */
    private function productFromQuery(): ?array
    {
        $id = $this->id($_GET);

        try {
            $product = $id === null ? null : $this->products->find($id);
        } catch (RuntimeException $e) {
            $this->pageError(500, 'Could not load the product. Please try again.');
            return null;
        }

        if ($product === null) {
            $this->pageError(404, 'That product does not exist.');
        }

        return $product;
    }

    /** The product form, with the category list for its dropdown. */
    private function renderForm(?array $product): void
    {
        try {
            $categories = $this->products->categories();
        } catch (RuntimeException $e) {
            $this->pageError(500, 'Could not load the categories. Please try again.');
            return;
        }

        $this->render('products/form', ['product' => $product, 'categories' => $categories]);
    }

    /** A positive integer from $_GET/$_POST, or null. */
    private function id(array $source, string $key = 'id'): ?int
    {
        $id = filter_var($source[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
    }


    /**
     * Show a view with $data as variables (plus $csrfToken).
     * Until the view exists, answers with the data as JSON so the backend can be tested on its own.
     */
    private function render(string $view, array $data): void
    {
        $data['csrfToken'] = $this->csrfToken();
        $file = APP_ROOT . '/app/views/' . $view . '.php';

        if (!is_file($file)) {
            jsonResponse(501, [
                'status' => 'error',
                'message' => "The view app/views/$view.php is not built yet.",
                'data' => $data,
            ]);
            return;
        }

        extract($data);
        require $file;
    }

    /** Stops Excel from treating names or categories beginning with a formula character as formulas. */
    private function csvCell(mixed $value): string
    {
        $value = (string) $value;
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    private function pageError(int $status, string $message): void
    {
        http_response_code($status);
        echo htmlspecialchars($message) . ' <a href="/dashboard">Back to the dashboard</a>';
    }

    private function invalid(array $errors): void
    {
        jsonResponse(422, [
            'status' => 'error',
            'message' => 'Please fix the highlighted fields and try again.',
            'errors' => $errors,
        ]);
    }

    private function serverError(): void
    {
        jsonResponse(500, ['status' => 'error', 'message' => 'Something went wrong on our side. Please try again.']);
    }

    /** Same token as ThresholdController: one per session, sent back by every form. */
    private function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}
