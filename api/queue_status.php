<?php
/**
 * API: Queue Status Endpoint
 * GET /api/queue_status.php?doctor_id=1&date=2026-11-09
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/token_manager.php';

header('Content-Type: application/json; charset=utf-8');

$doctorId = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT);
$date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_SPECIAL_CHARS) ?: date('Y-m-d');

if (!$doctorId) {
    echo json_encode(['success' => false, 'message' => 'Valid doctor_id is required.']);
    exit;
}

try {
    $queue = TokenManager::getLiveQueue($doctorId, $date);

    if (isset($queue['error'])) {
        echo json_encode(['success' => false, 'message' => $queue['error']]);
        exit;
    }

    // Check if the requesting user is a patient with an appointment for this queue
    $user = current_user();
    $userAppt = null;
    $userCanJoinMeeting = false;

    if ($user && $user['role'] === 'patient') {
        foreach ($queue['all_appointments'] as $appt) {
            if ($appt['patient_id'] == $user['id']) {
                $userAppt = $appt;
                $userCanJoinMeeting = is_meeting_link_accessible($appt, $queue['current_serving_token']);
                break;
            }
        }
    }

    // Sanitize patient private names for public view unless logged in as doctor or admin
    $isStaff = $user && in_array($user['role'], ['doctor', 'admin']);

    $sanitizedWaiting = array_map(function($w) use ($isStaff, $user) {
        $isOwn = $user && ($w['patient_id'] == $user['id']);
        return [
            'id' => $w['id'],
            'token_number' => $w['token_number'],
            'patient_name' => ($isStaff || $isOwn) ? $w['patient_name'] : ('Patient #' . substr(md5($w['patient_name']), 0, 4)),
            'estimated_time' => $w['estimated_time'],
            'status' => $w['status']
        ];
    }, $queue['waiting_tokens']);

    $response = [
        'success' => true,
        'data' => [
            'doctor' => $queue['doctor'],
            'date' => $queue['date'],
            'current_serving_token' => $queue['current_serving_token'],
            'currently_serving' => $queue['currently_serving'] ? [
                'token_number' => $queue['currently_serving']['token_number'],
                'patient_name' => ($isStaff || ($user && $queue['currently_serving']['patient_id'] == $user['id'])) ? $queue['currently_serving']['patient_name'] : 'In Consultation'
            ] : null,
            'next_token' => $queue['next_token'],
            'waiting_count' => $queue['waiting_count'],
            'completed_count' => $queue['completed_count'],
            'no_show_count' => $queue['no_show_count'],
            'total_tokens' => $queue['total_tokens'],
            'waiting_tokens' => $sanitizedWaiting,
            'user_has_token' => $userAppt ? true : false,
            'user_token_number' => $userAppt ? $userAppt['token_number'] : null,
            'user_appointment_id' => $userAppt ? $userAppt['id'] : null,
            'user_can_join' => $userCanJoinMeeting,
            'user_meeting_link' => $userCanJoinMeeting ? $userAppt['meeting_link'] : null
        ]
    ];

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
