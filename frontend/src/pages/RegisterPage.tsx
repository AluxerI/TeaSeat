import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { authApi } from "../api/authAPI";
import { translateError, extractError } from "../utils/translateError";
import styles from "./RegisterPage.module.scss";

// ── Иконки ──────────────────────────────────────────────────────────────────

function EyeIcon({ size = 18 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
      <circle cx="12" cy="12" r="3" />
    </svg>
  );
}

function EyeOffIcon({ size = 18 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94" />
      <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19" />
      <line x1="1" y1="1" x2="23" y2="23" />
    </svg>
  );
}

function UserIcon({ size = 18 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
      <circle cx="12" cy="7" r="4" />
    </svg>
  );
}

function MailIcon({ size = 18 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <rect x="2" y="4" width="20" height="16" rx="2" />
      <polyline points="2,4 12,13 22,4" />
    </svg>
  );
}

function LockIcon({ size = 18 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
      <path d="M7 11V7a5 5 0 0 1 10 0v4" />
    </svg>
  );
}

function PhoneIcon({ size = 18 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.15 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.06 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21 16l.92.92z" />
    </svg>
  );
}

function CheckIcon({ size = 14 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="20 6 9 17 4 12" />
    </svg>
  );
}

// ── Логотип ─────────────────────────────────────────────────────────────────

function Logo() {
  return (
    <a href="/" className={styles.logo} aria-label="Чайные посиделки — на главную">
      <svg width="56" height="56" viewBox="0 0 56 56" fill="none">
        <circle cx="28" cy="28" r="28" fill="#5a2d0c" />
        <ellipse cx="28" cy="38" rx="13" ry="3.5" fill="#c8903c" opacity="0.35" />
        <path d="M17 26 Q17 36 28 36 Q39 36 39 26 Z" fill="#c8903c" opacity="0.9" />
        <rect x="16" y="24" width="24" height="3" rx="1.5" fill="#e0a84a" />
        <path d="M39 27 Q45 27 45 31 Q45 35 39 35" stroke="#e0a84a" strokeWidth="2" fill="none" strokeLinecap="round" />
        <path d="M22 22 Q21 18 23 15" stroke="#8ecfcf" strokeWidth="1.4" fill="none" strokeLinecap="round" opacity="0.8" />
        <path d="M28 21 Q27 17 29 13" stroke="#8ecfcf" strokeWidth="1.4" fill="none" strokeLinecap="round" opacity="0.8" />
        <path d="M34 22 Q33 18 35 15" stroke="#8ecfcf" strokeWidth="1.4" fill="none" strokeLinecap="round" opacity="0.8" />
        <ellipse cx="28" cy="31" rx="4" ry="2" fill="#5a2d0c" opacity="0.4" transform="rotate(-15 28 31)" />
      </svg>
      <span className={styles.logoText}>Чайные посиделки</span>
    </a>
  );
}

// ── Поле ввода ──────────────────────────────────────────────────────────────

interface FieldProps {
  label: string;
  type?: string;
  placeholder: string;
  value: string;
  onChange: (v: string) => void;
  icon: React.ReactNode;
  error?: string;
  hint?: string;
  suffix?: React.ReactNode;
  required?: boolean;
}

function Field({ label, type = "text", placeholder, value, onChange, icon, error, hint, suffix, required }: FieldProps) {
  return (
    <div className={styles.field}>
      <label className={styles.fieldLabel}>
        {label}
        {required && <span className={styles.fieldRequired} aria-hidden="true">*</span>}
      </label>
      <div className={`${styles.fieldWrap} ${error ? styles.fieldWrapError : ""}`}>
        <span className={styles.fieldIcon}>{icon}</span>
        <input
          type={type}
          placeholder={placeholder}
          value={value}
          onChange={e => onChange(e.target.value)}
          className={styles.fieldInput}
          aria-invalid={!!error}
          aria-describedby={error ? `${label}-err` : undefined}
          required={required}
        />
        {suffix && <span className={styles.fieldSuffix}>{suffix}</span>}
      </div>
      {error && <p className={styles.fieldError} id={`${label}-err`} role="alert">{error}</p>}
      {hint && !error && <p className={styles.fieldHint}>{hint}</p>}
    </div>
  );
}

// ── Индикатор силы пароля ───────────────────────────────────────────────────

