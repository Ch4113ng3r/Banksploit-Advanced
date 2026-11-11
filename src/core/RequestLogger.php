<?php

declare(strict_types=1);

/**
 * RequestLogger — middleware that records the full request/response cycle to
 * the request_log table for the inspector UI.
 *
 * Usage (in index.php):
 *   $logger = new RequestLogger($pdo);
 *   $logger->begin();
 *   ... handle request, capture output ...
 *   $logger->finish($responseBody, $responseCode);
 *
 * Captures method, URL, headers, request body, response body, response code
 * and duration, and annotates user-controlled fields so the inspector can
 * highlight attacker-influenced input.
 */
final class RequestLogger
{
    private PDO $pdo;
    private float $startedAt = 0.0;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Mark the start of the request (for duration measurement).
     */
    public function begin(): void
    {
        $this->startedAt = microtime(true);
    }

    /**
     * Persist the completed request/response. Never throws into the request
     * path — logging failures are swallowed.
     */
    public function finish(string $responseBody, int $responseCode): void
    {
        $durationMs = $this->startedAt > 0.0
            ? (int) round((microtime(true) - $this->startedAt) * 1000)
            : 0;

        $method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $url     = $_SERVER['REQUEST_URI'] ?? '/';
        $headers = $this->collectHeaders();
        $body    = $this->collectRequestBody();

        $annotated = [
            'user_controlled' => $this->userControlledFields(),
            'query'           => $_GET,
            'body'            => $body,
        ];

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO request_log
                    (method, url, headers, request_body, response_body, response_code, duration_ms)
                 VALUES (:m, :u, :h, :rb, :resp, :code, :dur)'
            );
            $stmt->execute([
                ':m'    => $method,
                ':u'    => $url,
                ':h'    => $this->json($headers),
                ':rb'   => $this->json($annotated),
                ':resp' => $this->truncate($responseBody, 65000),
                ':code' => $responseCode,
                ':dur'  => $durationMs,
            ]);
        } catch (Throwable $e) {
            // Logging must never break the app; ignore failures.
        }
    }

    /**
     * Best-effort request header collection (works under Apache/mod_php).
     *
     * @return array<string, string>
     */
    private function collectHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $h = getallheaders();
            if (is_array($h)) {
                return $h;
            }
        }
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with((string) $k, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
                $headers[$name] = (string) $v;
            }
        }
        return $headers;
    }

    /**
     * Parse the raw request body (JSON or form-encoded), falling back to $_POST.
     *
     * @return array<string, mixed>
     */
    private function collectRequestBody(): array
    {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return $json;
            }
            parse_str($raw, $parsed);
            if ($parsed !== []) {
                return $parsed;
            }
        }
        return $_POST;
    }

    /**
     * Names of fields the client controlled this request — highlighted by the
     * inspector as attacker-influenced input.
     *
     * @return list<string>
     */
    private function userControlledFields(): array
    {
        $fields = array_merge(
            array_keys($_GET),
            array_keys($_POST),
            array_keys($this->collectRequestBody())
        );
        return array_values(array_unique(array_map('strval', $fields)));
    }

    private function json(mixed $value): string
    {
        $s = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return $s === false ? '{}' : $this->truncate($s, 65000);
    }

    private function truncate(string $s, int $max): string
    {
        return strlen($s) > $max ? substr($s, 0, $max) . '...[truncated]' : $s;
    }
}
