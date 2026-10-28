<?php
declare(strict_types=1);

$allEvents = $allEvents ?? [];
$calSelectedDate = trim((string)($_GET['sel_date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $calSelectedDate)) {
    $calSelectedDate = date('Y-m-d');
}
$calTimestamp = strtotime($calSelectedDate);
$initialCalYear = (int)date('Y', $calTimestamp);
$initialCalMonth = (int)date('n', $calTimestamp) - 1;
?>
<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
lucide.createIcons();

// Fullscreen Toggle
function toggleFullScreen() {
    const fsIcon = document.getElementById('fsIcon');
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {});
        if (fsIcon) fsIcon.setAttribute('data-lucide', 'minimize-2');
    } else {
        if (document.exitFullscreen) document.exitFullscreen();
        if (fsIcon) fsIcon.setAttribute('data-lucide', 'maximize-2');
    }
    lucide.createIcons();
}

// Global Events Array for Calendar & Widgets
const allEventsData = <?=json_encode($allEvents)?>;

// Calendar State
let calYear = <?=$initialCalYear?>;
let calMonth = <?=$initialCalMonth?>;
let selectedDateStr = '<?=$calSelectedDate?>';

const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

function renderCalendar() {
    const monthTitle = document.getElementById('calMonthTitle');
    const daysGrid = document.getElementById('calDaysGrid');
    if (!monthTitle || !daysGrid) return;

    monthTitle.textContent = `${monthNames[calMonth]} ${calYear}`;
    daysGrid.innerHTML = '';

    // Calculate first day of month (Monday = 1 ... Sunday = 7)
    const firstDay = new Date(calYear, calMonth, 1);
    let startDayOfWeek = firstDay.getDay(); // 0 is Sunday, 1 is Monday
    startDayOfWeek = (startDayOfWeek === 0) ? 7 : startDayOfWeek;

    // Days in current month
    const totalDaysInMonth = new Date(calYear, calMonth + 1, 0).getDate();

    // Leading empty slots
    for (let b = 1; b < startDayOfWeek; b++) {
        const blank = document.createElement('div');
        daysGrid.appendChild(blank);
    }

    // Days 1 to totalDaysInMonth
    for (let d = 1; d <= totalDaysInMonth; d++) {
        const mStr = String(calMonth + 1).padStart(2, '0');
        const dStr = String(d).padStart(2, '0');
        const dateKey = `${calYear}-${mStr}-${dStr}`;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'calendar-day-btn';
        btn.textContent = d;

        // Has event check
        const hasEvent = allEventsData.some(ev => ev.event_date === dateKey);
        if (hasEvent) btn.classList.add('has-event');

        // Selected active check
        if (dateKey === selectedDateStr) {
            btn.classList.add('active');
        }

        btn.onclick = function () {
            selectedDateStr = dateKey;
            renderCalendar();
            renderScheduleList(dateKey);
        };

        daysGrid.appendChild(btn);
    }
}

function prevCalMonth() {
    calMonth--;
    if (calMonth < 0) {
        calMonth = 11;
        calYear--;
    }
    renderCalendar();
}

function nextCalMonth() {
    calMonth++;
    if (calMonth > 11) {
        calMonth = 0;
        calYear++;
    }
    renderCalendar();
}

