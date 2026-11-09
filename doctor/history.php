<?php
/**
 * Doctor Consultation History
 */

require_once __DIR__ . '/../config/config.php';
require_role('doctor');

$user = current_user();
$pdo = Database::getConnection();

$docStmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ? LIMIT 1");
$docStmt->execute([$user['id']]);
$doctor = $docStmt->fetch();

$searchQuery = trim($_GET['q'] ?? '');
$filterDate = trim($_GET['date'] ?? '');

$sql = "
    SELECT a.*, u.name AS patient_name, u.phone AS patient_phone, u.email AS patient_email,
           p.id AS prescription_id, p.diagnosis
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    LEFT JOIN prescriptions p ON a.id = p.appointment_id
    WHERE a.doctor_id = ?
";
$params = [$doctor['id']];

if (!empty($searchQuery)) {
    $sql .= " AND (u.name LIKE ? OR u.phone LIKE ? OR p.diagnosis LIKE ?)";
    $term = "%$searchQuery%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($filterDate)) {
    $sql .= " AND a.appointment_date = ?";
    $params[] = $filterDate;
}

$sql .= " ORDER BY a.appointment_date DESC, a.token_number DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$history = $stmt->fetchAll();

$pageTitle = "Consultation History";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-history" style="color:var(--primary);"></i> Consultation Archive</h1>
        <p class="page-subtitle">View patient history, diagnoses, and past issued prescriptions</p>
    </div>
</div>

<div class="card" style="padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
    <form method="GET" action="" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
        <div style="flex: 2; min-width: 200px;">
            <input type="text" name="q" class="form-control" placeholder="Search by patient name, phone, or diagnosis..." value="<?= htmlspecialchars($searchQuery) ?>">
        </div>
        <div style="width: 180px;">
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
        </div>
        <div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
            <a href="history.php" class="btn btn-secondary btn-sm">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Token #</th>
                    <th>Patient Name</th>
                    <th>Contact</th>
                    <th>Diagnosis</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                            No consultation records found matching your filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td><strong><?= format_date($h['appointment_date']) ?></strong></td>
                            <td><span class="badge" style="background:#e0f2fe; color:#0369a1;">#<?= $h['token_number'] ?></span></td>
                            <td><strong><?= htmlspecialchars($h['patient_name']) ?></strong></td>
                            <td><?= htmlspecialchars($h['patient_phone']) ?></td>
                            <td><?= htmlspecialchars($h['diagnosis'] ?: ($h['reason_for_visit'] ?: 'General Consult')) ?></td>
                            <td><span class="badge badge-<?= $h['status'] ?>"><?= $h['status'] ?></span></td>
                            <td>
                                <?php if ($h['prescription_id']): ?>
                                    <a href="<?= BASE_URL ?>/patient/prescription.php?id=<?= $h['prescription_id'] ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-file-prescription"></i> View Rx
                                    </a>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size: 0.8rem;">No Rx</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
