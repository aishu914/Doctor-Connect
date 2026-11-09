<?php
/**
 * API: Get Slots and Availability Endpoint
 * Dynamically computes available slots and timing estimates.
 * GET /api/get_slots.php?doctor_id=1&date=2026-11-09
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$doctorId = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT);
$date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_SPECIAL_CHARS);

if (!$doctorId || !$date) {
    echo json_encode(['success' => false, 'message' => 'doctor_id and date are required.']);
    exit;
}

if ($date < date('Y-m-d')) {
    echo json_encode(['success' => false, 'message' => 'Selected date cannot be in the past.']);
    exit;
}

try {
    $pdo = Database::getConnection();
    $dayOfWeek = (int)date('w', strtotime($date));

    // Get doctor schedule for this day
    $stmt = $pdo->prepare("
        SELECT d.id AS doctor_id, d.consultation_fee, d.avg_consultation_mins,
               s.start_time, s.end_time, s.max_tokens, s.slot_duration
        FROM doctors d
        JOIN doctor_schedules s ON d.id = s.doctor_id
        WHERE d.id = ? AND d.is_active = 1 AND s.day_of_week = ? AND s.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$doctorId, $dayOfWeek]);
    $schedule = $stmt->fetch();

    if (!$schedule) {
        echo json_encode([
            'success' => false,
            'message' => 'Doctor is not available on ' . get_day_name($dayOfWeek) . ' (' . format_date($date) . ').'
        ]);
        exit;
    }

    // Get current token count booked for this doctor on this date
    $countStmt = $pdo->prepare("
        SELECT COALESCE(MAX(token_number), 0) AS max_token,
               COUNT(id) AS total_active
        FROM appointments
        WHERE doctor_id = ? AND appointment_date = ? AND status != 'cancelled'
    ");
    $countStmt->execute([$doctorId, $date]);
    $tokenStats = $countStmt->fetch();

    $maxTokens = (int)$schedule['max_tokens'];
    $currentTokenMax = (int)$tokenStats['max_token'];
    $nextToken = $currentTokenMax + 1;

    if ($nextToken > $maxTokens) {
        echo json_encode([
            'success' => false,
            'message' => 'All tokens for this date have been booked (Capacity: ' . $maxTokens . ').'
        ]);
        exit;
    }

    $avgMins = (int)($schedule['avg_consultation_mins'] ?: 15);
    $offsetMins = ($nextToken - 1) * $avgMins;
    $estDateTimeStr = date('Y-m-d H:i:s', strtotime("{$date} {$schedule['start_time']} + {$offsetMins} minutes"));

    echo json_encode([
        'success' => true,
        'data' => [
            'doctor_id' => $doctorId,
            'date' => $date,
            'day_name' => get_day_name($dayOfWeek),
            'start_time' => $schedule['start_time'],
            'end_time' => $schedule['end_time'],
            'formatted_hours' => format_time($schedule['start_time']) . ' - ' . format_time($schedule['end_time']),
            'avg_mins' => $avgMins,
            'booked_tokens' => $currentTokenMax,
            'max_tokens' => $maxTokens,
            'available_tokens' => max(0, $maxTokens - $currentTokenMax),
            'next_token_number' => $nextToken,
            'estimated_time' => $estDateTimeStr,
            'estimated_time_formatted' => date('h:i A', strtotime($estDateTimeStr)),
            'consultation_fee' => (float)$schedule['consultation_fee']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
