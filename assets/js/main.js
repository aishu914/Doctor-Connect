/**
 * Main Frontend Interactions & Slot Calculators
 */

document.addEventListener('DOMContentLoaded', () => {
    // Request notification permission on first user interaction
    if ('Notification' in window && Notification.permission === 'default') {
        const askNotifBtn = document.getElementById('enableNotifsBtn');
        if (askNotifBtn) {
            askNotifBtn.addEventListener('click', () => {
                Notification.requestPermission();
                askNotifBtn.style.display = 'none';
            });
        }
    }

    // Auto-dismiss flash messages after 6 seconds
    setTimeout(() => {
        document.querySelectorAll('.flash-messages-container .alert').forEach(al => {
            al.style.transition = 'opacity 0.5s ease';
            al.style.opacity = '0';
            setTimeout(() => al.remove(), 500);
        });
    }, 6000);

    // Dynamic slot information for booking form
    const doctorSelect = document.getElementById('bookingDoctorSelect');
    const dateInput = document.getElementById('bookingDateInput');
    const slotInfoBox = document.getElementById('slotInfoBox');

    async function loadDoctorAvailability() {
        if (!doctorSelect || !dateInput || !slotInfoBox) return;
        const doctorId = doctorSelect.value;
        const date = dateInput.value;

        if (!doctorId || !date) {
            slotInfoBox.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle"></i> Select doctor and date to view queue & timing details.</div>';
            return;
        }

        slotInfoBox.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Checking doctor schedule & existing tokens...</div>';

        try {
            const res = await fetch(`../api/get_slots.php?doctor_id=${encodeURIComponent(doctorId)}&date=${encodeURIComponent(date)}`);
            const data = await res.json();

            if (!data.success) {
                slotInfoBox.innerHTML = `<div class="alert alert-danger"><i class="fas fa-times-circle"></i> ${data.message}</div>`;
                const submitBtn = document.getElementById('bookSubmitBtn');
                if (submitBtn) submitBtn.disabled = true;
                return;
            }

            const info = data.data;
            const submitBtn = document.getElementById('bookSubmitBtn');
            if (submitBtn) submitBtn.disabled = false;

            slotInfoBox.innerHTML = `
                <div class="card" style="background: #f0fdf4; border-color: #bbf7d0; margin-bottom: 0;">
                    <h4 style="color: #166534; margin-bottom: 0.5rem;"><i class="fas fa-check-circle"></i> Schedule Available</h4>
                    <div style="font-size: 0.9rem; color: #15803d; display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                        <div><strong>Working Hours:</strong> ${info.formatted_hours}</div>
                        <div><strong>Avg Consultation:</strong> ${info.avg_mins} mins</div>
                        <div><strong>Tokens Booked:</strong> ${info.booked_tokens} / ${info.max_tokens}</div>
                        <div><strong>Your Token Will Be:</strong> <span class="badge" style="background:#166534;color:#fff;">#${info.next_token_number}</span></div>
                    </div>
                    <div style="margin-top: 0.75rem; font-size: 0.95rem; color: #166534;">
                        <i class="far fa-clock"></i> <strong>Estimated Time for Token #${info.next_token_number}:</strong> 
                        <span style="font-size: 1.1rem; font-weight: 700;">${info.estimated_time_formatted}</span>
                    </div>
                </div>
            `;
        } catch (e) {
            slotInfoBox.innerHTML = `<div class="alert alert-danger">Error checking slot availability.</div>`;
        }
    }

    if (doctorSelect && dateInput) {
        doctorSelect.addEventListener('change', loadDoctorAvailability);
        dateInput.addEventListener('change', loadDoctorAvailability);
    }
});
