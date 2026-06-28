import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { authApi, setAuthToken } from "../api/authAPI";
import { translateError, extractError } from "../utils/translateError";
import styles from "./LoginPage.module.scss";

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

function CheckIcon({ size = 14 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="20 6 9 17 4 12" />
    </svg>
  );
}

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

interface FieldProps {
  label: string;
  type?: string;
  placeholder: string;
  value: string;
  onChange: (v: string) => void;
  icon: React.ReactNode;
  error?: string;
  suffix?: React.ReactNode;
  required?: boolean;
}

function Field({ label, type = "text", placeholder, value, onChange, icon, error, suffix, required }: FieldProps) {
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
    </div>
  );
}

interface FormErrors {
  email?: string;
  password?: string;
}

export default function LoginPage() {
  const navigate = useNavigate();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [remember, setRemember] = useState(false);
  const [errors, setErrors] = useState<FormErrors>({});
  const [showPass, setShowPass] = useState(false);
  const [loading, setLoading] = useState(false);
  const [serverError, setServerError] = useState("");

  function validate(): boolean {
    const e: FormErrors = {};
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) e.email = "Некорректный email";
    if (!password) e.password = "Введите пароль";
    setErrors(e);
    return Object.keys(e).length === 0;
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setServerError("");
    if (!validate()) return;

    setLoading(true);
    try {
      const res = await authApi.login({ email: email.trim(), password });
      if (res.token) setAuthToken(res.token);
      navigate("/catalog");
    } catch (err: any) {
      const msg = translateError(extractError(err));
      setServerError(msg);
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className={styles.page}>
      <div className={styles.leafLeft} aria-hidden="true" />
      <div className={styles.leafRight} aria-hidden="true" />

      <div className={styles.card}>
        <Logo />

        <div className={styles.cardHead}>
          <h1 className={styles.cardTitle}>Войти</h1>
          <p className={styles.cardSub}>
            Нет аккаунта?&nbsp;
            <a href="/register" className={styles.cardLink}>Зарегистрироваться</a>
          </p>
        </div>

        <form className={styles.form} onSubmit={handleSubmit} noValidate>
          <Field
            label="Email"
            type="email"
            placeholder="you@example.com"
            value={email}
            onChange={setEmail}
            icon={<MailIcon />}
            error={errors.email}
            required
          />
          <Field
            label="Пароль"
            type={showPass ? "text" : "password"}
            placeholder="Введите пароль"
            value={password}
            onChange={setPassword}
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

          <div className={styles.row}>
            <label className={styles.checkRow}>
              <span className={`${styles.checkbox} ${remember ? styles.checkboxChecked : ""}`} aria-hidden="true">
                {remember && <CheckIcon size={12} />}
              </span>
              <input
                type="checkbox"
                className={styles.checkboxHidden}
                checked={remember}
                onChange={e => setRemember(e.target.checked)}
              />
              <span className={styles.checkLabel}>Запомнить меня</span>
            </label>
            <a href="/forgot-password" className={styles.forgotLink}>Забыли пароль?</a>
          </div>

          {serverError && <p className={styles.fieldError} role="alert">{serverError}</p>}

          <button type="submit" className={styles.submitBtn} disabled={loading}>
            {loading ? "Вход…" : "Войти"}
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
