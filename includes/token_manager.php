<?php
/**
 * Token Manager & Atomic Queue Engine
 * Handles race-safe token generation, live queue calculations, and status transitions.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

class TokenManager {

    /**
     * Atomically books an appointment and assigns the next race-safe token number.
     * Uses InnoDB transactions with FOR UPDATE and an automatic retry loop for 100% race safety.
     *
     * @param int $patientId
     * @param int $doctorId
     * @param string $appointmentDate (YYYY-MM-DD)
     * @param string|null $reason
     * @param int $maxRetries
     * @return array Array with success status, token_number, appointment_id, estimated_time, or error message
     */
    public static function bookToken(int $patientId, int $doctorId, string $appointmentDate, ?string $reason = null, int $maxRetries = 5): array {
        $pdo = Database::getConnection();

        // 1. Validate appointment date is not in the past
        $today = date('Y-m-d');
        if ($appointmentDate < $today) {
            return ['success' => false, 'message' => 'Cannot book appointments for past dates.'];
        }

        // 2. Prevent same patient booking multiple tokens for the same doctor on the same day
        $checkStmt = $pdo->prepare("
            SELECT id, token_number, status 
            FROM appointments 
            WHERE patient_id = ? AND doctor_id = ? AND appointment_date = ? AND status NOT IN ('cancelled')
            LIMIT 1
        ");
        $checkStmt->execute([$patientId, $doctorId, $appointmentDate]);
        if ($existing = $checkStmt->fetch()) {
            return [
                'success' => false,
                'message' => "You already have an active Token (#{$existing['token_number']}) for this doctor on " . format_date($appointmentDate) . "."
            ];
        }

        // 3. Fetch doctor and schedule for this day of week
        $dayOfWeek = (int)date('w', strtotime($appointmentDate)); // 0 = Sun, 6 = Sat
        $docStmt = $pdo->prepare("
            SELECT d.id AS doctor_id, d.avg_consultation_mins, d.consultation_fee, u.name AS doctor_name,
                   s.start_time, s.end_time, s.max_tokens, s.slot_duration
            FROM doctors d
            JOIN users u ON d.user_id = u.id
            JOIN doctor_schedules s ON d.id = s.doctor_id
            WHERE d.id = ? AND d.is_active = 1 AND s.day_of_week = ? AND s.is_active = 1
            LIMIT 1
        ");
        $docStmt->execute([$doctorId, $dayOfWeek]);
        $doctorSchedule = $docStmt->fetch();

        if (!$doctorSchedule) {
            return [
                'success' => false,
                'message' => "Doctor is not available on " . get_day_name($dayOfWeek) . " (" . format_date($appointmentDate) . ")."
            ];
        }

        $avgMins = (int)($doctorSchedule['avg_consultation_mins'] ?: 15);
        $startTime = $doctorSchedule['start_time'];
        $maxTokens = (int)($doctorSchedule['max_tokens'] ?: 50);

        // 4. Atomic Token Assignment Loop with Transaction & Retry
        $attempt = 0;
        while ($attempt < $maxRetries) {
            $attempt++;
            try {
                $pdo->beginTransaction();

                // Lock and query the highest token assigned for this doctor on this day
                $lockStmt = $pdo->prepare("
                    SELECT COALESCE(MAX(token_number), 0) AS max_token
                    FROM appointments
                    WHERE doctor_id = ? AND appointment_date = ?
                    FOR UPDATE
                ");
                $lockStmt->execute([$doctorId, $appointmentDate]);
                $row = $lockStmt->fetch();
                $currentTokenMax = (int)($row['max_token'] ?? 0);
                $nextToken = $currentTokenMax + 1;

                // Check capacity limit
                if ($nextToken > $maxTokens) {
                    $pdo->rollBack();
                    return [
                        'success' => false,
                        'message' => "Token quota full! Maximum tokens ($maxTokens) reached for this doctor on this date."
                    ];
                }

                // Calculate estimated meeting time:
                // session_start + (token_number - 1) * avg_consultation_mins
                $offsetMins = ($nextToken - 1) * $avgMins;
                $estimatedTime = date('Y-m-d H:i:s', strtotime("{$appointmentDate} {$startTime} + {$offsetMins} minutes"));

                // Temporary placeholder link, will be updated with actual appointment_id
                $insertStmt = $pdo->prepare("
                    INSERT INTO appointments 
                    (patient_id, doctor_id, appointment_date, token_number, status, meeting_link, estimated_time, reason_for_visit)
                    VALUES (?, ?, ?, ?, 'waiting', NULL, ?, ?)
                ");
                $insertStmt->execute([
                    $patientId,
                    $doctorId,
                    $appointmentDate,
                    $nextToken,
                    $estimatedTime,
                    $reason
                ]);

                $appointmentId = (int)$pdo->lastInsertId();

                // Generate secure Jitsi Meet room link
                $meetingLink = generate_jitsi_link($appointmentId, $appointmentDate);
                $updStmt = $pdo->prepare("UPDATE appointments SET meeting_link = ? WHERE id = ?");
                $updStmt->execute([$meetingLink, $appointmentId]);

                // Log queue event
                $logStmt = $pdo->prepare("
                    INSERT INTO queue_logs (appointment_id, action, performed_by) 
                    VALUES (?, 'called', ?)
                ");
                // Do not mark called yet, this is initial booking

                $pdo->commit();

                return [
                    'success' => true,
                    'appointment_id' => $appointmentId,
                    'token_number' => $nextToken,
                    'estimated_time' => $estimatedTime,
                    'meeting_link' => $meetingLink,
                    'doctor_name' => $doctorSchedule['doctor_name'],
                    'message' => "Token #{$nextToken} booked successfully!"
                ];

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                // Error Code 1062 is MySQL duplicate entry for UNIQUE KEY
                // Error Code 1213 is Deadlock found when trying to get lock
                $isDuplicate = (int)$e->errorInfo[1] === 1062 || str_contains($e->getMessage(), '1062');
                $isDeadlock  = (int)$e->errorInfo[1] === 1213 || str_contains($e->getMessage(), '1213');

                if (($isDuplicate || $isDeadlock) && $attempt < $maxRetries) {
                    // Jittered backoff (10ms - 50ms) before retry
                    usleep(rand(10000, 50000));
                    continue;
                }

                error_log("Token booking error: " . $e->getMessage());
                return [
                    'success' => false,
                    'message' => "Booking failed due to high concurrency. Please try again. (" . $e->getMessage() . ")"
                ];
            }
        }

        return ['success' => false, 'message' => 'Server busy. Could not allocate token. Please try again.'];
    }

    /**
     * Fetch complete live queue state for a doctor on a specific date.
     */
    public static function getLiveQueue(int $doctorId, string $date): array {
        $pdo = Database::getConnection();

        // 1. Doctor & Schedule Details
        $docStmt = $pdo->prepare("
            SELECT d.id, u.name AS doctor_name, d.specialization, d.avg_consultation_mins,
                   s.start_time, s.end_time
            FROM doctors d
            JOIN users u ON d.user_id = u.id
            LEFT JOIN doctor_schedules s ON d.id = s.doctor_id AND s.day_of_week = DAYOFWEEK(?) - 1
            WHERE d.id = ?
            LIMIT 1
        ");
        $docStmt->execute([$date, $doctorId]);
        $doctor = $docStmt->fetch();

        if (!$doctor) {
            return ['error' => 'Doctor not found'];
        }

        // 2. Fetch all appointments for doctor on this date
        $stmt = $pdo->prepare("
            SELECT a.id, a.token_number, a.status, a.estimated_time, a.reason_for_visit, 
                   a.meeting_link, a.created_at,
                   u.id AS patient_id, u.name AS patient_name, u.phone AS patient_phone
            FROM appointments a
            JOIN users u ON a.patient_id = u.id
            WHERE a.doctor_id = ? AND a.appointment_date = ?
            ORDER BY a.token_number ASC
        ");
        $stmt->execute([$doctorId, $date]);
        $appointments = $stmt->fetchAll();

        $currentlyServing = null;
        $nextInLine = null;
        $waitingList = [];
        $completedList = [];
        $noShowList = [];
        $cancelledList = [];

        foreach ($appointments as $appt) {
            match($appt['status']) {
                'serving'   => $currentlyServing = $appt,
                'waiting'   => $waitingList[] = $appt,
                'completed' => $completedList[] = $appt,
                'no_show'   => $noShowList[] = $appt,
                'cancelled' => $cancelledList[] = $appt,
                default     => null
            };
        }

        // Next in line is the first patient in waiting list
        if (!empty($waitingList)) {
            $nextInLine = $waitingList[0];
        }

        $servingTokenNumber = $currentlyServing ? (int)$currentlyServing['token_number'] : 0;

        return [
            'doctor' => [
                'id' => (int)$doctor['id'],
                'name' => $doctor['doctor_name'],
                'specialization' => $doctor['specialization'],
                'avg_consultation_mins' => (int)($doctor['avg_consultation_mins'] ?: 15),
                'session_start' => $doctor['start_time'] ?? '09:00:00',
                'session_end' => $doctor['end_time'] ?? '17:00:00'
            ],
            'date' => $date,
            'currently_serving' => $currentlyServing,
            'current_serving_token' => $servingTokenNumber,
            'next_in_line' => $nextInLine,
            'next_token' => $nextInLine ? (int)$nextInLine['token_number'] : null,
            'waiting_count' => count($waitingList),
            'completed_count' => count($completedList),
            'no_show_count' => count($noShowList),
            'total_tokens' => count($appointments),
            'waiting_tokens' => array_map(fn($w) => [
                'id' => $w['id'],
                'token_number' => (int)$w['token_number'],
                'patient_name' => $w['patient_name'],
                'estimated_time' => $w['estimated_time'],
                'status' => $w['status']
            ], $waitingList),
            'all_appointments' => $appointments
        ];
    }

    /**
     * Advance queue: Doctor calls the next waiting patient.
     * Automatically completes or handles any currently serving token if requested.
     */
    public static function callNextToken(int $doctorId, string $date, int $performedByUserId, ?string $currentTokenAction = 'completed'): array {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            // Find current serving token if any
            $servingStmt = $pdo->prepare("
                SELECT id, token_number FROM appointments 
                WHERE doctor_id = ? AND appointment_date = ? AND status = 'serving'
                FOR UPDATE
            ");
            $servingStmt->execute([$doctorId, $date]);
            $currentServing = $servingStmt->fetch();

            if ($currentServing) {
                $finalStatus = ($currentTokenAction === 'no_show') ? 'no_show' : 'completed';
                $updateCurrent = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
                $updateCurrent->execute([$finalStatus, $currentServing['id']]);

                // Log audit
                $logStmt = $pdo->prepare("
                    INSERT INTO queue_logs (appointment_id, action, performed_by) 
                    VALUES (?, ?, ?)
                ");
                $logStmt->execute([$currentServing['id'], $finalStatus === 'no_show' ? 'no_show' : 'served', $performedByUserId]);
            }

            // Find next lowest waiting token
            $nextStmt = $pdo->prepare("
                SELECT id, token_number, patient_id FROM appointments 
                WHERE doctor_id = ? AND appointment_date = ? AND status = 'waiting'
                ORDER BY token_number ASC
                LIMIT 1
                FOR UPDATE
            ");
            $nextStmt->execute([$doctorId, $date]);
            $nextAppt = $nextStmt->fetch();

            if (!$nextAppt) {
                $pdo->commit();
                return [
                    'success' => true,
                    'has_next' => false,
                    'message' => 'Queue complete! No more waiting patients for today.'
                ];
            }

            // Mark this token as serving
            $callStmt = $pdo->prepare("UPDATE appointments SET status = 'serving' WHERE id = ?");
            $callStmt->execute([$nextAppt['id']]);

            // Add queue log
            $logNext = $pdo->prepare("
                INSERT INTO queue_logs (appointment_id, action, performed_by) 
                VALUES (?, 'called', ?)
            ");
            $logNext->execute([$nextAppt['id'], $performedByUserId]);

            // Add notification for patient
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message)
                VALUES (?, 'Your Turn is Called!', ?)
            ");
            $notifMsg = "Doctor is ready for Token #{$nextAppt['token_number']}. Please join the video consultation now!";
            $notifStmt->execute([$nextAppt['patient_id'], $notifMsg]);

            $pdo->commit();

            return [
                'success' => true,
                'has_next' => true,
                'appointment_id' => $nextAppt['id'],
                'token_number' => $nextAppt['token_number'],
                'message' => "Token #{$nextAppt['token_number']} is now being served."
            ];

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Advance queue error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to advance queue: ' . $e->getMessage()];
        }
    }

    /**
     * Mark single appointment status (served, no_show, cancelled)
     */
    public static function updateAppointmentStatus(int $appointmentId, string $status, int $performedByUserId, ?string $reason = null): array {
        $validStatuses = ['waiting', 'serving', 'completed', 'no_show', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            return ['success' => false, 'message' => 'Invalid appointment status.'];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
        $stmt->execute([$appointmentId]);
        $appt = $stmt->fetch();

        if (!$appt) {
            return ['success' => false, 'message' => 'Appointment not found.'];
        }

        // Cancel policy check: e.g. cannot cancel within 2 hours of estimated meeting time
        if ($status === 'cancelled' && $appt['estimated_time']) {
            $estTimestamp = strtotime($appt['estimated_time']);
            $hoursUntilMeeting = ($estTimestamp - time()) / 3600;
            if ($hoursUntilMeeting < CANCELLATION_CUTOFF_HOURS && $hoursUntilMeeting > 0) {
                return [
                    'success' => false,
                    'message' => "Appointments cannot be cancelled within " . CANCELLATION_CUTOFF_HOURS . " hours of estimated meeting time."
                ];
            }
        }

        $upd = $pdo->prepare("
            UPDATE appointments 
            SET status = ?, cancellation_reason = COALESCE(?, cancellation_reason) 
            WHERE id = ?
        ");
        $upd->execute([$status, $reason, $appointmentId]);

        // Map status to queue log action
        $action = match($status) {
            'serving'   => 'called',
            'completed' => 'served',
            'no_show'   => 'no_show',
            'cancelled' => 'cancelled',
            default     => 'skipped'
        };

        $log = $pdo->prepare("INSERT INTO queue_logs (appointment_id, action, performed_by) VALUES (?, ?, ?)");
        $log->execute([$appointmentId, $action, $performedByUserId]);

        return ['success' => true, 'message' => "Appointment marked as " . ucfirst($status)];
    }
}
