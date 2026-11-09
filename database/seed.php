<?php
/**
 * Database Migration & Initial Seeder Script
 * Doctor Connect Platform
 *
 * Usage:
 * - CLI: php database/seed.php
 * - Web: http://localhost/Doctor connect/database/seed.php
 */

require_once __DIR__ . '/../config/database.php';

// Allow running via CLI or Browser
$isCli = (php_sapi_name() === 'cli');

function out($msg) {
    global $isCli;
    echo $isCli ? ($msg . "\n") : ("<p>" . htmlspecialchars($msg) . "</p>");
}

try {
    // 1. Connect without database first to ensure it exists (try standard WAMP/XAMPP ports: 3306, 3307, 3308)
    $portsToTry = array_unique([DB_PORT, '3306', '3307', '3308']);
    $pdoRoot = null;
    $connectedPort = null;
    $lastError = null;

    foreach ($portsToTry as $port) {
        try {
            $dsnNoDb = "mysql:host=" . DB_HOST . ";port=" . $port . ";charset=" . DB_CHARSET;
            $pdoRoot = new PDO($dsnNoDb, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $connectedPort = $port;
            break;
        } catch (PDOException $pe) {
            $lastError = $pe;
            continue;
        }
    }

    if (!$pdoRoot) {
        throw new Exception("Could not connect to MySQL/MariaDB on any port (tried 3306, 3307, 3308). Please ensure WampServer is running (green icon) and MySQL/MariaDB service is started. Details: " . ($lastError ? $lastError->getMessage() : ''));
    }

    $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    out("✔ Database '" . DB_NAME . "' checked/created successfully (Connected on port $connectedPort).");

    // 2. Connect to the actual database
    $pdo = Database::getConnection();

    // 3. Execute Schema
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("schema.sql file not found at " . $schemaFile);
    }
    $schemaSql = file_get_contents($schemaFile);
    $pdo->exec($schemaSql);
    out("✔ Schema tables created successfully.");

    // 4. Seed Users
    $users = [
        [
            'name' => 'Dr. Robert Admin',
            'email' => 'admin@doctorconnect.com',
            'phone' => '9876543210',
            'password' => 'admin123',
            'role' => 'admin'
        ],
        [
            'name' => 'Dr. Alexander Smith',
            'email' => 'dr.smith@doctorconnect.com',
            'phone' => '9876543211',
            'password' => 'doctor123',
            'role' => 'doctor'
        ],
        [
            'name' => 'Dr. Sarah Jenkins',
            'email' => 'dr.sarah@doctorconnect.com',
            'phone' => '9876543212',
            'password' => 'doctor123',
            'role' => 'doctor'
        ],
        [
            'name' => 'Dr. Emily Vance',
            'email' => 'dr.emily@doctorconnect.com',
            'phone' => '9876543213',
            'password' => 'doctor123',
            'role' => 'doctor'
        ],
        [
            'name' => 'John Doe (Patient)',
            'email' => 'john.doe@example.com',
            'phone' => '9876543214',
            'password' => 'patient123',
            'role' => 'patient'
        ],
        [
            'name' => 'Alice Cooper (Patient)',
            'email' => 'alice@example.com',
            'phone' => '9876543215',
            'password' => 'patient123',
            'role' => 'patient'
        ]
    ];

    $insertUserStmt = $pdo->prepare("
        INSERT INTO users (name, email, phone, password_hash, role) 
        VALUES (?, ?, ?, ?, ?)
    ");

    $userIds = [];
    foreach ($users as $u) {
        $hash = password_hash($u['password'], PASSWORD_BCRYPT);
        $insertUserStmt->execute([$u['name'], $u['email'], $u['phone'], $hash, $u['role']]);
        $userIds[$u['email']] = (int)$pdo->lastInsertId();
    }
    out("✔ Default users seeded (Admin, Doctors, Patients).");

    // 5. Seed Doctors Table
    $insertDocStmt = $pdo->prepare("
        INSERT INTO doctors (user_id, specialization, qualification, consultation_fee, avg_consultation_mins, bio)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $doctors = [
        [
            'user_id' => $userIds['dr.smith@doctorconnect.com'],
            'specialization' => 'Cardiology',
            'qualification' => 'MBBS, MD (Cardiology), FACC',
            'fee' => 700.00,
            'avg_mins' => 15,
            'bio' => 'Senior Cardiologist with 14+ years experience in preventive heart care, hypertension, and arrhythmia management.'
        ],
        [
            'user_id' => $userIds['dr.sarah@doctorconnect.com'],
            'specialization' => 'General Physician',
            'qualification' => 'MBBS, DNB (Internal Medicine)',
            'fee' => 450.00,
            'avg_mins' => 15,
            'bio' => 'Primary care specialist dealing with infectious diseases, seasonal fevers, diabetes, and comprehensive health checkups.'
        ],
        [
            'user_id' => $userIds['dr.emily@doctorconnect.com'],
            'specialization' => 'Pediatrics',
            'qualification' => 'MBBS, MD (Pediatrics)',
            'fee' => 500.00,
            'avg_mins' => 15,
            'bio' => 'Child specialist and neonatologist experienced in child immunizations, nutrition, and pediatric wellness.'
        ]
    ];

    $docIds = [];
    foreach ($doctors as $d) {
        $insertDocStmt->execute([
            $d['user_id'],
            $d['specialization'],
            $d['qualification'],
            $d['fee'],
            $d['avg_mins'],
            $d['bio']
        ]);
        $docIds[] = (int)$pdo->lastInsertId();
    }
    out("✔ Doctor profiles seeded.");

    // 6. Seed Schedules for each doctor (Mon to Sat: days 1 through 6)
    $schedStmt = $pdo->prepare("
        INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, max_tokens, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");

    foreach ($docIds as $dId) {
        for ($day = 0; $day <= 6; $day++) {
            // Sunday (0) closed, Monday-Saturday (1-6) open
            if ($day === 0) continue;
            $schedStmt->execute([
                $dId,
                $day,
                '09:00:00',
                '13:00:00',
                15,
                25
            ]);
        }
    }
    out("✔ Weekly schedules seeded (Mon-Sat 09:00 - 13:00).");

    // 7. Seed Sample Appointments for Today for Dr. Alexander Smith
    $today = date('Y-m-d');
    $sampleAppointments = [
        [
            'patient_id' => $userIds['john.doe@example.com'],
            'doctor_id' => $docIds[0],
            'token' => 1,
            'status' => 'serving',
            'est_time' => "$today 09:00:00",
            'reason' => 'Routine BP checkup and chest tightness review'
        ],
        [
            'patient_id' => $userIds['alice@example.com'],
            'doctor_id' => $docIds[0],
            'token' => 2,
            'status' => 'waiting',
            'est_time' => "$today 09:15:00",
            'reason' => 'ECG report consultation'
        ]
    ];

    $apptStmt = $pdo->prepare("
        INSERT INTO appointments 
        (patient_id, doctor_id, appointment_date, token_number, status, meeting_link, estimated_time, reason_for_visit)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($sampleAppointments as $sa) {
        $meetingLink = "https://meet.jit.si/DoctorConnect-Room-" . rand(100, 999) . "-" . substr(md5($today), 0, 8);
        $apptStmt->execute([
            $sa['patient_id'],
            $sa['doctor_id'],
            $today,
            $sa['token'],
            $sa['status'],
            $meetingLink,
            $sa['est_time'],
            $sa['reason']
        ]);
        $createdApptId = (int)$pdo->lastInsertId();

        // Add queue log
        $pdo->prepare("INSERT INTO queue_logs (appointment_id, action, performed_by) VALUES (?, ?, ?)")
            ->execute([$createdApptId, ($sa['status'] === 'serving' ? 'called' : 'called'), $userIds['admin@doctorconnect.com']]);
    }
    out("✔ Initial live appointments seeded for today ($today) for Dr. Smith.");

    out("\n=======================================================");
    out("🎉 SYSTEM SEEDING COMPLETED SUCCESSFULLY!");
    out("=======================================================");
    out("DEMO CREDENTIALS:");
    out("• Admin:   admin@doctorconnect.com    / admin123");
    out("• Doctor:  dr.smith@doctorconnect.com / doctor123");
    out("• Doctor:  dr.sarah@doctorconnect.com / doctor123");
    out("• Doctor:  dr.emily@doctorconnect.com / doctor123");
    out("• Patient: john.doe@example.com       / patient123");
    out("• Patient: alice@example.com          / patient123");
    out("=======================================================");

} catch (Exception $e) {
    out("❌ Error during seeding: " . $e->getMessage());
}
