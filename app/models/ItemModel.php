<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\SupaClient;

/**
 * One physical item = one RFID tag (EPC). Items are created when stock is received
 * (ReceptionModel::receive) and marked sold by the checkout (complete_checkout in config/checkout.sql).
 */
class ItemModel
{
    private const TABLE = 'items';

    /**
     * What every tag in a batch is, in ONE request (the reader sees many tags at once).
     * Returns one entry per EPC, in the same order:
     *   ['epc' => ..., 'status' => 'in_stock' | 'sold' | 'unknown', 'product' => array|null]
     * $epcs must be uppercase hex (ScanModel::validate()).
     */
    public function lookup(array $epcs): array
    {
        $rows = SupaClient::rows('GET', self::TABLE, [
            'select' => 'epc,purchase_id,products(id,name,category,price,producer,image_url)',
            'epc' => 'in.(' . implode(',', $epcs) . ')',
        ]);
        $byEpc = array_column($rows, null, 'epc');

        return array_map(function (string $epc) use ($byEpc): array {
            $item = $byEpc[$epc] ?? null;
            if ($item === null) {
                return ['epc' => $epc, 'status' => 'unknown', 'product' => null];
            }
            $product = $item['products'];
            $product['id'] = (int) $product['id'];
            $product['price'] = (float) $product['price'];

            return [
                'epc' => $epc,
                'status' => $item['purchase_id'] === null ? 'in_stock' : 'sold',
                'product' => $product,
            ];
        }, $epcs);
    }

    /** The tags of one product type that are still in stock, for the product page. */
    public function inStock(int $productId): array
    {
        return SupaClient::rows('GET', self::TABLE, [
            'select' => 'epc,reception_id',
            'product_id' => 'eq.' . $productId,
            'purchase_id' => 'is.null',
            'order' => 'id.desc',
        ]);
    }
}
