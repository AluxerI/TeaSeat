// Старый импорт оставляем совместимым: Courier и Manager используют один
// разбор HTTP-ошибок, а существующие courier-тесты не требуют переписывания.
export {
  StaffApiError, // общий тип ошибки staff-разделов
  fieldError, // ошибка по конкретному полю формы
  retrySeconds, // секунды до повтора (для 429)
  toStaffApiError, // приведение ошибки к StaffApiError
  type StaffErrorKind, // переиспользуем тип категорий ошибок
} from "../staff/errors";
