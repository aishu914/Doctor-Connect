# DoctorConnect - Token-Based Online Doctor Consultation Platform

A production-ready telemedicine platform built with PHP 8, MySQL/MariaDB, HTML5, CSS3, and vanilla JavaScript.

---

## 1. System Architecture & Requirement Analysis

### 1.1 Core Workflow
1. **Patient Registration & Discovery**: Patients sign up, explore doctors filtered by specialty, and inspect real-time working schedules and fees.
2. **Atomic Token Booking**: Patients select an available date. The system atomically locks the queue record, allocates the next sequential race-safe token number, and calculates an estimated consultation time.
3. **Live Queue Synchronization**: Both patient and clinic reception see the real-time queue board polling every 3 seconds (`<= 3 seconds` response time). When a token is called, an audio chime sounds and browser notifications alert the patient.
4. **Conditional Video Consultation**: Video links (Jitsi Meet) activate **only when the patient's token is actively being served or is next in line**. This prevents unauthorized early entries or room congestion.
5. **Consultation & Prescription**: Doctors manage their daily queue from a dedicated cockpit, conduct Jitsi video consultations, record clinical impressions, and generate printable digital prescriptions with medication schedules.

---

## 2. Race Safety & Concurrency Engine

Token duplication during high-concurrency booking is eliminated through a **3-tier defense architecture**:

1. **Physical Database Constraint**:
   ```sql
   UNIQUE KEY unique_token_per_doctor_day (doctor_id, appointment_date, token_number)
   ```
   Enforced at the InnoDB engine level; duplicate token numbers for the same doctor and date cannot physically be committed.
2. **Pessimistic Row/Gap Locking (`FOR UPDATE`)**:
   ```sql
   SELECT COALESCE(MAX(token_number), 0) AS max_token
   FROM appointments
   WHERE doctor_id = ? AND appointment_date = ?
   FOR UPDATE;
   ```
   Within an active transaction (`beginTransaction()`), concurrent threads queue up sequentially on the locked rows/gap.
3. **Exponential Backoff & Retry Loop**:
   If an edge-case collision (MySQL Error 1062) or deadlock (MySQL Error 1213) occurs under high traffic, the transaction is automatically rolled back, waits with randomized jitter (`usleep(10000 - 50000)`), and retries up to 5 times transparently.

---

## 3. Technology Stack & Module Map

| Layer | Technology | Key Responsibility |
|---|---|---|
| **Backend** | PHP 8.1+ | PDO prepared statements, session security, CSRF protection, token generator |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ | InnoDB engine, foreign keys, unique composite indexes, transactions |
| **Frontend** | HTML5, CSS3, Vanilla JS | Responsive mobile-first UI, Web Audio API chime, AJAX queue polling |
| **Video Telehealth** | Jitsi Meet (`meet.jit.si`) | Free, encrypted, zero-installation video consultation rooms |

### Directory Structure
```
Doctor connect/
├── config/
│   ├── database.php            # PDO Singleton connection
│   └── config.php              # Global session security, constants & base URL
├── database/
│   ├── schema.sql              # Clean DDL with tables, indexes, constraints
│   └── seed.php                # Database creation & sample data seeder
├── includes/
│   ├── auth.php                # Authentication & role-based route guards
│   ├── csrf.php                # Anti-CSRF token verification
│   ├── functions.php           # Sanitization, flash alerts, meeting link rules
│   ├── token_manager.php       # Atomic race-safe token & queue engine
│   ├── header.php              # Responsive navigation & user state
│   └── footer.php              # Scripts & layout footer
├── assets/
│   ├── css/style.css           # Healthcare theme, responsive cards & print CSS
│   └── js/
│       ├── live_queue.js       # Real-time 3s queue poller & turn sound synthesizer
│       └── main.js             # Slot calculator & dynamic form handlers
├── api/
│   ├── queue_status.php        # JSON endpoint for live queue board
│   ├── get_slots.php           # Dynamic availability & token estimator
│   └── call_token.php          # Doctor & reception queue advancement
├── patient/
│   ├── dashboard.php           # Active token cards, history, cancellation
│   ├── book.php                # Doctor selection & atomic token booking
│   ├── queue.php               # Live queue board & conditional video link
│   ├── prescription.php        # Printable digital prescription (Rx)
│   └── profile.php             # Patient profile & contact updates
├── doctor/
│   ├── dashboard.php           # Daily metrics & session overview
│   ├── queue_console.php       # Doctor cockpit: call next, video call, prescribe
│   ├── schedule.php            # Weekly consultation availability & fees
│   └── history.php             # Search past patient consultations
├── admin/
│   ├── dashboard.php           # Clinic overview & daily statistics
│   ├── reception.php           # Reception console & walk-in token issuance
│   ├── doctors.php             # Add doctors & assign specialties
│   ├── reports.php             # Date/doctor analytics with CSV export
│   └── settings.php            # Clinic info, policies & Jitsi config
├── video/
│   └── room.php                # Secure Jitsi Meet video consultation frame
├── index.php                   # Public landing & doctor directory
├── login.php                   # Unified login with 1-click demo buttons
├── register.php                # Patient registration with validation
└── logout.php                  # Session clearance
```

---

## 4. Quick Setup & Run Instructions

### Option A: Using XAMPP
1. Ensure Apache and MySQL are running in your XAMPP Control Panel.
2. Put or link the `Doctor connect` directory inside `C:\xampp\htdocs\`.
3. Open your browser and run the automated seeder script:
   ```
   http://localhost/Doctor connect/database/seed.php
   ```
4. Access the platform:
   ```
   http://localhost/Doctor connect/index.php
   ```

### Option B: Using PHP Built-In Web Server
1. Ensure MySQL is running on `127.0.0.1:3306`.
2. Run the database seed script:
   ```powershell
   & "C:\xampp\php\php.exe" database/seed.php
   ```
3. Start the built-in development server from the project directory:
   ```powershell
   & "C:\xampp\php\php.exe" -S 127.0.0.1:8000
   ```
4. Open `http://127.0.0.1:8000` in your browser.

---

## 5. Demo Accounts

The database seeder automatically creates pre-configured test accounts for all roles:

| Role | Email | Password | Description |
|---|---|---|---|
| **Admin** | `admin@doctorconnect.com` | `admin123` | Full clinic management, reception desk, reports |
| **Doctor** | `dr.smith@doctorconnect.com` | `doctor123` | Cardiologist with live queue console |
| **Doctor** | `dr.sarah@doctorconnect.com` | `doctor123` | General Physician |
| **Doctor** | `dr.emily@doctorconnect.com` | `doctor123` | Pediatrician |
| **Patient** | `john.doe@example.com` | `patient123` | Patient with active Token #1 for today |
| **Patient** | `alice@example.com` | `patient123` | Patient with Token #2 for today |

*(The login page features 1-click buttons to auto-populate any of these accounts.)*

---

## 6. Key Design Decisions

1. **Slots are dynamically derived, not statically stored**:
   Available slots and estimated meeting times are computed on the fly from `doctor_schedules.start_time + (token_number - 1) * avg_consultation_mins`. This avoids bloated pre-generated slot records.
2. **Conditional Video Link Activation**:
   A patient can only access `video/room.php` if their token is actively marked as `serving` or if they are immediately `next` in line.
3. **No-Show Handling**:
   Doctors and receptionists can flag no-shows (`no_show` status), which logs the audit trail in `queue_logs` and instantly calls the next patient.
4. **Cancellation Policy**:
   Patients cannot cancel tokens within 2 hours of the estimated appointment time.
