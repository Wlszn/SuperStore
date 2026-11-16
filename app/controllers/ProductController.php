<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ProductModel;
use App\Models\ReceptionModel;
use RuntimeException;

/**
 * Product management and inventory. Every page and action is admin only,
 * except the lookup API, which the self-checkout uses.
 *
 *   GET  /products                   inventory dashboard          (page)
 *   GET  /products/create            empty product form           (page)
 *   GET  /products/edit?id=5         product form, filled in      (page)
 *   GET  /products/show?id=5         details + reception history  (page)
 *   GET  /products/receive[?id=5]    receive-stock form           (page)
 *   POST /products                   create a product             (JSON)
 *   POST /products/update            edit a product               (JSON)
 *   POST /products/delete            delete a product             (JSON)
 *   POST /receptions                 record received stock        (JSON)
 *   GET  /api/products/lookup?code=  find a product by UPC or EPC (JSON)
 */
class ProductController
{
    private ProductModel $products;
    private ReceptionModel $receptions;

    public function __construct()
    {
        $this->products = new ProductModel();
        $this->receptions = new ReceptionModel();
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

    // GET /products/create: the form with no product
    public function create(): void
    {
        if (!$this->allowPage()) {
            return;
        }

        $this->render('products/form', ['product' => null]);
    }

    // GET /products/edit?id=5: the same form, filled in
    public function edit(): void
    {
        if (!$this->allowPage()) {
            return;
        }

        $product = $this->productFromQuery();
        if ($product !== null) {
            $this->render('products/form', ['product' => $product]);
        }
    }

    // GET /products/show?id=5: one product and every time it was received
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
        } catch (RuntimeException $e) {
            $this->pageError(500, 'Could not load the reception history. Please try again.');
            return;
        }

        $this->render('products/show', ['product' => $product, 'receptions' => $receptions]);
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

    /** POST /receptions: record a delivery (product_id, quantity, received_at). */
    public function receive(): void
    {
        if (!$this->allowAction()) {
            return;
        }

        [$data, $errors] = $this->receptions->validate($_POST);
        if ($errors !== []) {
            $this->invalid($errors);
            return;
        }

        try {
            if ($this->products->find($data['product_id']) === null) {
                $this->invalid(['product_id' => 'That product does not exist.']);
                return;
            }

            $reception = $this->receptions->create($data);
            $product = $this->products->find($data['product_id']); // fresh stock
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

    public function pointsCalculate(float $price): int
    {
        //for every dollar they get 10 points out of it
    
        return (int)$price * 10;
    }

    /** GET /api/products/lookup?code=...: the product behind a scanned UPC or EPC. */
    public function lookup(): void
    {
        $code = trim((string) ($_GET['code'] ?? ''));
        if ($code === '') {
            jsonResponse(422, ['status' => 'error', 'message' => 'Add ?code= with a UPC or an EPC.']);
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

    public function getProductByBarcode(string $barcode): ?array
    {
        try {
            return $this->products->findByCode($barcode);
        } catch (RuntimeException $e) {
            return null;
        }
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
