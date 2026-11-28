<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CustomerDirectoryModel;
use App\Models\CustomerModel;
use App\Models\PasswordResetModel;
use App\Services\Mailer;
use App\Services\SupaClient;
use RuntimeException;

/**
 * Admin Customers page: everyone registered in SuperStore.
 *
 *   GET  /customers                   the page (widgets + table)
 *   GET  /customers/export            the same table as a CSV file
 *   GET  /customers/edit?id=3         edit one account
 *   POST /customers/update            save it (JSON, for the form)
 *   POST /customers/reset-password    email the customer a password reset link (JSON)
 */
class CustomerController
{
    public function index(): void
    {
        if (!$this->adminPageAllowed()) {
            return;
        }
        $customers = [];
        $summary = null;
        $error = null;
        try {
            $directory = new CustomerDirectoryModel();
            $customers = $directory->all();
            $summary = $directory->summary($customers);
        } catch (RuntimeException $e) {
            $error = 'Could not load the customers. Please try again.';
        }

        require APP_ROOT . '/app/views/customers/index.php';
    }

    public function export(): void
    {
        if (!$this->adminPageAllowed()) {
            return;
        }
        try {
            $customers = (new CustomerDirectoryModel())->all();
        } catch (RuntimeException $e) {
            http_response_code(503);
            echo 'Could not load the customers. Please try again.';
            return;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="customers-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");   // so Excel shows accents
        fputcsv($out, ['Name', 'Email', 'Phone', 'Address', 'Membership number', 'Points', 'Role', 'Joined', 'Purchases', 'Total spent', 'Last purchase'], ',', '"', '');
        foreach ($customers as $customer) {
            fputcsv($out, [
                $this->csvCell($customer['name']),
                $this->csvCell($customer['email']),
                $this->csvCell($customer['phone']),
                $this->csvCell($customer['address']),
                $this->csvCell($customer['membership_number']),
                $customer['points'],
                $customer['is_admin'] ? 'Administrator' : 'Customer',
                $customer['joined']?->format('Y-m-d') ?? '',
                $customer['purchases'],
                number_format($customer['spent'], 2, '.', ''),
                $customer['last_purchase']?->format('Y-m-d H:i') ?? '',
            ], ',', '"', '');
        }
        fclose($out);
    }

    public function edit(): void
    {
        if (!$this->adminPageAllowed()) {
            return;
        }
        $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
        try {
            $customer = $id ? $this->find((int) $id) : null;
        } catch (RuntimeException $e) {
            http_response_code(503);
            echo 'Could not load this customer. Please try again.';
            return;
        }
        if ($customer === null) {
            http_response_code(404);
            echo 'Customer not found. <a href="/customers">Back to the customers</a>';
            return;
        }
        $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
        $self = (int) $customer['id'] === (int) $_SESSION['customer_id'];
        $mailReady = Mailer::configured();
        require APP_ROOT . '/app/views/customers/edit.php';
    }

