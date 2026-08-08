const assignmentPageData = window.assignmentPageData || {};
const selectedSetup = assignmentPageData.selectedSetup || {};
const currentAssignment = assignmentPageData.currentAssignment || null;
const technicians = assignmentPageData.technicians || [];
const techSchedules = assignmentPageData.techSchedules || [];
const initialTechId = assignmentPageData.initialTechId || '';
const initialInstallDate = assignmentPageData.initialInstallDate || '';
const initialInstallTime = assignmentPageData.initialInstallTime || '';
const initialInstallEndTime = assignmentPageData.initialInstallEndTime || '';
const isEditMode = !!(currentAssignment && currentAssignment.assign_id);
const assignmentPermissions = assignmentPageData.assignmentPermissions || {};
const serverToday = assignmentPageData.serverToday || '';
let selectedTechId = initialTechId || '';
let currentQueueTechId = initialTechId || '';
let selectedInstallDate = initialInstallDate || '';
let calendarCursor = selectedInstallDate ? dateFromKey(selectedInstallDate) : new Date();
calendarCursor.setDate(1);

const thaiMonths = [
  'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
  'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
];
const weekdayNames = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];
const availableTimeSlots = [
  { key: 'morning', start: '09:00', end: '12:00', title: 'ช่วงเช้า', detail: '09:00 - 12:00' },
  { key: 'afternoon', start: '13:00', end: '15:00', title: 'ช่วงบ่าย', detail: '13:00 - 15:00' },
  { key: 'evening', start: '16:00', end: '18:00', title: 'ช่วงเย็น', detail: '16:00 - 18:00' }
];
let selectedInstallTime = initialInstallTime || '';
let selectedInstallEndTime = initialInstallEndTime || '';
let selectedTimeSlot = availableTimeSlots.find(slot => slot.start === selectedInstallTime && slot.end === selectedInstallEndTime)?.key || '';

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function normalizeText(value) {
  return String(value || '').toLowerCase().trim();
}

function pad2(value) {
  return String(value).padStart(2, '0');
}

function toDateKey(dateObj) {
  return `${dateObj.getFullYear()}-${pad2(dateObj.getMonth() + 1)}-${pad2(dateObj.getDate())}`;
}

function dateFromKey(value) {
  const parts = String(value || '').split('-').map(Number);
  if (parts.length !== 3 || parts.some(Number.isNaN)) return new Date();
  return new Date(parts[0], parts[1] - 1, parts[2]);
}

