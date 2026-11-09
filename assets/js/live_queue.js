/**
 * Live Queue Engine
 * Polls backend API every 3s, updates DOM, triggers audio/visual cues when turn arrives.
 */

class LiveQueue {
    constructor(options) {
        this.doctorId = options.doctorId;
        this.date = options.date;
        this.userTokenNumber = options.userTokenNumber || null;
        this.pollInterval = options.pollInterval || 3000;
        this.apiUrl = options.apiUrl || '../api/queue_status.php';
        this.isDoctorOrAdmin = options.isDoctorOrAdmin || false;
        
        this.intervalId = null;
        this.lastServingToken = null;
        this.hasAlertedTurn = false;

        this.init();
    }

    init() {
        this.fetchStatus();
        this.intervalId = setInterval(() => this.fetchStatus(), this.pollInterval);
    }

    destroy() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
        }
    }

    async fetchStatus() {
        try {
            const url = `${this.apiUrl}?doctor_id=${encodeURIComponent(this.doctorId)}&date=${encodeURIComponent(this.date)}`;
            const response = await fetch(url);
            if (!response.ok) return;

            const data = await response.json();
            if (data.success) {
                this.updateUI(data.data);
            }
        } catch (err) {
            console.error("Queue poll error:", err);
        }
    }

    updateUI(queue) {
        // Update Serving Token Display
        const servingElem = document.getElementById('currentServingToken');
        if (servingElem) {
            const currentToken = queue.current_serving_token || 0;
            servingElem.textContent = currentToken > 0 ? `#${currentToken}` : 'None';

            // Check if patient's turn is called
            if (this.userTokenNumber && this.userTokenNumber === currentToken) {
                if (!this.hasAlertedTurn) {
                    this.triggerTurnAlert(currentToken);
                    this.hasAlertedTurn = true;
                }
            }
        }

        // Update Next Token Display
        const nextElem = document.getElementById('nextTokenNumber');
        if (nextElem) {
            nextElem.textContent = queue.next_token ? `#${queue.next_token}` : 'None';
        }

        // Update Waiting Count
        const waitingElem = document.getElementById('waitingCount');
        if (waitingElem) {
            waitingElem.textContent = queue.waiting_count;
        }

        // Update Completed Count
        const completedElem = document.getElementById('completedCount');
        if (completedElem) {
            completedElem.textContent = queue.completed_count;
        }

        // Update Meeting Button Visibility (for Patient)
        const meetContainer = document.getElementById('patientMeetingAction');
        if (meetContainer && this.userTokenNumber) {
            const isServing = (queue.current_serving_token === this.userTokenNumber);
            const isNext = (queue.next_token === this.userTokenNumber);

            if (isServing) {
                meetContainer.innerHTML = `
                    <div class="alert alert-success" style="animation: pulse 1.5s infinite;">
                        <i class="fas fa-video fa-lg"></i>
                        <div>
                            <strong>It's your turn!</strong> Doctor is waiting for you in the consultation room.
                        </div>
                    </div>
                    <a href="${queue.user_meeting_link || ('../video/room.php?id=' + queue.user_appointment_id)}" 
                       target="_blank" class="btn btn-success btn-lg" style="width: 100%;">
                        <i class="fas fa-video"></i> Join Consultation Now
                    </a>
                `;
            } else if (isNext) {
                meetContainer.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-clock"></i>
                        <div>
                            <strong>You are NEXT in line!</strong> Please test your camera/mic and prepare to join.
                        </div>
                    </div>
                    <a href="${queue.user_meeting_link || ('../video/room.php?id=' + queue.user_appointment_id)}" 
                       target="_blank" class="btn btn-outline-primary btn-lg" style="width: 100%;">
                        <i class="fas fa-door-open"></i> Enter Waiting Room
                    </a>
                `;
            }
        }

        // Update Waiting List Render
        const listContainer = document.getElementById('waitingTokensList');
        if (listContainer && queue.waiting_tokens) {
            if (queue.waiting_tokens.length === 0) {
                listContainer.innerHTML = `<div style="text-align:center; padding: 2rem; color: #64748b;">No patients currently waiting.</div>`;
            } else {
                let html = '';
                queue.waiting_tokens.forEach(t => {
                    const isMine = (this.userTokenNumber && t.token_number === this.userTokenNumber);
                    html += `
                        <div class="token-queue-item ${isMine ? 'is-user-token' : ''}">
                            <div class="token-queue-badge">
                                <span>Token #${t.token_number}</span>
                                ${isMine ? '<span class="badge badge-primary" style="background:#0284c7;color:#fff;">YOU</span>' : ''}
                            </div>
                            <div style="font-size:0.875rem; color:#64748b;">
                                <i class="far fa-clock"></i> Est: ${this.formatTime(t.estimated_time)}
                            </div>
                        </div>
                    `;
                });
                listContainer.innerHTML = html;
            }
        }
    }

    formatTime(timeStr) {
        if (!timeStr) return 'TBD';
        const d = new Date(timeStr);
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    triggerTurnAlert(tokenNum) {
        // 1. Play synthesized bell chime using Web Audio API
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            osc.frequency.setValueAtTime(880, ctx.currentTime + 0.2); // A5
            gain.gain.setValueAtTime(0.2, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 1.2);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 1.2);
        } catch (e) {
            // Audio context may require prior user interaction
        }

        // 2. Change tab title
        document.title = `🔔 [YOUR TURN!] Token #${tokenNum} - DoctorConnect`;

        // 3. Desktop browser notification if permitted
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('DoctorConnect: It is Your Turn!', {
                body: `Token #${tokenNum} is now being called by the doctor. Click to join consultation.`,
                icon: '../assets/images/medical-icon.png'
            });
        }
    }
}
window.LiveQueue = LiveQueue;