function renderScheduleList(dateKey) {
    const container = document.getElementById('scheduleListContainer');
    if (!container) return;

    const matchingEvents = allEventsData.filter(ev => ev.event_date === dateKey);

    if (matchingEvents.length > 0) {
        let html = '';
        matchingEvents.forEach(ev => {
            const dayNum = ev.event_date.split('-')[2];
            html += `
                <div class="schedule-pill-item" onclick="openEventModal(${ev.event_id}, '${escapeHtml(ev.title)}', '${ev.event_date}', '${escapeHtml(ev.event_time)}', '${escapeHtml(ev.location)}', '${escapeHtml(ev.status)}')">
                    <div class="schedule-date-num">${dayNum}</div>
                    <div class="overflow-hidden">
                        <div class="schedule-event-title">${escapeHtml(ev.title)}</div>
                        <div class="schedule-event-time">${escapeHtml(ev.event_time)} &bull; ${escapeHtml(ev.location)}</div>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    } else {
        // Fallback: Show next upcoming distribution dates so area is never empty
        const nextEvents = allEventsData.filter(ev => ev.event_date >= dateKey).slice(0, 3);
        if (nextEvents.length === 0 && allEventsData.length > 0) {
            nextEvents.push(...allEventsData.slice(0, 3));
        }

        let html = `
            <div class="p-2 text-center rounded-4 mb-1" style="background: rgba(255,255,255,0.7); font-size: 11px;">
                No distributions on <strong>${dateKey}</strong>.
                <button type="button" class="btn btn-link p-0 text-decoration-none fw-bold small ms-1" style="color:var(--panel-plum);font-size:11px;" onclick="scheduleForDate('${dateKey}')">+ Schedule</button>
            </div>
        `;

        nextEvents.forEach(ev => {
            const dayNum = ev.event_date.split('-')[2];
            html += `
                <div class="schedule-pill-item" onclick="openEventModal(${ev.event_id}, '${escapeHtml(ev.title)}', '${ev.event_date}', '${escapeHtml(ev.event_time)}', '${escapeHtml(ev.location)}', '${escapeHtml(ev.status)}')">
                    <div class="schedule-date-num">${dayNum}</div>
                    <div class="overflow-hidden">
                        <div class="schedule-event-title">${escapeHtml(ev.title)}</div>
                        <div class="schedule-event-time">${escapeHtml(ev.event_time)} &bull; ${escapeHtml(ev.location)}</div>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    }
    lucide.createIcons();
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function scheduleForDate(dateStr) {
    const modalEl = document.getElementById('modalAddEvent');
    if (modalEl) {
        modalEl.querySelector('#ev_date').value = dateStr;
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
    }
}

function openEventModal(id, title, date, time, location, status) {
    const modalEl = document.getElementById('modalViewEvent');
    if (modalEl) {
        modalEl.querySelector('#vEvTitle').textContent = title;
        modalEl.querySelector('#vEvDateTime').textContent = `${date} at ${time}`;
        modalEl.querySelector('#vEvLocation').textContent = `Location: ${location}`;
        modalEl.querySelector('#vEvStatusBadge').textContent = status;
        modalEl.querySelector('#vEvIdComplete').value = id;
        modalEl.querySelector('#vEvIdDelete').value = id;
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
    }
}

function confirmDeleteDonation(id, name) {
    const modalEl = document.getElementById('modalDeleteDonation');
    if (modalEl) {
        modalEl.querySelector('#del_don_id').value = id;
        modalEl.querySelector('#del_don_name').textContent = name;
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
    }
}

function viewDonorDetails(name, phone, addr) {
    const modalEl = document.getElementById('modalDonorQuick');
    if (modalEl) {
        modalEl.querySelector('#dqName').textContent = name;
        modalEl.querySelector('#dqPhone').textContent = phone || 'No phone provided';
        modalEl.querySelector('#dqAddr').textContent = addr || 'Central Depot';
        modalEl.querySelector('#dqTelBtn').href = `tel:${phone}`;
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    lucide.createIcons();

    // Initialize interactive calendar and distribution schedule
    renderCalendar();
    renderScheduleList(selectedDateStr);

    // Populate Edit Donation Modal
    const editDonModal = document.getElementById('modalEditDonation');
    if (editDonModal) {
        editDonModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            editDonModal.querySelector('#edit_don_id').value = btn.getAttribute('data-id');
            editDonModal.querySelector('#edit_don_name').value = btn.getAttribute('data-name');
            editDonModal.querySelector('#edit_don_qty').value = btn.getAttribute('data-qty');
            editDonModal.querySelector('#edit_don_cat').value = btn.getAttribute('data-cat');
            editDonModal.querySelector('#edit_don_donor').value = btn.getAttribute('data-donor');
            editDonModal.querySelector('#edit_don_date').value = btn.getAttribute('data-date');
            editDonModal.querySelector('#edit_don_status').value = btn.getAttribute('data-status') || 'Received';
        });
    }

    // Automatic Filtration: Debounce search keyword input
    const searchInput = document.getElementById('search_input');
    const filterForm = document.getElementById('donationsFilterForm');
    if (searchInput && filterForm) {
        let debounceTimer;
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                filterForm.submit();
            }, 350);
        });

        // If search was performed, restore focus to search input
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('search') && urlParams.get('search') !== '') {
            searchInput.focus();
            const valLen = searchInput.value.length;
            searchInput.setSelectionRange(valLen, valLen);
        }
    }

    // Populate Delete Donation Modal
    const delDonModal = document.getElementById('modalDeleteDonation');
    if (delDonModal) {
        delDonModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget ? (e.relatedTarget.closest('[data-bs-target="#modalDeleteDonation"]') || e.relatedTarget) : null;
            if (btn && btn.getAttribute('data-id')) {
                delDonModal.querySelector('#del_don_id').value = btn.getAttribute('data-id');
                delDonModal.querySelector('#del_don_name').textContent = btn.getAttribute('data-name');
            }
        });
    }

    // Populate Edit Category Modal
    const editCatModal = document.getElementById('modalEditCategory');
    if (editCatModal) {
        editCatModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            editCatModal.querySelector('#ec_id').value = btn.getAttribute('data-id');
            editCatModal.querySelector('#ec_name').value = btn.getAttribute('data-name');
            editCatModal.querySelector('#ec_desc').value = btn.getAttribute('data-desc');
            editCatModal.querySelector('#ec_icon').value = btn.getAttribute('data-icon') || 'package';
            editCatModal.querySelector('#ec_color').value = btn.getAttribute('data-color') || '#0284c7';
        });
    }

    // Populate Delete Category Modal
    const delCatModal = document.getElementById('modalDeleteCategory');
    if (delCatModal) {
        delCatModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            delCatModal.querySelector('#del_cat_id').value = btn.getAttribute('data-id');
            delCatModal.querySelector('#del_cat_name').textContent = btn.getAttribute('data-name');
        });
    }

    // Populate Edit Donor Modal
    const editDnModal = document.getElementById('modalEditDonor');
    if (editDnModal) {
        editDnModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            editDnModal.querySelector('#edn_id').value = btn.getAttribute('data-id');
            editDnModal.querySelector('#edn_person').value = btn.getAttribute('data-person');
            editDnModal.querySelector('#edn_phone').value = btn.getAttribute('data-phone');
            editDnModal.querySelector('#edn_addr').value = btn.getAttribute('data-address');
        });
    }

    // Populate Delete Donor Modal
    const delDnModal = document.getElementById('modalDeleteDonor');
    if (delDnModal) {
        delDnModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            delDnModal.querySelector('#del_dn_id').value = btn.getAttribute('data-id');
            delDnModal.querySelector('#del_dn_name').textContent = btn.getAttribute('data-person');
        });
    }
});
</script>
</body>
</html>
