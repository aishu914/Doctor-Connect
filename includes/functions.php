<?php
/**
 * Global Utility Functions
 */

function sanitize(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header("Location: " . $url);
    exit;
}

function set_flash(string $type, string $message): void {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flash(): array {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function render_flash_messages(): string {
    $flashes = get_flash();
    if (empty($flashes)) return '';

    $html = '<div class="flash-messages-container">';
    foreach ($flashes as $flash) {
        $type = htmlspecialchars($flash['type']);
        $icon = match($type) {
            'success' => 'fa-check-circle',
            'danger', 'error' => 'fa-exclamation-circle',
            'warning' => 'fa-exclamation-triangle',
            default => 'fa-info-circle'
        };
        $alertClass = ($type === 'error') ? 'alert-danger' : 'alert-' . $type;
        $html .= '<div class="alert ' . $alertClass . ' alert-dismissible">';
        $html .= '<i class="fas ' . $icon . '"></i> ';
        $html .= '<span>' . htmlspecialchars($flash['message']) . '</span>';
        $html .= '<button type="button" class="close-alert" onclick="this.parentElement.remove();">&times;</button>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function get_day_name(int $dayOfWeek): string {
    $days = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday'
    ];
    return $days[$dayOfWeek] ?? 'Unknown';
}

function format_time(string $timeStr): string {
    return date('h:i A', strtotime($timeStr));
}

function format_datetime(?string $datetimeStr): string {
    if (!$datetimeStr) return 'TBD';
    return date('d M Y, h:i A', strtotime($datetimeStr));
}

function format_date(string $dateStr): string {
    return date('D, d M Y', strtotime($dateStr));
}

/**
 * Generate secure Jitsi Meet room link for appointment
 */
function generate_jitsi_link(int $appointmentId, string $date): string {
    // Unique salt room hash to prevent guessing
    $hash = substr(hash('sha256', "doctor_connect_appt_{$appointmentId}_{$date}"), 0, 12);
    return "https://" . JITSI_DEFAULT_DOMAIN . "/DoctorConnect-Room-{$appointmentId}-{$hash}";
}

/**
 * Rule 3.4 & Key Design Decision 3:
 * Link visible to patient ONLY when token is SERVING or NEXT in line!
 */
function is_meeting_link_accessible(array $appointment, int $currentServingToken): bool {
    // If appointment is already completed or cancelled or no-show, cannot join
    if (in_array($appointment['status'], ['completed', 'cancelled', 'no_show'])) {
        return false;
    }
    
    // Status is actively serving
    if ($appointment['status'] === 'serving') {
        return true;
    }

    // Status is waiting and token is next in line
    // If no one is serving yet, token #1 is NEXT.
    if ($appointment['status'] === 'waiting') {
        if ($currentServingToken === 0 && $appointment['token_number'] === 1) {
            return true;
        }
        if ($appointment['token_number'] === $currentServingToken + 1) {
            return true;
        }
    }

    return false;
}
