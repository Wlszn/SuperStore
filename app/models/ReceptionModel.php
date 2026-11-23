<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\SupaClient;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use function config\supa_request;

/** A delivery: the tagged items of one product type that came in on one date. */
class ReceptionModel
{
    private const TABLE = 'receptions';
    private const COLUMNS = 'id,product_id,quantity,received_at';

    /** Most tags in one delivery (the same limit as one RFID read). */
    private const MAX_TAGS = 100;

    /** Every reception of one product, newest first. */
    public function forProduct(int $productId, ?int $limit = null): array
    {
        $rows = SupaClient::rows('GET', self::TABLE, [
            'select' => self::COLUMNS,
            'product_id' => 'eq.' . $productId,
            'order' => 'received_at.desc,id.desc',
        ] + ($limit === null ? [] : ['limit' => $limit]));

        return array_map([$this, 'normalize'], $rows);
    }

    /**
     * Record a delivery: every tag becomes one item of the product, in one transaction
     * (receive_items() in config/checkout.sql). $data must come from validate(). Returns the reception.
     *
     * @throws InvalidArgumentException if a tag is already registered
     * @throws RuntimeException if the database is unavailable
     */
    public function receive(array $data): array
    {
        [$status, $body] = supa_request('POST', 'rpc/receive_items', null, [
            'p_product' => $data['product_id'],
            'p_received' => $data['received_at'],
            'p_epcs' => $data['epcs'],
        ]);
        if (($body['code'] ?? '') === 'PT422') {
            throw new InvalidArgumentException($body['message']);
        }
        if ($status < 200 || $status >= 300 || !isset($body['id'])) {
            throw new RuntimeException('The delivery was not saved.');
        }

        return $this->normalize($body);
    }

    public function validate(array $input): array
    {
        $errors = [];

        $productId = filter_var($input['product_id'] ?? null, FILTER_VALIDATE_INT);
        if ($productId === false || $productId < 1) {
            $errors['product_id'] = 'Please choose a product.';
        }

        // The tags the reader saw (epcs[] in the form): uppercase hex, no duplicates.
        $epcs = [];
        foreach (is_array($input['epcs'] ?? null) ? $input['epcs'] : [] as $epc) {
            $epc = is_string($epc) ? strtoupper(str_replace(' ', '', trim($epc))) : '';
            if (!preg_match('/^[0-9A-F]{1,24}$/', $epc)) {
                $errors['epcs'] = 'Every tag must be up to 24 hexadecimal characters (0-9, A-F).';
                break;
            }
            $epcs[$epc] = true;
        }
        $epcs = array_keys($epcs);
        if (!isset($errors['epcs']) && $epcs === []) {
            $errors['epcs'] = 'Place the new items on the RFID reader first.';
        } elseif (count($epcs) > self::MAX_TAGS) {
            $errors['epcs'] = sprintf('Receive at most %d tags at a time.', self::MAX_TAGS);
        }

        $today = new DateTimeImmutable('today');
        $rawDate = trim((string) ($input['received_at'] ?? ''));
        $date = $rawDate === '' ? $today : DateTimeImmutable::createFromFormat('!Y-m-d', $rawDate);

        if ($date === false || $date->format('Y-m-d') !== ($rawDate === '' ? $today->format('Y-m-d') : $rawDate)) {
            $errors['received_at'] = 'Please enter a valid date.';
        } elseif ($date > $today) {
            $errors['received_at'] = 'The date received cannot be in the future.';
        }

        $data = [
            'product_id' => (int) $productId,
            'received_at' => $date ? $date->format('Y-m-d') : null,
            'epcs' => $epcs,
        ];

        return [$data, $errors];
    }

    private function normalize(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['product_id'] = (int) $row['product_id'];
        $row['quantity'] = (int) $row['quantity'];

        return $row;
    }
}