function formatDate(value) {
  if (!value) return '-';
  const parts = String(value).split('-');
  if (parts.length !== 3) return value;
  return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

function normalizeTime(value) {
  const text = String(value || '').trim();
  if (/^\d{2}:\d{2}$/.test(text)) return text;
  if (/^\d{2}:\d{2}:\d{2}$/.test(text)) return text.slice(0, 5);
  return '';
}

function timeToMinutes(value) {
  const time = normalizeTime(value);
  if (!time) return -1;
  const [hour, minute] = time.split(':').map(Number);
  return (hour * 60) + minute;
}

function timeRangeLabel(start, end) {
  const startText = normalizeTime(start);
  const endText = normalizeTime(end);
  if (!startText) return '-';
  return endText ? `${startText} - ${endText} น.` : `${startText} น.`;
}

function rangesOverlap(startA, endA, startB, endB) {
  const aStart = timeToMinutes(startA);
  const aEnd = timeToMinutes(endA);
  const bStart = timeToMinutes(startB);
  const bEnd = timeToMinutes(endB);
  if (aStart < 0 || aEnd <= aStart || bStart < 0 || bEnd <= bStart) return false;
  return aStart < bEnd && aEnd > bStart;
}

function formatMonthLabel(dateObj) {
  return `${thaiMonths[dateObj.getMonth()]} ${dateObj.getFullYear() + 543}`;
}

function isPastDate(dateKey) {
  return dateKey < serverToday;
}

function isWeekend(dateKey) {
  const date = dateFromKey(dateKey);
  const day = date.getDay();
  return day === 0 || day === 6;
}

function isTimeSlotPassed(dateKey, endTime) {
  if (!dateKey || !endTime) return true;
  if (dateKey < serverToday) return true;
  if (dateKey > serverToday) return false;

  const normalizedEnd = normalizeTime(endTime);
  if (!normalizedEnd) return true;

  const [hour, minute] = normalizedEnd.split(':').map(Number);
  const now = new Date();
  const slotEnd = new Date(
    now.getFullYear(),
    now.getMonth(),
    now.getDate(),
    hour,
    minute,
    0,
    0
  );

  return slotEnd <= now;
}

function isTechBaseAvailable(tech) {
  return Number(tech.tech_status || 0) === 0;
}

function isTechSelectable(tech) {
  if (!tech) return false;
  const isCurrentTech = String(tech.tech_id || '') === String(initialTechId || '');
  if (!assignmentPermissions.canChangeTech) return isCurrentTech;
  return isCurrentTech || isTechBaseAvailable(tech);
}

function getTechById(techId) {
  return technicians.find(item => item.tech_id === techId) || null;
}

function techDisplayName(tech) {
  return tech ? (tech.tech_fullname || tech.tech_name || '-') : '-';
}

function customerDisplayName(row) {
  return row.user_name || '-';
}

function getTechSlots(techId) {
  return techSchedules
    .filter(slot => slot.tech_id === techId)
    .sort((a, b) => String(a.assign_install_date || '').localeCompare(String(b.assign_install_date || '')));
}

function getTechSlotsOnDate(techId, dateKey) {
  return techSchedules.filter(slot => slot.tech_id === techId && slot.assign_install_date === dateKey);
}

function isTimeSlotBusy(techId, dateKey, startTime, endTime) {
  return getTechSlotsOnDate(techId, dateKey).some(slot => {
    if (currentAssignment && String(slot.assign_id || '') === String(currentAssignment.assign_id || '')) return false;
    const slotStart = normalizeTime(slot.assign_install_time);
    const slotEnd = normalizeTime(slot.assign_install_end_time) || addMinutesToTime(slotStart, 120);
    return rangesOverlap(startTime, endTime, slotStart, slotEnd);
  });
}

function getDateSlotState(techId, dateKey) {
  const busyCount = availableTimeSlots.filter(slot => isTimeSlotBusy(techId, dateKey, slot.start, slot.end)).length;
  return {
    busyCount,
    isFull: busyCount === availableTimeSlots.length,
  };
}

function updateSaveButton() {
  const saveButton = document.getElementById('saveAssignmentButton');
  if (!saveButton) return;
  saveButton.disabled = assignmentPermissions.readOnly
    || !document.getElementById('tech_id').value
    || !document.getElementById('assign_install_date').value
    || !document.getElementById('assign_time_slot').value;
}

function addMinutesToTime(timeValue, minutesToAdd) {
  const start = timeToMinutes(timeValue);
  if (start < 0) return '';
  const total = start + Number(minutesToAdd || 0);
  return `${pad2(Math.floor(total / 60))}:${pad2(total % 60)}`;
}

function assignmentStatusText(status) {
  const value = String(status ?? '');
  if (value === '1') return 'มอบหมายแล้ว';
  if (value === '2') return 'ช่างรับงานแล้ว';
  if (value === '5') return 'เสร็จสิ้น';
  return '-';
}

function renderTechnicians() {
  const keyword = normalizeText(document.getElementById('techSearch').value);
  const list = document.getElementById('techList');
  const countText = document.getElementById('availableCountText');

  const filtered = technicians.filter(tech => {
    const text = normalizeText([tech.tech_id, tech.tech_name, tech.tech_fullname, tech.tech_phone, tech.tech_email].join(' '));
    return text.includes(keyword);
  });

  const selectableCount = filtered.filter(isTechSelectable).length;
  countText.textContent = `ช่างทั้งหมด ${filtered.length} คน · พร้อมรับงาน ${selectableCount} คน`;

  if (filtered.length === 0) {
    list.innerHTML = '<div class="manager-empty-state">ไม่พบข้อมูลช่าง</div>';
    return;
  }

  list.innerHTML = filtered.map(tech => {
    const active = selectedTechId === tech.tech_id ? ' active' : '';
    const unavailable = !isTechSelectable(tech);
    const disabledClass = unavailable ? ' is-disabled' : '';
    const queueCount = Number(tech.total_queue || getTechSlots(tech.tech_id).length || 0);
    const statusText = !isTechBaseAvailable(tech)
      ? (String(tech.tech_id || '') === String(initialTechId || '') ? 'ไม่พร้อมรับงาน แต่ยังทำงานนี้ได้' : 'ไม่พร้อมรับงาน')
      : 'พร้อมรับงาน';

    return `
      <article class="manager-tech-card${active}${disabledClass}">
        <div class="manager-tech-card-main" ${unavailable ? '' : `onclick="selectTechnician('${escapeHtml(tech.tech_id)}')"`}>
          <div class="tech-avatar">${escapeHtml((techDisplayName(tech) || 'ช').slice(0, 1))}</div>
          <div>
            <strong>${escapeHtml(techDisplayName(tech))}</strong>
            <p>${escapeHtml(tech.tech_phone || '-')} | ${escapeHtml(tech.tech_email || '-')}</p>
            <small>สถานะ: ${escapeHtml(statusText)} | คิวที่ถูกมอบหมาย ${queueCount} คิว</small>
          </div>
        </div>
        <div class="manager-tech-actions">
          <button type="button" class="select" ${unavailable ? 'disabled' : ''} onclick="selectTechnician('${escapeHtml(tech.tech_id)}')">
            ${unavailable ? 'เลือกไม่ได้' : (active ? 'เลือกแล้ว' : 'เลือกช่าง')}
          </button>
        </div>
      </article>
    `;
  }).join('');
}

function selectTechnician(techId) {
  const tech = getTechById(techId);
  if (!tech || !isTechSelectable(tech)) return;

  selectedTechId = techId;
  currentQueueTechId = techId;
  document.getElementById('tech_id').value = techId;

  if (!selectedInstallDate && initialInstallDate) selectedInstallDate = initialInstallDate;
  document.getElementById('assign_install_date').value = selectedInstallDate || '';
  document.getElementById('selectedDateBox').textContent = selectedInstallDate
    ? `วันที่ติดตั้งที่เลือก: ${formatDate(selectedInstallDate)}`
    : 'กรุณาเลือกวันที่ติดตั้งจากปฏิทิน';

  selectedInstallTime = '';
  selectedInstallEndTime = '';
  selectedTimeSlot = '';
  document.getElementById('assign_time_slot').value = '';
  document.getElementById('schedulePanel').classList.add('show');

  renderTechnicians();
  renderSelectedTechSchedule();
  renderCalendar();
  renderTimeSlots();
  updateSaveButton();

  setTimeout(() => document.getElementById('schedulePanel').scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
}

function renderSelectedTechSchedule() {
  const body = document.getElementById('techQueueTableBody');
  const title = document.getElementById('selectedTechScheduleText');
  const count = document.getElementById('techQueueCountText');
  const tech = getTechById(currentQueueTechId);

  if (!tech) {
    body.innerHTML = '<tr><td colspan="6" class="manager-empty-cell">ยังไม่ได้เลือกช่าง</td></tr>';
    title.textContent = 'เลือกช่างก่อน ระบบจะแสดงตารางงานและวันที่เลือกได้';
    count.textContent = 'ยังไม่ได้เลือกช่าง';
    return;
  }

  const todayKey = toDateKey(new Date());
  const displayDate = selectedInstallDate || todayKey;
  const slots = getTechSlotsOnDate(tech.tech_id, displayDate)
    .sort((a, b) => String(a.assign_install_time || '00:00').localeCompare(String(b.assign_install_time || '00:00')));

  const statusText = isTechBaseAvailable(tech) ? 'พร้อมรับงาน' : 'ไม่พร้อมรับงาน';
  title.textContent = `ช่างที่เลือก: ${techDisplayName(tech)} | สถานะ ${statusText} | วันที่ ${formatDate(displayDate)}`;
  count.textContent = `งานในวันนี้ ${slots.length} คิว`;

  if (slots.length === 0) {
    body.innerHTML = `<tr><td colspan="6" class="manager-empty-cell">ยังไม่มีงานที่ได้รับมอบหมายในวันที่ ${formatDate(displayDate)}</td></tr>`;
    return;
  }

  body.innerHTML = slots.map(slot => `
    <tr>
      <td>${escapeHtml(slot.setup_id || '-')}</td>
      <td>${escapeHtml(customerDisplayName(slot))}</td>
      <td>${escapeHtml(slot.pro_name || '-')}</td>
      <td>${escapeHtml(formatDate(slot.assign_install_date))}</td>
      <td><strong>${escapeHtml(timeRangeLabel(slot.assign_install_time, slot.assign_install_end_time))}</strong></td>
      <td><span class="manager-queue-status">${escapeHtml(assignmentStatusText(slot.assign_status))}</span></td>
    </tr>
  `).join('');
}
function changeCalendarMonth(diff) {
  calendarCursor.setMonth(calendarCursor.getMonth() + diff);
  calendarCursor.setDate(1);
  renderCalendar();
}

function renderCalendar() {
  const calendar = document.getElementById('installCalendar');
  const monthText = document.getElementById('calendarMonthText');
  monthText.textContent = formatMonthLabel(calendarCursor);

  const tech = getTechById(currentQueueTechId);
  if (!tech) {
    calendar.innerHTML = '<div class="manager-empty-state calendar-empty">เลือกช่างก่อนเพื่อดูตารางวันว่าง</div>';
    return;
  }

  const canSelectDate = assignmentPermissions.canChangeDate && selectedTechId === currentQueueTechId && isTechSelectable(tech);
  const year = calendarCursor.getFullYear();
  const month = calendarCursor.getMonth();
  const firstDay = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  let html = weekdayNames.map(day => `<div class="calendar-weekday">${day}</div>`).join('');
  for (let i = 0; i < firstDay; i++) html += '<div class="calendar-day empty"></div>';

  for (let day = 1; day <= daysInMonth; day++) {
    const dateObj = new Date(year, month, day);
    const dateKey = `${dateObj.getFullYear()}-${pad2(dateObj.getMonth() + 1)}-${pad2(dateObj.getDate())}`;
    const pastDate = isPastDate(dateKey);
    const weekend = isWeekend(dateKey);
    const slotState = getDateSlotState(currentQueueTechId, dateKey);
    const unavailableByStatus = !isTechSelectable(tech);
    const selected = selectedInstallDate === dateKey && selectedTechId === currentQueueTechId ? ' selected' : '';
    const todayClass = dateKey === serverToday ? ' today' : '';
    const statusClass = (pastDate || weekend)
      ? ' holiday'
      : (slotState.isFull ? ' busy' : (slotState.busyCount > 0 ? ' partial' : (unavailableByStatus ? ' busy' : ' available')));
    const label = pastDate
      ? ''
      : (weekend ? 'วันหยุด' : (slotState.isFull ? 'เต็ม' : (slotState.busyCount > 0 ? 'มีคิว' : (unavailableByStatus ? 'ไม่พร้อมรับงาน' : 'ว่าง'))));
    const disabled = !assignmentPermissions.canChangeDate || pastDate || weekend || slotState.isFull || unavailableByStatus || !canSelectDate ? 'disabled' : '';

    html += `
      <button type="button" class="calendar-day${statusClass}${todayClass}${selected}" ${disabled} onclick="selectInstallDate('${dateKey}')">
        <span>${day}</span>
        <small>${label}</small>
      </button>
    `;
  }

  calendar.innerHTML = html;
}

function selectInstallDate(dateKey) {
  const tech = getTechById(currentQueueTechId);
  if (!assignmentPermissions.canChangeDate || !tech || selectedTechId !== currentQueueTechId || (isPastDate(dateKey) || isWeekend(dateKey)) || getDateSlotState(currentQueueTechId, dateKey).isFull || !isTechSelectable(tech)) {
    return;
  }

  selectedInstallDate = dateKey;
  selectedInstallTime = '';
  selectedInstallEndTime = '';
  selectedTimeSlot = '';
  document.getElementById('assign_install_date').value = dateKey;
  document.getElementById('assign_time_slot').value = '';

  document.getElementById('selectedDateBox').textContent = `วันที่ติดตั้งที่เลือก: ${formatDate(dateKey)}`;

  renderCalendar();
  renderSelectedTechSchedule();
  renderTimeSlots();
  updateSaveButton();
}

function renderTimeSlots() {
  const box = document.getElementById('timeSlots');
  const panel = document.getElementById('timePanel');
  const text = document.getElementById('selectedTimeText');
  if (!box || !panel || !text) return;

  const currentTech = getTechById(currentQueueTechId);
  if (!currentQueueTechId || !selectedInstallDate || selectedTechId !== currentQueueTechId || !isTechSelectable(currentTech)) {
    panel.classList.remove('show');
    box.innerHTML = '';
    text.textContent = 'เลือกวันที่ก่อน แล้วระบบจะแสดงช่วงเวลาที่เลือกได้';
    return;
  }

  panel.classList.add('show');
  text.textContent = selectedInstallTime && selectedInstallEndTime
    ? `เวลาที่เลือก: ${timeRangeLabel(selectedInstallTime, selectedInstallEndTime)}`
    : `เลือกช่วงเวลาสำหรับวันที่ ${formatDate(selectedInstallDate)}`;

  box.innerHTML = availableTimeSlots.map(slot => {
    const busy = isTimeSlotBusy(currentQueueTechId, selectedInstallDate, slot.start, slot.end);
    const passed = isTimeSlotPassed(selectedInstallDate, slot.end);
    const unavailable = busy || passed;
    const active = selectedTimeSlot === slot.key && !unavailable ? ' active' : '';
    const disabled = unavailable ? ' disabled' : '';
    const label = passed
      ? 'หมดเวลา'
      : (busy ? 'ไม่ว่าง' : (active ? '✓ เลือกแล้ว' : 'ว่าง'));

    return `
      <button type="button" class="manager-time-slot${active}${disabled}" ${unavailable ? 'disabled' : ''} onclick="selectInstallTime('${slot.key}')">
        <strong>${escapeHtml(slot.title)}</strong>
        <em>${escapeHtml(slot.detail)} น.</em>
        <span>${label}</span>
      </button>
    `;
  }).join('');
}

function selectInstallTime(slotKey) {
  if (!selectedInstallDate || !currentQueueTechId) return;

  const slot = availableTimeSlots.find(item => item.key === slotKey);
  if (!slot) return;

  const startTime = normalizeTime(slot.start);
  const endTime = normalizeTime(slot.end);

  if (!startTime || !endTime || timeToMinutes(endTime) <= timeToMinutes(startTime)) {
    alert('กรุณาเลือกเวลาเริ่มต้นและเวลาสิ้นสุดให้ถูกต้อง');
    return;
  }

  if (isTimeSlotPassed(selectedInstallDate, endTime)) {
    alert('ช่วงเวลานี้ผ่านไปแล้ว กรุณาเลือกช่วงเวลาใหม่');
    return;
  }

  if (isTimeSlotBusy(currentQueueTechId, selectedInstallDate, startTime, endTime)) {
    alert('ช่วงเวลานี้มีงานของช่างแล้ว กรุณาเลือกช่วงเวลาอื่น');
    return;
  }

  selectedInstallTime = startTime;
  selectedInstallEndTime = endTime;
  selectedTimeSlot = slot.key;
  document.getElementById('assign_time_slot').value = slot.key;
  document.getElementById('selectedTimeText').textContent = `เวลาที่เลือก: ${timeRangeLabel(startTime, endTime)}`;
  renderTimeSlots();
  updateSaveButton();
}
function beforeAssignSubmit() {
  if (assignmentPermissions.readOnly) return false;
  if (!document.getElementById('setup_id').value) {
    alert('ไม่พบใบงานติดตั้ง');
    return false;
  }
  if (!document.getElementById('tech_id').value) {
    alert('กรุณาเลือกช่างติดตั้ง');
    return false;
  }
  if (!document.getElementById('assign_install_date').value) {
    alert('กรุณาเลือกวันที่ติดตั้งจากตารางเวลาช่าง');
    return false;
  }
  if (!document.getElementById('assign_time_slot').value) {
    alert('กรุณาเลือกช่วงเวลาติดตั้ง');
    return false;
  }
  const selectedSlot = availableTimeSlots.find(slot => slot.key === document.getElementById('assign_time_slot').value);
  if (!selectedSlot) return false;
  const startTime = selectedSlot.start;
  const endTime = selectedSlot.end;

  if (isTimeSlotPassed(document.getElementById('assign_install_date').value, endTime)) {
    alert('ช่วงเวลาที่เลือกผ่านไปแล้ว กรุณาเลือกช่วงเวลาใหม่');
    return false;
  }

  if (isTimeSlotBusy(document.getElementById('tech_id').value, document.getElementById('assign_install_date').value, startTime, endTime)) {
    alert('ช่วงเวลานี้มีงานของช่างแล้ว กรุณาเลือกช่วงเวลาอื่น');
    return false;
  }
  if (assignmentPermissions.requiresTechChangeConfirmation && initialTechId && document.getElementById('tech_id').value !== initialTechId) {
    if (!confirm('ช่างรับงานนี้แล้ว ยืนยันการเปลี่ยนช่างหรือไม่?')) return false;
    document.getElementById('confirm_tech_change').value = '1';
  }
  return true;
}

document.querySelectorAll('[data-calendar-month]').forEach(button => {
  button.addEventListener('click', () => changeCalendarMonth(Number(button.dataset.calendarMonth || 0)));
});

const techSearchInput = document.getElementById('techSearch');
if (techSearchInput) techSearchInput.addEventListener('input', renderTechnicians);

const setupNoteInput = document.getElementById('setup_note');
const setupNoteCounter = document.getElementById('setupNoteCounter');
function updateSetupNoteCounter() {
  if (!setupNoteInput || !setupNoteCounter) return;
  setupNoteCounter.textContent = `${setupNoteInput.value.length}/500`;
}
if (setupNoteInput) setupNoteInput.addEventListener('input', updateSetupNoteCounter);
updateSetupNoteCounter();

renderTechnicians();
renderSelectedTechSchedule();
renderCalendar();
renderTimeSlots();
updateSaveButton();

if (initialTechId) {
  document.getElementById('schedulePanel').classList.add('show');
  document.getElementById('tech_id').value = initialTechId;
  document.getElementById('assign_install_date').value = initialInstallDate || '';
  if (initialInstallDate) {
    selectedInstallDate = initialInstallDate;
    document.getElementById('selectedDateBox').textContent = `วันที่ติดตั้งที่เลือก: ${formatDate(initialInstallDate)}`;
  }
  if (initialInstallTime) {
    selectedInstallTime = initialInstallTime;
    selectedInstallEndTime = initialInstallEndTime || '';
    document.getElementById('assign_time_slot').value = selectedTimeSlot;
  }
  renderSelectedTechSchedule();
  renderCalendar();
  renderTimeSlots();
  updateSaveButton();
}
