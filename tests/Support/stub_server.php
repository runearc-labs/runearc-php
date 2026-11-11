<?php

declare(strict_types=1);

// A tiny HTTP server for failures the real API cannot be made to produce on demand,
// and for receiving real webhook deliveries. Usage:
//   php stub_server.php <scenario.json> <port-file> <log-file>
// The scenario is a list of steps used in order; the last one repeats:
//   {"action": "respond", "status": 503, "headers": {...}, "body": "..."}
//   {"action": "drop"}   closes the connection without answering
//   {"action": "hang"}   never answers
//   {"action": "proxy", "upstream": "http://...", "lose": true}   forwards to the real
//                        API; with "lose" the answer is replaced by a 503
// Every request is appended to the log file as one JSON line.

/** A scalar field of a decoded JSON object as text; anything else is the default. */
function text(mixed $value, string $default = ''): string
{
    return is_string($value) || is_int($value) || is_float($value) ? (string) $value : $default;
}

$scenarioFile = $argv[1] ?? '';
$portFile = $argv[2] ?? '';
$logFile = $argv[3] ?? '';
$decoded = json_decode((string) file_get_contents($scenarioFile), true, 512, JSON_THROW_ON_ERROR);
if (!is_array($decoded) || $decoded === []) {
    fwrite(STDERR, "the scenario must be a non-empty list of steps\n");
    exit(1);
}
$steps = array_values($decoded);
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($server === false) {
    fwrite(STDERR, "listen failed: $error\n");
    exit(1);
}
$name = (string) stream_socket_get_name($server, false);
file_put_contents($portFile, substr($name, (int) strrpos($name, ':') + 1));

$count = 0;
while (!feof($server)) {
    $conn = @stream_socket_accept($server, 3600);
    if ($conn === false) {
        continue;
    }
    $head = '';
    while (!str_contains($head, "\r\n\r\n")) {
        $chunk = fread($conn, 8192);
        if ($chunk === false || $chunk === '') {
            break;
        }
        $head .= $chunk;
    }
    [$headText, $body] = array_pad(explode("\r\n\r\n", $head, 2), 2, '');
    $lines = explode("\r\n", $headText);
    [$method, $target] = explode(' ', array_shift($lines));
    $headers = [];
    foreach ($lines as $line) {
        [$k, $v] = array_pad(explode(':', $line, 2), 2, '');
        $headers[strtolower(trim($k))] = trim($v);
    }
    $length = (int) ($headers['content-length'] ?? 0);
    while (strlen($body) < $length) {
        $chunk = fread($conn, max(1, $length - strlen($body)));
        if ($chunk === false || $chunk === '') {
            break;
        }
        $body .= $chunk;
    }
    file_put_contents(
        $logFile,
        json_encode(['method' => $method, 'target' => $target, 'headers' => $headers, 'body' => $body, 'at' => microtime(true)]) . "\n",
        FILE_APPEND | LOCK_EX,
    );

    $current = $steps[min($count, count($steps) - 1)];
    $step = is_array($current) ? $current : [];
    $count++;
    $status = 200;
    $out = [];
    $payload = '';
    $action = text($step['action'] ?? null);
    switch ($action) {
        case 'drop':
            fclose($conn);
            continue 2;
        case 'hang':
            sleep(3600);
            continue 2;
        case 'proxy':
            $curl = curl_init(text($step['upstream'] ?? null) . $target);
            $send = [];
            foreach (['authorization', 'content-type', 'idempotency-key'] as $h) {
                if (isset($headers[$h])) {
                    $send[] = "$h: {$headers[$h]}";
                }
            }
            curl_setopt_array($curl, [
                CURLOPT_CUSTOMREQUEST => $method === '' ? 'GET' : $method,
                CURLOPT_HTTPHEADER => $send,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
            ]);
            $payload = (string) curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if (!empty($step['lose'])) {
                $status = 503;
                $payload = '{"error":{"code":"unavailable","message":"try again","fix":null}}';
            }
            $out = ['content-type' => 'application/json'];
            break;
        default:
            $status = (int) text($step['status'] ?? null, '200');
            $rawHeaders = $step['headers'] ?? [];
            $out = [];
            if (is_array($rawHeaders)) {
                foreach ($rawHeaders as $name => $value) {
                    $out[(string) $name] = text($value);
                }
            }
            $payload = text($step['body'] ?? null);
    }
    $text = "HTTP/1.1 $status X\r\nConnection: close\r\nContent-Length: " . strlen($payload) . "\r\n";
    foreach ($out as $k => $v) {
        $text .= $k . ': ' . $v . "\r\n";
    }
    fwrite($conn, $text . "\r\n" . $payload);
    fclose($conn);
}
