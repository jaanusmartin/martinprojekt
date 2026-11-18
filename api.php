<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
app_start_session();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function api_send(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_input(): array
{
    $raw = file_get_contents('php://input');
    $input = json_decode($raw === false ? '' : $raw, true);
    if (!is_array($input)) {
        throw new InvalidArgumentException('Päringu andmed ei ole korrektsed.');
    }
    return $input;
}

function api_text(mixed $value, int $maximum, string $label): string
{
    if (!is_string($value)) {
        throw new InvalidArgumentException($label . ' puudub või ei ole tekst.');
    }
    $text = trim($value);
    $characterCount = preg_match_all('/./us', $text);
    if ($text === '' || $characterCount === false || $characterCount > $maximum) {
        throw new InvalidArgumentException($label . ' peab olema 1–' . $maximum . ' tähemärki.');
    }
    return $text;
}

function api_check_csrf(array $input): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrfToken'] ?? '');
    if (!is_string($token) || !hash_equals(app_csrf_token(), $token)) {
        throw new DomainException('Päringu turvakontroll ebaõnnestus. Värskenda lehte ja proovi uuesti.', 403);
    }
}

function api_check_admin(): void
{
    if (!app_is_admin()) {
        throw new DomainException('Selle toimingu jaoks tuleb sisse logida.', 401);
    }
}

function api_ticket_code(mixed $code): string
{
    if (!is_string($code) || !preg_match('/^MK-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/D', $code)) {
        throw new InvalidArgumentException('Sisesta kehtiv jälgimiskood kujul MK-ABCD-EFGH.');
    }
    return $code;
}

function api_find_ticket(array $tickets, string $code): ?int
{
    foreach ($tickets as $index => $ticket) {
        if (isset($ticket['code']) && hash_equals((string) $ticket['code'], $code)) {
            return $index;
        }
    }
    return null;
}

function api_new_ticket_code(array $tickets): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $parts = ['', ''];
        for ($part = 0; $part < 2; $part++) {
            for ($character = 0; $character < 4; $character++) {
                $parts[$part] .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        }
        $code = 'MK-' . $parts[0] . '-' . $parts[1];
    } while (api_find_ticket($tickets, $code) !== null);
    return $code;
}

function api_message(string $sender, string $text): array
{
    return [
        'sender' => $sender,
        'text' => $text,
        'createdAt' => date(DATE_ATOM),
    ];
}

