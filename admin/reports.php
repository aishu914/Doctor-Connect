<?php
/**
 * Admin Reports & CSV Export
 */

require_once __DIR__ . '/../config/config.php';
require_role('admin');

$pdo = Database::getConnection();

$fromDate = filter_input(INPUT_GET, 'from_date', FILTER_SANITIZE_SPECIAL_CHARS) ?: date('Y-m-01');
$toDate   = filter_input(INPUT_GET, 'to_date', FILTER_SANITIZE_SPECIAL_CHARS) ?: date('Y-m-d');
$doctorId = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT) ?: 0;

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportSql = "
        SELECT a.id AS appointment_id, a.appointment_date, a.token_number, a.status,
               uDoc.name AS doctor_name, d.specialization,
               uPat.name AS patient_name, uPat.phone AS patient_phone,
               a.estimated_time, a.reason_for_visit, a.created_at
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users uDoc ON d.user_id = uDoc.id
        JOIN users uPat ON a.patient_id = uPat.id
        WHERE a.appointment_date BETWEEN ? AND ?
    ";
    $params = [$fromDate, $toDate];
    if ($doctorId > 0) {
        $exportSql .= " AND a.doctor_id = ?";
        $params[] = $doctorId;
    }
    $exportSql .= " ORDER BY a.appointment_date DESC, a.token_number ASC";

    $stmt = $pdo->prepare($exportSql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=DoctorConnect_Report_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Appointment ID', 'Date', 'Token #', 'Doctor', 'Specialization', 'Patient Name', 'Phone', 'Estimated Time', 'Status', 'Reason', 'Booked At']);

    foreach ($rows as $r) {
        fputcsv($output, [
            $r['appointment_id'],
            $r['appointment_date'],
            $r['token_number'],
            $r['doctor_name'],
            $r['specialization'],
            $r['patient_name'],
            $r['patient_phone'],
            $r['estimated_time'],
            $r['status'],
            $r['reason_for_visit'],
            $r['created_at']
        ]);
    }
    fclose($output);
    exit;
}

// Fetch report stats
$statsSql = "
    SELECT 
        COUNT(*) AS total_appointments,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) AS noshow_count,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
        SUM(CASE WHEN status IN ('waiting', 'serving') THEN 1 ELSE 0 END) AS active_count
    FROM appointments
    WHERE appointment_date BETWEEN ? AND ?
";
$statsParams = [$fromDate, $toDate];
if ($doctorId > 0) {
    $statsSql .= " AND doctor_id = ?";
    $statsParams[] = $doctorId;
}
$sStmt = $pdo->prepare($statsSql);
$sStmt->execute($statsParams);
$reportStats = $sStmt->fetch();

// Doctor breakdown table
$docBreakdown = $pdo->prepare("
    SELECT d.id, u.name AS doctor_name, d.specialization,
           COUNT(a.id) AS total_tokens,
           SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS completed,
           SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END) AS noshow,
           SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN appointments a ON d.id = a.doctor_id AND a.appointment_date BETWEEN ? AND ?
    GROUP BY d.id
    ORDER BY total_tokens DESC
");
$docBreakdown->execute([$fromDate, $toDate]);
$breakdownList = $docBreakdown->fetchAll();

$allDocs = $pdo->query("SELECT d.id, u.name FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.is_active = 1")->fetchAll();

$pageTitle = "Reports & Analytics";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-chart-bar" style="color:var(--primary);"></i> Clinic Analytics & Reports</h1>
        <p class="page-subtitle">Track consultation volume, completion rates, and export audit data</p>
    </div>
    <div>
        <a href="reports.php?export=csv&from_date=<?= urlencode($fromDate) ?>&to_date=<?= urlencode($toDate) ?>&doctor_id=<?= $doctorId ?>" class="btn btn-success">
            <i class="fas fa-file-excel"></i> Export CSV Report
        </a>
    </div>
</div>

<!-- FILTER CARD -->
<div class="card" style="padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
    <form method="GET" action="" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div>
            <label class="form-label" style="font-size:0.8rem; margin-bottom: 0.2rem;">From Date:</label>
            <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($fromDate) ?>">
        </div>
        <div>
            <label class="form-label" style="font-size:0.8rem; margin-bottom: 0.2rem;">To Date:</label>
            <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($toDate) ?>">
        </div>
        <div style="flex: 1; min-width: 200px;">
            <label class="form-label" style="font-size:0.8rem; margin-bottom: 0.2rem;">Doctor Filter:</label>
            <select name="doctor_id" class="form-control">
                <option value="0">-- All Doctors --</option>
                <?php foreach ($allDocs as $ad): ?>
                    <option value="<?= $ad['id'] ?>" <?= ($doctorId == $ad['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ad['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filters</button>
        </div>
    </form>
</div>

<!-- STATS SUMMARY -->
<div class="grid-4" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fas fa-ticket-alt"></i></div>
        <div>
            <div class="stat-num"><?= (int)$reportStats['total_appointments'] ?></div>
            <div class="stat-label">Total Appointments</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-num"><?= (int)$reportStats['completed_count'] ?></div>
            <div class="stat-label">Completed Consults</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon danger"><i class="fas fa-user-slash"></i></div>
        <div>
            <div class="stat-num"><?= (int)$reportStats['noshow_count'] ?></div>
            <div class="stat-label">No-Shows</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fas fa-hourglass-half"></i></div>
        <div>
            <div class="stat-num"><?= (int)$reportStats['active_count'] ?></div>
            <div class="stat-label">Pending / Active</div>
        </div>
    </div>
</div>

<!-- DOCTOR BREAKDOWN TABLE -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Doctor-Wise Performance & Attendance</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Doctor Name</th>
                    <th>Specialization</th>
                    <th>Total Bookings</th>
                    <th>Completed</th>
                    <th>No-Shows</th>
                    <th>Cancelled</th>
                    <th>Completion Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($breakdownList as $b): 
                    $rate = ($b['total_tokens'] > 0) ? round(($b['completed'] / $b['total_tokens']) * 100, 1) : 0;
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($b['doctor_name']) ?></strong></td>
                        <td><?= htmlspecialchars($b['specialization']) ?></td>
                        <td><strong><?= (int)$b['total_tokens'] ?></strong></td>
                        <td><span style="color:var(--success); font-weight:700;"><?= (int)$b['completed'] ?></span></td>
                        <td><span style="color:var(--danger); font-weight:700;"><?= (int)$b['noshow'] ?></span></td>
                        <td><?= (int)$b['cancelled'] ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div style="flex:1; background:#e2e8f0; height:8px; border-radius:4px; overflow:hidden;">
                                    <div style="background:var(--success); height:100%; width:<?= $rate ?>%;"></div>
                                </div>
                                <span style="font-size:0.8rem; font-weight:600;"><?= $rate ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
