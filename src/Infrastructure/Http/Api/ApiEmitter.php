<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

use App\Infrastructure\Http\Response;

/** Sends an ApiResponse to the client (headers + body) and ends the request. */
final class ApiEmitter
{
    public static function emit(ApiResponse $response): never
    {
        foreach ($response->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        header('Cache-Control: no-store');

        if ($response->filePath !== null) {
            http_response_code($response->status);
            Response::file($response->filePath, (string) $response->fileName, (string) $response->contentType);
            exit;
        }

        if ($response->content !== null) {
            $headers = [];
            if ($response->fileName !== null) {
                $headers['Content-Disposition'] = 'inline; filename="' . rawurlencode($response->fileName) . '"; filename*=UTF-8\'\'' . rawurlencode($response->fileName);
            }
            $headers['Content-Length'] = (string) strlen($response->content);
            Response::raw($response->content, $response->status, (string) $response->contentType, $headers);
        }

        http_response_code($response->status);
        if ($response->json !== null) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($response->json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        }
        exit;
    }
}
