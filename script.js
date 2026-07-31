// ===============================
// Icons
// ===============================
const EYE_OPEN = `
  <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path>
  <circle cx="12" cy="12" r="3"></circle>
`;

const EYE_OFF = `
  <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a18.5 18.5 0 0 1 4.22-5.06"></path>
  <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 7 11 7a18.5 18.5 0 0 1-2.16 3.19"></path>
  <line x1="1" y1="1" x2="23" y2="23"></line>
  <path d="M14.12 14.12A3 3 0 1 1 9.88 9.88"></path>
`;

// ===============================
// Toggle password
// ===============================
function makeToggle(btnId, inputId, eyeId) {
  const btn = document.getElementById(btnId);
  const input = document.getElementById(inputId);
  const eye = document.getElementById(eyeId);

  if (!btn || !input || !eye) return;

  btn.addEventListener("click", () => {
    const hidden = input.type === "password";
    input.type = hidden ? "text" : "password";
    eye.innerHTML = hidden ? EYE_OFF : EYE_OPEN;
  });
}

makeToggle("togglePass", "user_password", "eyeIcon");
makeToggle("togglePass1", "user_password", "eye1");
makeToggle("togglePass2", "user_confirm", "eye2");

// ===============================
// Login alert
// ===============================
document.addEventListener("DOMContentLoaded", () => {
  const params = new URLSearchParams(window.location.search);

  const error = params.get("error");
  const registered = params.get("registered");
  const userIdFromUrl = params.get("user_id");

  const loginAlert = document.getElementById("loginAlert");
  const loginAlertText = document.getElementById("loginAlertText");
  const loginUserId = document.getElementById("user_id");

  if (loginUserId && userIdFromUrl) {
    loginUserId.value = userIdFromUrl;
  }

  if (loginAlert && loginAlertText) {
    if (registered === "1") {
      loginAlertText.textContent = "สมัครสมาชิกสำเร็จแล้ว กรุณาเข้าสู่ระบบ";
      loginAlert.classList.add("show", "success");
      return;
    }

    if (error) {
      const messages = {
        empty: "กรุณากรอกข้อมูลให้ครบก่อนเข้าสู่ระบบ",
        invalid: "ไม่พบข้อมูลผู้ใช้งาน หรือรหัสผ่านไม่ถูกต้อง",
        role: "ประเภทผู้ใช้งานที่เลือกไม่ตรงกับบัญชีนี้",
        denied: "บัญชีนี้ไม่มีสิทธิ์เข้าใช้งานหน้านั้น",
        login: "กรุณาเข้าสู่ระบบก่อนใช้งาน",
        server: "ระบบเชื่อมต่อฐานข้อมูลไม่ได้ กรุณาตรวจสอบ XAMPP/MySQL",
      };

      loginAlertText.textContent = messages[error] || messages.invalid;
      loginAlert.classList.add("show");
    }
  }
});

// ===============================
// Register: auto-generate user_id
// Format: USR-2569-0000 = 13 chars
// ===============================
function generateUserId() {
  const year = new Date().getFullYear() + 543;
  const rand = Math.floor(1000 + Math.random() * 9000);
  return `USR-${year}-${rand}`;
}

const regUserId = document.getElementById("user_id");
const regForm = document.getElementById("regForm");

if (regUserId && regForm) {
  regUserId.value = generateUserId();
}

// ===============================
// Register: password strength
// ===============================
const passInput = document.getElementById("user_password");
const strengthFill = document.getElementById("strengthFill");
const strengthLabel = document.getElementById("strengthLabel");

if (passInput && strengthFill && strengthLabel && regForm) {
  passInput.addEventListener("input", function () {
    const value = this.value;
    let score = 0;

    if (value.length >= 6) score++;
    if (value.length >= 10) score++;
    if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score++;
    if (/[0-9]/.test(value)) score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;

    const levels = [
      { width: "0%", text: "ระดับความปลอดภัย: -", className: "" },
      { width: "25%", text: "ระดับความปลอดภัย: ต่ำมาก", className: "weak" },
      { width: "50%", text: "ระดับความปลอดภัย: ปานกลาง", className: "medium" },
      { width: "75%", text: "ระดับความปลอดภัย: ดี", className: "good" },
      { width: "100%", text: "ระดับความปลอดภัย: แข็งแกร่งมาก", className: "strong" },
    ];

    const level = value.length === 0 ? levels[0] : levels[Math.min(score, 4)];

    strengthFill.style.width = level.width;
    strengthFill.className = "strength-fill " + level.className;
    strengthLabel.textContent = level.text;
    strengthLabel.className = "strength-label " + level.className;
  });
}

// ===============================
// Register: email check
// ===============================
const emailInput = document.getElementById("user_email");
const emailCheck = document.getElementById("emailCheck");

if (emailInput && emailCheck) {
  emailInput.addEventListener("input", function () {
    const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value);
    emailCheck.style.display = valid ? "block" : "none";
  });
}

// ===============================
// Register: phone numbers only
// ===============================
const phoneInput = document.getElementById("user_phone");

if (phoneInput) {
  phoneInput.addEventListener("input", function () {
    this.value = this.value.replace(/\D/g, "").slice(0, 10);
  });
}

// ===============================
// Register validation
// ===============================
if (regForm) {
  regForm.addEventListener("submit", function (e) {
    const phone = document.getElementById("user_phone").value;
    const pass = document.getElementById("user_password").value;
    const confirm = document.getElementById("user_confirm").value;

    const alertBox = document.getElementById("regAlert");
    const alertText = document.getElementById("regAlertText");

    function showError(message) {
      e.preventDefault();
      alertText.textContent = message;
      alertBox.className = "alert show";
      alertBox.scrollIntoView({ behavior: "smooth", block: "center" });
    }

    if (phone.length !== 10) {
      showError("กรุณากรอกเบอร์โทรศัพท์ให้ครบ 10 หลัก");
      return;
    }

    if (pass.length < 6) {
      showError("รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร");
      return;
    }

    if (pass !== confirm) {
      showError("รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง");
      return;
    }
  });
}

// ===============================
// Register alert from query string
// ===============================
document.addEventListener("DOMContentLoaded", () => {
  const params = new URLSearchParams(window.location.search);
  const status = params.get("status");

  const regAlert = document.getElementById("regAlert");
  const regAlertText = document.getElementById("regAlertText");

  if (!regAlert || !regAlertText || !status) return;

  const messages = {
    success: ["สมัครสมาชิกสำเร็จแล้ว กรุณาเข้าสู่ระบบ", "success"],
    duplicate: ["รหัสผู้ใช้งานนี้มีอยู่ในระบบแล้ว กรุณารีเฟรชเพื่อรับรหัสใหม่", ""],
    error: ["เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง", ""],
  };

  if (messages[status]) {
    regAlertText.textContent = messages[status][0];
    regAlert.className = "alert show " + messages[status][1];
  }
});