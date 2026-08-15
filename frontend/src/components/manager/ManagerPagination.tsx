// Компоненты пагинации MUI и тип текста.
import { Pagination, Typography } from "@mui/material";
// Тип метаданных пагинации из ответа API.
import type { PaginationMeta } from "../../manager/types";
// CSS-модуль общих стилей.
import styles from "../../scss/pages/ManagerShared.module.scss";

// Компонент пагинации списков кабинета менеджера.
export function ManagerPagination({ meta, onChange }: { meta: PaginationMeta; onChange: (page: number) => void }) {
  // Если страница всего одна — пагинацию не показываем.
  if (meta.last_page <= 1) return null;
  return (
    <div className={styles.pagination}>
      <Typography className={styles.muted}>Всего: {meta.total}</Typography> {/* общее количество записей */}
      <Pagination page={meta.current_page} count={meta.last_page} onChange={(_, page) => onChange(page)} color="primary" /> {/* кнопки страниц */}
    </div>
  );
}
