<?php
/**
 * Admin: Doctor Management
 */

require_once __DIR__ . '/../config/config.php';
require_role('admin');

$pdo = Database::getConnection();

// Handle Add Doctor POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_doctor') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrf)) {
        $name      = trim($_POST['name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $password  = $_POST['password'] ?? 'doctor123';
        $spec      = trim($_POST['specialization'] ?? '');
        $qual      = trim($_POST['qualification'] ?? 'MBBS, MD');
        $fee       = (float)($_POST['consultation_fee'] ?? 500);
        $avgMins   = (int)($_POST['avg_consultation_mins'] ?? 15);
        $bio       = trim($_POST['bio'] ?? '');

        if (empty($name) || empty($email) || empty($phone) || empty($spec)) {
            set_flash('danger', 'Name, email, phone, and specialization are required.');
        } else {
            // Check duplicate email
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1");
            $chk->execute([$email, $phone]);
            if ($chk->fetch()) {
                set_flash('danger', 'User with this email or phone number already exists.');
            } else {
                $pdo->beginTransaction();
                try {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $insU = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'doctor')");
                    $insU->execute([$name, $email, $phone, $hash]);
                    $userId = (int)$pdo->lastInsertId();

                    $insDoc = $pdo->prepare("
                        INSERT INTO doctors (user_id, specialization, qualification, consultation_fee, avg_consultation_mins, bio, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, 1)
                    ");
                    $insDoc->execute([$userId, $spec, $qual, $fee, $avgMins, $bio]);
                    $doctorId = (int)$pdo->lastInsertId();

                    // Seed default Monday-Saturday schedule
                    $sched = $pdo->prepare("
                        INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, max_tokens, is_active)
                        VALUES (?, ?, '09:00:00', '13:00:00', ?, 25, 1)
                    ");
                    for ($day = 1; $day <= 6; $day++) {
                        $sched->execute([$doctorId, $day, $avgMins]);
                    }

                    $pdo->commit();
                    set_flash('success', "Doctor '{$name}' created with active schedules!");
                    redirect(BASE_URL . '/admin/doctors.php');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    set_flash('danger', 'Failed to create doctor: ' . $e->getMessage());
                }
            }
        }
    }
}

// Fetch all doctors
$doctorsList = $pdo->query("
    SELECT d.*, u.name, u.email, u.phone,
           (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id = d.id) AS total_consultations
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    ORDER BY d.id DESC
")->fetchAll();

$pageTitle = "Manage Doctors";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-user-md" style="color:var(--primary);"></i> Manage Medical Doctors</h1>
        <p class="page-subtitle">Add specialists, adjust fees, consultation durations, and account logins</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="openAddDoctorModal()">
            <i class="fas fa-plus-circle"></i> Add New Doctor
        </button>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Doctor Name</th>
                    <th>Specialization & Qualification</th>
                    <th>Contact</th>
                    <th>Fee</th>
                    <th>Session Mins</th>
                    <th>Total Consults</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($doctorsList as $doc): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($doc['name']) ?></strong><br>
                            <span style="font-size:0.775rem; color:var(--text-muted);"><?= htmlspecialchars($doc['email']) ?></span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($doc['specialization']) ?></strong><br>
                            <span style="font-size:0.775rem; color:var(--text-muted);"><?= htmlspecialchars($doc['qualification']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($doc['phone']) ?></td>
                        <td><strong><?= CURRENCY_SYMBOL ?><?= number_format($doc['consultation_fee'], 2) ?></strong></td>
                        <td><?= (int)$doc['avg_consultation_mins'] ?> mins</td>
                        <td><span class="badge" style="background:#e0f2fe;color:#0369a1;"><?= (int)$doc['total_consultations'] ?></span></td>
                        <td>
                            <span class="badge badge-<?= $doc['is_active'] ? 'completed' : 'cancelled' ?>">
                                <?= $doc['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD DOCTOR MODAL -->
<div id="addDoctorModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 650px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="font-size: 1.3rem;"><i class="fas fa-user-md" style="color:var(--primary);"></i> Add Specialist Doctor</h3>
            <button type="button" class="close-alert" onclick="closeAddDoctorModal()">&times;</button>
        </div>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_doctor">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="doc_name">Doctor Full Name *</label>
                    <input type="text" id="doc_name" name="name" class="form-control" placeholder="e.g. Dr. Jennifer Lawrence" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="doc_email">Email Address (Login) *</label>
                    <input type="email" id="doc_email" name="email" class="form-control" placeholder="doctor@clinic.com" required>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="doc_phone">Phone Number *</label>
                    <input type="tel" id="doc_phone" name="phone" class="form-control" placeholder="10-digit mobile number" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="doc_pass">Initial Password</label>
                    <input type="password" id="doc_pass" name="password" class="form-control" value="doctor123" required>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="doc_spec">Specialization *</label>
                    <input type="text" id="doc_spec" name="specialization" class="form-control" placeholder="e.g. Dermatology, Neurology" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="doc_qual">Qualification</label>
                    <input type="text" id="doc_qual" name="qualification" class="form-control" value="MBBS, MD">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="doc_fee">Consultation Fee (<?= CURRENCY_SYMBOL ?>)</label>
                    <input type="number" step="50" id="doc_fee" name="consultation_fee" class="form-control" value="500" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="doc_mins">Avg. Consultation Mins</label>
                    <input type="number" min="5" max="60" id="doc_mins" name="avg_consultation_mins" class="form-control" value="15" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="doc_bio">Bio / Qualifications</label>
                <textarea id="doc_bio" name="bio" class="form-control" rows="2" placeholder="Brief professional description..."></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeAddDoctorModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check-circle"></i> Create Doctor Account
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddDoctorModal() {
    document.getElementById('addDoctorModal').classList.add('active');
}
function closeAddDoctorModal() {
    document.getElementById('addDoctorModal').classList.remove('active');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
