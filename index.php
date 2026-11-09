<?php
/**
 * Landing Page - Doctor Connect
 */

require_once __DIR__ . '/config/config.php';

$pdo = Database::getConnection();

// Fetch active doctors and their general schedules
$docQuery = "
    SELECT d.id, u.name, d.specialization, d.qualification, d.consultation_fee, d.avg_consultation_mins, d.bio
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.is_active = 1
    ORDER BY u.name ASC
";
$doctors = $pdo->query($docQuery)->fetchAll();

$pageTitle = "Online Token & Telemedicine Consultation";
require_once __DIR__ . '/includes/header.php';
?>

<!-- HERO SECTION -->
<div style="background: linear-gradient(135deg, #0369a1 0%, #0f766e 100%); color: #ffffff; border-radius: var(--radius-lg); padding: 3.5rem 2rem; margin-bottom: 2.5rem; text-align: center; box-shadow: var(--shadow-lg);">
    <div style="display: inline-block; background: rgba(255,255,255,0.15); padding: 0.35rem 1.25rem; border-radius: 9999px; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1.25rem;">
        ⚡ Race-Safe Atomic Token Management & Video Telehealth
    </div>
    <h1 style="font-size: 2.75rem; font-weight: 800; color: #ffffff; margin-bottom: 1rem; letter-spacing: -1px;">
        Skip The Waiting Room.<br><span style="color: #67e8f9;">Consult From Home.</span>
    </h1>
    <p style="font-size: 1.15rem; max-width: 680px; margin: 0 auto 2rem; color: #e0f2fe; line-height: 1.6;">
        Book an online token, track the doctor's live queue in real-time, and join the video consultation when your turn is called.
    </p>
    <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
        <a href="<?= BASE_URL ?>/patient/book.php" class="btn btn-warning btn-lg">
            <i class="fas fa-ticket-alt"></i> Book A Token Now
        </a>
        <a href="<?= BASE_URL ?>/patient/queue.php" class="btn btn-secondary btn-lg" style="background: rgba(255,255,255,0.2); color:#fff; border-color: rgba(255,255,255,0.3);">
            <i class="fas fa-tv"></i> View Live Queue Board
        </a>
    </div>
</div>

<!-- 4-STEP WORKFLOW -->
<div style="margin-bottom: 3rem;">
    <div style="text-align: center; margin-bottom: 2rem;">
        <h2 style="font-size: 1.85rem;">How DoctorConnect Works</h2>
        <p style="color: var(--text-muted);">Simple, transparent, zero-crowd medical consultations</p>
    </div>

    <div class="grid-4">
        <div class="card" style="text-align: center; border-top: 4px solid var(--primary);">
            <div style="width: 50px; height: 50px; background: var(--primary-light); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.25rem; font-weight: 800;">
                1
            </div>
            <h4 style="margin-bottom: 0.5rem;">Select & Book</h4>
            <p style="font-size: 0.875rem; color: var(--text-muted);">
                Choose your specialist and appointment date. See open slots dynamically.
            </p>
        </div>

        <div class="card" style="text-align: center; border-top: 4px solid var(--accent);">
            <div style="width: 50px; height: 50px; background: #ede9fe; color: var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.25rem; font-weight: 800;">
                2
            </div>
            <h4 style="margin-bottom: 0.5rem;">Get Token</h4>
            <p style="font-size: 0.875rem; color: var(--text-muted);">
                Instant atomic race-safe token number generated with estimated consultation time.
            </p>
        </div>

        <div class="card" style="text-align: center; border-top: 4px solid var(--warning);">
            <div style="width: 50px; height: 50px; background: var(--warning-light); color: var(--warning); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.25rem; font-weight: 800;">
                3
            </div>
            <h4 style="margin-bottom: 0.5rem;">Track Live Queue</h4>
            <p style="font-size: 0.875rem; color: var(--text-muted);">
                Watch queue progression in real-time. Sound chime alerts you when your turn arrives.
            </p>
        </div>

        <div class="card" style="text-align: center; border-top: 4px solid var(--success);">
            <div style="width: 50px; height: 50px; background: var(--success-light); color: var(--success); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.25rem; font-weight: 800;">
                4
            </div>
            <h4 style="margin-bottom: 0.5rem;">Video Consultation</h4>
            <p style="font-size: 0.875rem; color: var(--text-muted);">
                Instant Jitsi video call link activates when called. Get digital prescriptions.
            </p>
        </div>
    </div>
</div>

<!-- SPECIALIST DOCTORS LIST -->
<div style="margin-bottom: 3rem;">
    <div class="page-header">
        <div>
            <h2 class="page-title">Meet Our Medical Specialists</h2>
            <p class="page-subtitle">Board-certified doctors available for online token appointments</p>
        </div>
    </div>

    <div class="grid-3">
        <?php foreach ($doctors as $doc): ?>
            <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.85rem; margin-bottom: 1rem;">
                        <div style="width: 55px; height: 55px; background: var(--primary-light); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <div>
                            <h3 style="font-size: 1.15rem;"><?= htmlspecialchars($doc['name']) ?></h3>
                            <span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:600;">
                                <?= htmlspecialchars($doc['specialization']) ?>
                            </span>
                        </div>
                    </div>

                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem; line-height: 1.5;">
                        <?= htmlspecialchars($doc['bio'] ?: 'Experienced specialist providing high quality telehealth consultations.') ?>
                    </p>

                    <div style="background: #f8fafc; border-radius: var(--radius-sm); padding: 0.75rem; margin-bottom: 1.25rem; font-size: 0.85rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                            <span style="color: var(--text-muted);">Qualification:</span>
                            <strong><?= htmlspecialchars($doc['qualification']) ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                            <span style="color: var(--text-muted);">Consultation Fee:</span>
                            <strong style="color: var(--secondary);"><?= CURRENCY_SYMBOL ?><?= number_format($doc['consultation_fee'], 2) ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">Avg. Session:</span>
                            <strong><?= (int)$doc['avg_consultation_mins'] ?> mins</strong>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <a href="<?= BASE_URL ?>/patient/book.php?doctor_id=<?= $doc['id'] ?>" class="btn btn-primary btn-sm" style="flex:1;">
                        <i class="fas fa-calendar-check"></i> Book Token
                    </a>
                    <a href="<?= BASE_URL ?>/patient/queue.php?doctor_id=<?= $doc['id'] ?>" class="btn btn-secondary btn-sm" title="View Live Queue">
                        <i class="fas fa-users"></i> Queue
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