function PasswordStrength({ password }: { password: string }) {
  const checks = [
    { label: "Минимум 8 символов", ok: password.length >= 8 },
    { label: "Заглавная буква",    ok: /[A-ZА-ЯЁ]/.test(password) },
    { label: "Цифра",              ok: /\d/.test(password) },
    { label: "Спецсимвол",         ok: /[!@#$%^&*_]/.test(password) },
  ];
  const score = checks.filter(c => c.ok).length;
  const labels = ["", "Слабый", "Средний", "Хороший", "Надёжный"];
  const colors = ["", "#c0392b", "#e67e22", "#d4a017", "#5a8a2a"];

  if (!password) return null;

  return (
    <div className={styles.strength}>
      <div className={styles.strengthBars}>
        {[1, 2, 3, 4].map(i => (
          <div
            key={i}
            className={styles.strengthBar}
            style={{ background: i <= score ? colors[score] : "#e0d0ba" }}
          />
        ))}
      </div>
      <span className={styles.strengthLabel} style={{ color: colors[score] }}>
        {labels[score]}
      </span>
      <ul className={styles.strengthChecks}>
        {checks.map(c => (
          <li key={c.label} className={`${styles.strengthCheck} ${c.ok ? styles.strengthCheckOk : ""}`}>
            <span className={styles.strengthCheckIcon}>{c.ok ? <CheckIcon /> : null}</span>
            {c.label}
          </li>
        ))}
      </ul>
    </div>
  );
}

// ── Главный компонент ──────────────────────────────────────────────────────

interface FormState {
  name: string;
  email: string;
  phone: string;
  password: string;
  confirm: string;
  agree: boolean;
}

interface FormErrors {
  name?: string;
  email?: string;
  phone?: string;
  password?: string;
  confirm?: string;
  agree?: string;
}

export default function RegisterPage() {
  const navigate = useNavigate();
  const [form, setForm] = useState<FormState>({
    name: "", email: "", phone: "", password: "", confirm: "", agree: false,
  });
  const [errors, setErrors]     = useState<FormErrors>({});
  const [showPass, setShowPass] = useState(false);
  const [showConf, setShowConf] = useState(false);
  const [success, setSuccess]   = useState(false);
  const [loading, setLoading]   = useState(false);
  const [serverError, setServerError] = useState("");

  const set = (key: keyof FormState) => (val: string) =>
    setForm(f => ({ ...f, [key]: val }));

  function formatPhone(raw: string): string {
    const digits = raw.replace(/\D/g, '');
    let result = '';

    if (digits.length === 0) return '';

    const first = digits[0];
    if (first === '7' || first === '8') {
      result = '+7';
      const rest = first === '8' ? digits.slice(1) : digits.slice(1);

      if (rest.length > 0) result += ' (' + rest.slice(0, 3);
      if (rest.length > 3) result += ') ' + rest.slice(3, 6);
      if (rest.length > 6) result += '-' + rest.slice(6, 8);
      if (rest.length > 8) result += '-' + rest.slice(8, 10);
    } else {
      result = digits.slice(0, 10);
    }

    return result;
  }

  function validate(): boolean {
    const e: FormErrors = {};
    if (!form.name.trim())                          e.name     = "Введите имя";
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) e.email = "Некорректный email";
    if (form.phone && !/^\+?[\d\s\-()]{7,}$/.test(form.phone)) e.phone = "Некорректный телефон";
    if (form.password.length < 8)                   e.password = "Минимум 8 символов";
    if (form.confirm !== form.password)             e.confirm  = "Пароли не совпадают";
    if (!form.agree)                                e.agree    = "Необходимо согласие";
    setErrors(e);
    return Object.keys(e).length === 0;
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setServerError("");
    if (!validate()) return;

    setLoading(true);
    try {
      await authApi.register({
        name: form.name.trim(),
        email: form.email.trim(),
        phone: form.phone ? '+7' + form.phone.replace(/\D/g, '').slice(-10) : undefined,
        password: form.password,
        password_confirmation: form.confirm,
      });
      setSuccess(true);
    } catch (err: any) {
      const msg = translateError(extractError(err));
      setServerError(msg);
    } finally {
      setLoading(false);
    }
  }

  if (success) {
    return (
      <div className={styles.page}>
        <div className={styles.card}>
          <Logo />
          <div className={styles.successWrap}>
            <div className={styles.successIcon}>🍵</div>
            <h2 className={styles.successTitle}>Добро пожаловать!</h2>
            <p className={styles.successText}>
              Аккаунт создан. Проверьте почту&nbsp;
              <strong>{form.email}</strong> и подтвердите регистрацию.
            </p>
            <a href="/catalog" className={styles.successBtn}>Перейти в каталог</a>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className={styles.page}>
      <div className={styles.leafLeft}  aria-hidden="true" />
      <div className={styles.leafRight} aria-hidden="true" />

      <div className={styles.card}>
        <Logo />

        <div className={styles.cardHead}>
          <h1 className={styles.cardTitle}>Создать аккаунт</h1>
          <p className={styles.cardSub}>
            Уже есть аккаунт?&nbsp;
            <a href="/login" className={styles.cardLink}>Войти</a>
          </p>
        </div>

        <form className={styles.form} onSubmit={handleSubmit} noValidate>
          <Field
            label="Имя"
            placeholder="Как вас зовут?"
            value={form.name}
            onChange={set("name")}
            icon={<UserIcon />}
            error={errors.name}
            required
          />
          <Field
            label="Email"
            type="email"
            placeholder="you@example.com"
            value={form.email}
            onChange={set("email")}
            icon={<MailIcon />}
            error={errors.email}
            required
          />
          <Field
            label="Телефон"
            type="tel"
            placeholder="+7 (___) ___-__-__"
            value={form.phone}
            onChange={v => setForm(f => ({ ...f, phone: formatPhone(v) }))}
            icon={<PhoneIcon />}
            error={errors.phone}
            hint="Необязательно"
          />
          <Field
            label="Пароль"
            type={showPass ? "text" : "password"}
            placeholder="Придумайте пароль"
            value={form.password}
            onChange={set("password")}
            icon={<LockIcon />}
            error={errors.password}
            required
            suffix={
              <button
                type="button"
                className={styles.eyeBtn}
                onClick={() => setShowPass(v => !v)}
                aria-label={showPass ? "Скрыть пароль" : "Показать пароль"}
              >
                {showPass ? <EyeOffIcon /> : <EyeIcon />}
              </button>
            }
          />
          <PasswordStrength password={form.password} />

          <Field
            label="Повторите пароль"
            type={showConf ? "text" : "password"}
            placeholder="Ещё раз"
            value={form.confirm}
            onChange={set("confirm")}
            icon={<LockIcon />}
            error={errors.confirm}
            required
            suffix={
              <button
                type="button"
                className={styles.eyeBtn}
                onClick={() => setShowConf(v => !v)}
                aria-label={showConf ? "Скрыть пароль" : "Показать пароль"}
              >
                {showConf ? <EyeOffIcon /> : <EyeIcon />}
              </button>
            }
          />

          <label className={`${styles.checkRow} ${errors.agree ? styles.checkRowError : ""}`}>
            <span className={`${styles.checkbox} ${form.agree ? styles.checkboxChecked : ""}`} aria-hidden="true">
              {form.agree && <CheckIcon size={12} />}
            </span>
            <input
              type="checkbox"
              className={styles.checkboxHidden}
              checked={form.agree}
              onChange={e => setForm(f => ({ ...f, agree: e.target.checked }))}
              required
            />
            <span className={styles.checkLabel}>
              Я принимаю&nbsp;
              <a href="/terms" className={styles.cardLink}>условия использования</a>
              &nbsp;и&nbsp;
              <a href="/privacy" className={styles.cardLink}>политику конфиденциальности</a>
            </span>
          </label>
          {errors.agree && <p className={styles.fieldError} role="alert">{errors.agree}</p>}
          {serverError && <p className={styles.fieldError} role="alert">{serverError}</p>}

          <button type="submit" className={styles.submitBtn} disabled={loading || !form.agree}>
            {loading ? "Отправка…" : "Зарегистрироваться"}
          </button>
        </form>

        <div className={styles.divider}><span>или войдите через</span></div>

        <div className={styles.socials}>
          <button className={styles.socialBtn} aria-label="Войти через ВКонтакте">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="#4680C2">
              <path d="M20.436 4H3.564C3.253 4 3 4.253 3 4.564v14.872c0 .311.253.564.564.564h16.872c.311 0 .564-.253.564-.564V4.564C21 4.253 20.747 4 20.436 4zm-2.467 10.143h-1.498c-.567 0-.74-.452-1.755-1.478-.882-.865-1.27-.982-1.487-.982-.302 0-.389.086-.389.503v1.348c0 .36-.115.576-1.066.576-1.57 0-3.312-.95-4.538-2.72C5.568 9.406 5.2 7.873 5.2 7.527c0-.216.086-.418.503-.418h1.499c.374 0 .517.173.661.576.73 2.1 1.943 3.942 2.446 3.942.187 0 .273-.086.273-.56V9.234c-.058-1.007-.59-1.093-.59-1.452 0-.173.143-.36.374-.36h2.36c.316 0 .43.173.43.546v2.937c0 .316.143.43.23.43.187 0 .345-.114.69-.46 1.065-1.194 1.826-3.033 1.826-3.033.1-.216.273-.417.647-.417h1.499c.45 0 .548.23.45.546-.187.863-2.015 3.452-2.015 3.452-.158.258-.215.374 0 .661.158.216.676.661.99 1.065.633.777.93 1.437.676 1.953z" />
            </svg>
            ВКонтакте
          </button>
          <button className={styles.socialBtn} aria-label="Войти через Telegram">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#229ED9" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M21 4L2 10.5l6.5 2L17 7l-6.5 7 7 4z" />
              <path d="M8.5 12.5L10 18l3-4" />
            </svg>
            Telegram
          </button>
        </div>
      </div>
    </div>
  );
}
