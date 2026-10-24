<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Whatsapp;

use App\Infrastructure\Integration\HttpJsonRequest;
use RuntimeException;

/**
 * Legt Produkte im Meta-Commerce-Katalog an, der mit WhatsApp Business
 * verknüpft ist (WhatsApp selbst hat kein eigenes Produkt-API — Katalog-
 * Produkte werden über die Meta Graph API im zugehörigen Commerce-Manager-
 * Katalog verwaltet und stehen darüber auch im WhatsApp-Chat zur Verfügung).
 *
 * Referenz: https://developers.facebook.com/docs/commerce-platform/catalog
 */
final class WhatsappCatalogApiClient
{
    private const GRAPH_VERSION = 'v21.0';
    private const GRAPH_BASE = 'https://graph.facebook.com/' . self::GRAPH_VERSION;

    /**
     * @return array{id: string}
     */
    public function upsertProduct(
        string $accessToken,
        string $catalogId,
        string $retailerId,
        string $name,
        ?string $description,
        int $priceMinorUnits,
        string $currency,
        ?string $imageUrl,
        string $productUrl,
        bool $available
    ): array {
        $existingId = $this->findProductIdByRetailerId($accessToken, $catalogId, $retailerId);

        $body = [
            'retailer_id' => $retailerId,
            'name' => $name,
            'description' => $description ?? '',
            'price' => $priceMinorUnits,
            'currency' => $currency,
            'url' => $productUrl,
            'availability' => $available ? 'in stock' : 'out of stock',
            'condition' => 'new',
        ];
        if ($imageUrl !== null) {
            $body['image_url'] = $imageUrl;
        }

        if ($existingId !== null) {
            $response = HttpJsonRequest::send(
                'POST',
                self::GRAPH_BASE . '/' . rawurlencode($existingId),
                ['Authorization' => 'Bearer ' . $accessToken, 'Content-Type' => 'application/json'],
                $body
            );

            if ($response['status'] >= 400) {
                throw new RuntimeException($this->describeError('Produkt konnte im WhatsApp-Katalog nicht aktualisiert werden', $response));
            }

            return ['id' => $existingId];
        }

        $response = HttpJsonRequest::send(
            'POST',
            self::GRAPH_BASE . '/' . rawurlencode($catalogId) . '/products',
            ['Authorization' => 'Bearer ' . $accessToken, 'Content-Type' => 'application/json'],
            $body
        );

        if ($response['status'] >= 400 || $response['json'] === null || !isset($response['json']['id'])) {
            throw new RuntimeException($this->describeError('Produkt konnte nicht in den WhatsApp-Katalog übermittelt werden', $response));
        }

        return ['id' => (string) $response['json']['id']];
    }

    public function deleteProduct(string $accessToken, string $productId): void
    {
        $response = HttpJsonRequest::send(
            'DELETE',
            self::GRAPH_BASE . '/' . rawurlencode($productId),
            ['Authorization' => 'Bearer ' . $accessToken]
        );

        if ($response['status'] >= 400 && $response['status'] !== 404) {
            throw new RuntimeException($this->describeError('Produkt konnte nicht aus dem WhatsApp-Katalog entfernt werden', $response));
        }
    }

    /**
     * Meta kennt kein "insert or update by retailer_id" in einem Aufruf —
     * daher wird zuerst nachgeschaut, ob unter der retailer_id (bei uns:
     * die Artikel-ID) bereits ein Produkt existiert.
     */
    private function findProductIdByRetailerId(string $accessToken, string $catalogId, string $retailerId): ?string
    {
        $response = HttpJsonRequest::send(
            'GET',
            self::GRAPH_BASE . '/' . rawurlencode($catalogId) . '/products?' . http_build_query([
                'filter' => json_encode(['retailer_id' => ['eq' => $retailerId]]),
                'fields' => 'id,retailer_id',
                'access_token' => $accessToken,
            ]),
            []
        );

        $data = $response['json']['data'] ?? [];
        if (!is_array($data) || count($data) === 0) {
            return null;
        }

        $first = $data[0];

        return is_array($first) && isset($first['id']) ? (string) $first['id'] : null;
    }

    /** @param array{status: int, json: array<string, mixed>|null, raw: string} $response */
    private function describeError(string $prefix, array $response): string
    {
        $detail = $response['json']['error']['message'] ?? $response['raw'];
        if (is_array($detail)) {
            $detail = json_encode($detail);
        }

        return $prefix . ' (HTTP ' . $response['status'] . '): ' . $detail;
    }
}