try {
    app_data_directory_ready();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_send(['error' => 'Kasuta POST-päringut.'], 405);
    }

    $input = api_input();
    api_check_csrf($input);
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'checkin':
            app_rate_limit('checkin', 20, 3600);
            $score = filter_var($input['score'] ?? null, FILTER_VALIDATE_INT);
            $categories = ['Kontrolltööd/õppimine', 'Suhted/üksindus', 'Väsimus/unepuudus', 'Ärevus/pinge', 'Muu'];
            $category = $input['category'] ?? null;
            if ($score === false || $score < 1 || $score > 5 || !is_string($category) || !in_array($category, $categories, true)) {
                throw new InvalidArgumentException('Vali meeleolu skoor ja stressiallika kategooria.');
            }
            app_update_json(CHECKINS_FILE, static function (array &$checkins) use ($score, $category): void {
                $checkins[] = ['date' => date('Y-m-d'), 'score' => $score, 'category' => $category];
            });
            api_send(['ok' => true, 'message' => 'Anonüümne koondkirje on saadetud.']);

        case 'create_ticket':
            app_rate_limit('create_ticket', 3, 86400);
            $text = api_text($input['message'] ?? null, 2000, 'Sõnum');
            $code = app_update_json(TICKETS_FILE, static function (array &$tickets) use ($text): string {
                $code = api_new_ticket_code($tickets);
                $tickets[] = [
                    'code' => $code,
                    'status' => 'new',
                    'createdAt' => date(DATE_ATOM),
                    'updatedAt' => date(DATE_ATOM),
                    'messages' => [api_message('student', $text)],
                ];
                return $code;
            });
            api_send(['ok' => true, 'code' => $code]);

        case 'get_ticket':
            app_rate_limit('get_ticket', 60, 3600);
            $code = api_ticket_code($input['code'] ?? null);
            $tickets = app_read_json(TICKETS_FILE);
            $index = api_find_ticket($tickets, $code);
            if ($index === null) {
                api_send(['error' => 'Selle koodiga pöördumist ei leitud. Kontrolli koodi ja proovi uuesti.'], 404);
            }
            $ticket = $tickets[$index];
            api_send([
                'ok' => true,
                'ticket' => [
                    'code' => $ticket['code'],
                    'status' => $ticket['status'],
                    'messages' => $ticket['messages'],
                ],
            ]);

        case 'reply_ticket':
            app_rate_limit('reply_ticket', 10, 3600);
            $code = api_ticket_code($input['code'] ?? null);
            $text = api_text($input['message'] ?? null, 2000, 'Sõnum');
            $found = app_update_json(TICKETS_FILE, static function (array &$tickets) use ($code, $text): bool {
                $index = api_find_ticket($tickets, $code);
                if ($index === null || ($tickets[$index]['status'] ?? '') === 'closed') {
                    return false;
                }
                $tickets[$index]['messages'][] = api_message('student', $text);
                $tickets[$index]['updatedAt'] = date(DATE_ATOM);
                return true;
            });
            if (!$found) {
                api_send(['error' => 'Pöördumist ei leitud või see on suletud.'], 404);
            }
            api_send(['ok' => true, 'message' => 'Sõnum on saadetud.']);

        case 'admin_data':
            api_check_admin();
            $checkins = app_read_json(CHECKINS_FILE);
            $tickets = app_read_json(TICKETS_FILE);
            $cutoff = date('Y-m-d', strtotime('-29 days'));
            $daily = [];
            $categoryCounts = [];
            foreach ($checkins as $entry) {
                if (!isset($entry['date'], $entry['score'], $entry['category']) || $entry['date'] < $cutoff || $entry['date'] > date('Y-m-d')) {
                    continue;
                }
                $date = (string) $entry['date'];
                $daily[$date] ??= ['sum' => 0, 'count' => 0];
                $daily[$date]['sum'] += (int) $entry['score'];
                $daily[$date]['count']++;
                $category = (string) $entry['category'];
                $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
            }
            $averages = [];
            foreach ($daily as $date => $values) {
                $averages[] = ['date' => $date, 'average' => round($values['sum'] / $values['count'], 2), 'count' => $values['count']];
            }
            usort($averages, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));
            usort($tickets, static fn (array $a, array $b): int => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));
            api_send([
                'ok' => true,
                'averages' => $averages,
                'categories' => $categoryCounts,
                'tickets' => $tickets,
            ]);

        case 'admin_reply':
            api_check_admin();
            $code = api_ticket_code($input['code'] ?? null);
            $text = api_text($input['message'] ?? null, 2000, 'Vastus');
            $found = app_update_json(TICKETS_FILE, static function (array &$tickets) use ($code, $text): bool {
                $index = api_find_ticket($tickets, $code);
                if ($index === null) {
                    return false;
                }
                $tickets[$index]['messages'][] = api_message('admin', $text);
                $tickets[$index]['updatedAt'] = date(DATE_ATOM);
                if (($tickets[$index]['status'] ?? '') === 'new') {
                    $tickets[$index]['status'] = 'answered';
                }
                return true;
            });
            if (!$found) {
                api_send(['error' => 'Pöördumist ei leitud.'], 404);
            }
            api_send(['ok' => true]);

        case 'admin_status':
            api_check_admin();
            $code = api_ticket_code($input['code'] ?? null);
            $status = $input['status'] ?? null;
            if (!is_string($status) || !in_array($status, ['new', 'answered', 'closed'], true)) {
                throw new InvalidArgumentException('Valitud pöördumise staatus ei sobi.');
            }
            $found = app_update_json(TICKETS_FILE, static function (array &$tickets) use ($code, $status): bool {
                $index = api_find_ticket($tickets, $code);
                if ($index === null) {
                    return false;
                }
                $tickets[$index]['status'] = $status;
                $tickets[$index]['updatedAt'] = date(DATE_ATOM);
                return true;
            });
            if (!$found) {
                api_send(['error' => 'Pöördumist ei leitud.'], 404);
            }
            api_send(['ok' => true]);

        default:
            api_send(['error' => 'Tundmatu API tegevus.'], 404);
    }
} catch (InvalidArgumentException $exception) {
    api_send(['error' => $exception->getMessage()], 400);
} catch (DomainException $exception) {
    $status = in_array($exception->getCode(), [401, 403], true) ? $exception->getCode() : 429;
    api_send(['error' => $exception->getMessage()], $status);
} catch (Throwable $exception) {
    error_log('MeeleKompass API error: ' . $exception->getMessage());
    api_send(['error' => 'Päringu töötlemisel tekkis serveri viga. Proovi hiljem uuesti.'], 500);
}