    public function update(): void
    {
        if (!$this->adminActionAllowed()) {
            return;
        }
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        $text = static fn (string $key): string => trim(is_string($_POST[$key] ?? null) ? $_POST[$key] : '');
        $fields = ['name' => $text('name'), 'email' => $text('email'), 'phone' => $text('phone'), 'address' => $text('address')];
        $membership = $text('membership_number');
        $points = filter_var($_POST['points'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);

        $errors = CustomerModel::validateData($fields['name'], $fields['email'], $fields['phone'], $fields['address']);
        if ($membership === '' || mb_strlen($membership) > 64 || preg_match('/[\x00-\x1F\x7F]/', $membership)) {
            $errors['membership_number'] = 'Use a membership code with 1 to 64 printable characters.';
        }
        if ($points === false) {
            $errors['points'] = 'Points must be a whole number, 0 or more.';
        }
        if (!$id) {
            jsonResponse(404, ['status' => 'error', 'message' => 'Customer not found.']);
            return;
        }
        if ($errors !== []) {
            jsonResponse(422, ['status' => 'error', 'message' => 'Please fix the highlighted fields and try again.', 'errors' => $errors]);
            return;
        }
        // An admin cannot remove their own access (nobody would be left to give it back).
        $isAdmin = (int) $id === (int) $_SESSION['customer_id'] ? true : isset($_POST['is_admin']);
        try {
            if ($this->find((int) $id) === null) {
                jsonResponse(404, ['status' => 'error', 'message' => 'Customer not found.']);
                return;
            }
            $owner = CustomerModel::findByEmail($fields['email']);
            if ($owner !== null && $owner->getId() !== (int) $id) {
                jsonResponse(409, ['status' => 'error', 'message' => 'Please fix the highlighted fields and try again.',
                    'errors' => ['email' => 'Another account already uses this email address.']]);
                return;
            }
            $card = SupaClient::rows('GET', 'customers', ['select' => 'id', 'membership_number' => 'eq.' . $membership, 'id' => 'neq.' . $id, 'limit' => 1]);
            if ($card !== []) {
                jsonResponse(409, ['status' => 'error', 'message' => 'Please fix the highlighted fields and try again.',
                    'errors' => ['membership_number' => 'Another account already uses this membership code.']]);
                return;
            }
            SupaClient::rows('PATCH', 'customers', ['id' => 'eq.' . $id], $fields + [
                'membership_number' => $membership,
                'points' => $points,
                'is_admin' => $isAdmin,
            ]);
        } catch (RuntimeException $e) {
            jsonResponse(503, ['status' => 'error', 'message' => 'Could not save the customer. Please try again.']);
            return;
        }
        if ((int) $id === (int) $_SESSION['customer_id']) {
            $_SESSION['customer_name'] = $fields['name'];
        }
        jsonResponse(200, ['status' => 'success', 'message' => 'Customer "' . $fields['name'] . '" updated.']);
    }

    public function sendReset(): void
    {
        if (!$this->adminActionAllowed()) {
            return;
        }
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        try {
            $customer = $id ? $this->find((int) $id) : null;
            if ($customer === null) {
                jsonResponse(404, ['status' => 'error', 'message' => 'Customer not found.']);
                return;
            }
            $sent = (new PasswordResetModel())->emailLink((int) $customer['id'], $customer['name'], $customer['email'], true);
        } catch (RuntimeException $e) {
            jsonResponse(503, ['status' => 'error', 'message' => 'Could not create the reset link. Please try again.']);
            return;
        }
        if ($sent === null) {
            jsonResponse(429, ['status' => 'error', 'message' => 'A reset email was sent less than a minute ago. Wait a moment before sending another.']);
        } elseif ($sent === false) {
            jsonResponse(502, ['status' => 'error', 'message' => 'The email could not be sent. Check the email settings in .env (BREVO_API_KEY, MAIL_FROM_EMAIL).']);
        } else {
            jsonResponse(200, ['status' => 'success', 'message' => 'Password reset email sent to ' . $customer['email'] . '.']);
        }
    }

    /** One customer without the password hash, or null. */
    private function find(int $id): ?array
    {
        return SupaClient::rows('GET', 'customers', [
            'select' => 'id,name,email,phone,address,membership_number,points,is_admin,created_at',
            'id' => 'eq.' . $id,
        ])[0] ?? null;
    }

    /** JSON actions: signed in as an admin, with the page's CSRF token. Sends the error itself. */
    private function adminActionAllowed(): bool
    {
        if (!isset($_SESSION['customer_id'])) {
            jsonResponse(401, ['status' => 'error', 'message' => 'Please sign in first.']);
            return false;
        }
        if (empty($_SESSION['is_admin'])) {
            jsonResponse(403, ['status' => 'error', 'message' => 'Only store administrators can change customers.']);
            return false;
        }
        if (empty($_SESSION['csrf_token']) || !is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            jsonResponse(403, ['status' => 'error', 'message' => 'Your session expired. Reload the page and try again.']);
            return false;
        }
        return true;
    }

    /** Text a spreadsheet could run as a formula (=, +, -, @) gets a leading '. */
    private function csvCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    /** Guests go to the login page; signed-in customers who are not admins get a 403. */
    private function adminPageAllowed(): bool
    {
        if (!isset($_SESSION['customer_id'])) {
            header('Location: /login');
            return false;
        }
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            echo 'Only store administrators can see the customers. <a href="/">Back</a>';
            return false;
        }
        return true;
    }
}
