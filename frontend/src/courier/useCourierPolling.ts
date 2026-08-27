// Оставляем старое имя как совместимый доменный alias: существующим страницам
// и тестам не нужно знать, что тот же безопасный polling теперь использует picker.
export { useRecursivePolling as useCourierPolling } from "../hooks/useRecursivePolling";
