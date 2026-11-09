<?php
/**
 * API: Queue Control Endpoint (Doctor / Admin Console)
 * POST /api/call_token.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/token_manager.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in() || !has_role(['doctor', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Read JSON or POST body
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$action = $input['action'] ?? '';
$doctorId = (int)($input['doctor_id'] ?? 0);
$date = $input['date'] ?? date('Y-m-d');
$user = current_user();

try {
    if ($action === 'call_next') {
        $currentAction = $input['current_token_action'] ?? 'completed';
        $res = TokenManager::callNextToken($doctorId, $date, $user['id'], $currentAction);
        echo json_encode($res);
        exit;
    }

    if ($action === 'update_status') {
        $appointmentId = (int)($input['appointment_id'] ?? 0);
        $newStatus = $input['status'] ?? '';
        $reason = $input['reason'] ?? null;

        if (!$appointmentId || !$newStatus) {
            echo json_encode(['success' => false, 'message' => 'Appointment ID and status required.']);
            exit;
        }

        $res = TokenManager::updateAppointmentStatus($appointmentId, $newStatus, $user['id'], $reason);
        echo json_encode($res);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
