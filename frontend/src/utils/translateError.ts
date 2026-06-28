const errorMap: Record<string, string> = {
  'The email field is required.': 'Email обязателен.',
  'The password field is required.': 'Пароль обязателен.',
  'The name field is required.': 'Имя обязательно.',
  'The phone field is required.': 'Телефон обязателен.',
  'The email has already been taken.': 'Этот email уже зарегистрирован.',
  'The password confirmation does not match.': 'Пароли не совпадают.',
  'These credentials do not match our records.': 'Неверный email или пароль.',
  'The provided password is incorrect.': 'Неверный пароль.',
  'The provided credentials are incorrect.': 'Неверный email или пароль.',
  'Invalid credentials.': 'Неверный email или пароль.',
  'Invalid credentials': 'Неверный email или пароль.',
  'Too many login attempts. Please try again in': 'Слишком много попыток. Повторите через',

  'The password confirmation field is required.': 'Подтверждение пароля обязательно.',
  'The token field is required.': 'Токен сброса обязателен.',
  'This password reset token is invalid.': 'Недействительный токен сброса пароля.',
  "We can't find a user with that email address.": 'Пользователь с таким email не найден.',
  'Passwords must be at least 8 characters.': 'Пароль должен быть минимум 8 символов.',

  'The code field is required.': 'Код подтверждения обязателен.',
  'The verification code is invalid.': 'Неверный код подтверждения.',
  'The provided code is incorrect.': 'Неверный код.',

  'The email format is invalid.': 'Некорректный формат email.',
  'The phone format is invalid.': 'Некорректный формат телефона.',

  'The given data was invalid.': 'Проверьте правильность заполнения полей.',
};

export function translateError(msg: string): string {
  if (!msg) return '';
  for (const [en, ru] of Object.entries(errorMap)) {
    if (msg.includes(en)) return ru;
  }
  return msg;
}

export function extractError(err: any): string {
  if (!err?.response?.data) return 'Произошла ошибка. Попробуйте позже.';

  const data = err.response.data;

  if (data.message && data.message !== 'The given data was invalid.') {
    return data.message;
  }

  if (data.errors) {
    const firstField = Object.values(data.errors)[0];
    if (Array.isArray(firstField) && firstField.length > 0) {
      return firstField[0];
    }
  }

  return data.message || data.error || 'Произошла ошибка. Попробуйте позже.';
}
