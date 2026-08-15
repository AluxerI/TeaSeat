// MUI-компоненты: аккордеон, баннер, контейнер, разделитель, карточка, текст.
import { Accordion, AccordionDetails, AccordionSummary, Alert, Box, Divider, Paper, Typography } from "@mui/material";
// Иконка раскрытия аккордеона.
import ExpandMoreRoundedIcon from "@mui/icons-material/ExpandMoreRounded";
// Тип заказа.
import type { ManagerOrder } from "../../../manager/types";
// Форматирование даты и денег.
import { dateTime, money } from "../../../manager/format";
// Чип статуса.
import { ManagerStatusChip } from "../ManagerStatusChip";
// CSS-модуль общих стилей.
import styles from "../../../scss/pages/ManagerShared.module.scss";

// Строка «подпись — значение» для панелей деталей.
const Line = ({ label, value }: { label: string; value: React.ReactNode }) => (
  <Box className={`${styles.row} ${styles.dividerRow}`}><Typography className={styles.meta}>{label}</Typography><Typography sx={{ textAlign: "right", fontWeight: 700 }}>{value || "—"}</Typography></Box>
);

// Панель «Клиент и оплата».
export function CustomerPaymentPanel({ order }: { order: ManagerOrder }) {
  return (
    <Paper className={styles.panel}>
      <Typography className={styles.sectionTitle}>Клиент и оплата</Typography>
      <Line label="Имя" value={order.customer?.name} /> {/* имя клиента */}
      <Line label="Телефон" value={order.customer?.phone} /> {/* телефон */}
      <Line label="Email" value={order.customer?.email} /> {/* email */}
      <Divider sx={{ my: 1 }} /> {/* разделитель */}
      <Line label="Способ оплаты" value={order.payment.method} /> {/* способ оплаты */}
      <Line label="Оплачен" value={order.payment.paid_at ? dateTime(order.payment.paid_at) : "Нет"} /> {/* дата оплаты */}
      <Line label="Получить" value={money(order.payment.amount_to_collect)} /> {/* сумма к получению */}
      <Line label="Итого" value={money(order.totals.final_total)} /> {/* итоговая сумма */}
    </Paper>
  );
}

// Панель «Доставка и сотрудники».
export function DeliveryPanel({ order }: { order: ManagerOrder }) {
  const window = order.delivery.scheduled_window; // запланированное окно доставки
  return (
    <Paper className={styles.panel}>
      <Typography className={styles.sectionTitle}>Доставка и сотрудники</Typography>
      <Line label="Способ" value={order.delivery.method?.name} /> {/* способ доставки */}
      <Line label="Адрес" value={order.delivery.address?.full_address} /> {/* адрес */}
      <Line label="Интервал" value={window ? `${window.date}, ${window.time_from}–${window.time_to}` : "Не назначен"} /> {/* окно доставки */}
      <Line label="Склад" value={order.delivery.warehouse?.name} /> {/* склад отправки */}
      <Line label="Сборщик" value={order.staff.picker?.name} /> {/* сборщик */}
      <Line label="Курьер" value={order.staff.courier?.name} /> {/* курьер */}
      <Line label="Трекинг" value={order.delivery.tracking_number} /> {/* трек-номер */}
    </Paper>
  );
}

// Панель «Комментарии»: клиентский и внутренний журнал.
export function NotesPanel({ order }: { order: ManagerOrder }) {
  return (
    <Paper className={styles.panel}>
      <Typography className={styles.sectionTitle}>Комментарии</Typography>
      <Typography className={styles.meta} sx={{ mt: 1 }}>Клиентский</Typography>
      <Typography className={styles.preWrap}>{order.notes?.customer || "—"}</Typography> {/* комментарий клиента */}
      <Typography className={styles.meta} sx={{ mt: 2 }}>Внутренний журнал</Typography>
      <Typography className={styles.preWrap}>{order.notes?.internal || "—"}</Typography> {/* внутренние заметки */}
    </Paper>
  );
}

// Панель «Исполнение по складам»: фулфилмент-части заказа.
export function FulfillmentPanel({ order }: { order: ManagerOrder }) {
  return (
    <Paper className={styles.panel}>
      <Typography className={styles.sectionTitle}>Исполнение по складам</Typography>
      {/* Если частей нет — заказ исполняется одной точкой. */}
      {(order.partial_orders ?? []).length === 0 && <Typography className={styles.muted} sx={{ mt: 1 }}>Заказ исполняется одной точкой.</Typography>}
      {/* Каждая фулфилмент-часть: маршрут складов и статус. */}
      {(order.partial_orders ?? []).map((part) => (
        <Box key={part.id} className={styles.dividerRow}>
          <Box className={styles.cardHeader}>
            <Box><Typography sx={{ fontWeight: 750 }}>{part.order_number}</Typography><Typography className={styles.meta}>{part.warehouse?.name} → {part.destination_warehouse?.name ?? "клиент"}</Typography></Box>
            <ManagerStatusChip status={part.status} label={part.status_name} /> {/* статус части */}
          </Box>
        </Box>
      ))}
    </Paper>
  );
}

// Панель проблем комплектации заказа (если они есть).
export function IssuesPanel({ order }: { order: ManagerOrder }) {
  const issues = order.fulfillment_issues ?? []; // проблемы заказа
  if (!issues.length) return null; // нет проблем — панель не показываем
  return (
    <Paper className={styles.panel}>
      <Typography className={styles.sectionTitle}>Проблемы комплектации</Typography>
      {/* Каждая проблема: товар и описание причины; закрытые — информационные. */}
      {issues.map((issue) => <Alert key={issue.id} severity={issue.status === "closed" ? "info" : "error"} sx={{ mt: 1 }}>{issue.product?.name}: {issue.reason_message}</Alert>)}
    </Paper>
  );
}

// Техническая история: статусы, движения остатков, правки менеджера.
export function TechnicalHistory({ order }: { order: ManagerOrder }) {
  return (
    <Box>
      {/* История смены статусов. */}
      <Accordion><AccordionSummary expandIcon={<ExpandMoreRoundedIcon />}><Typography sx={{ fontWeight: 750 }}>История статусов ({order.status_history?.length ?? 0})</Typography></AccordionSummary><AccordionDetails>
        {(order.status_history ?? []).map((item, index) => <Box key={`${item.created_at}-${index}`} className={styles.dividerRow}><Typography sx={{ fontWeight: 700 }}>{item.from_status_name ?? item.from_status ?? "Создан"} → {item.to_status_name ?? item.to_status}</Typography><Typography className={styles.meta}>{dateTime(item.created_at)} · {item.changed_by?.name ?? "система"}</Typography>{item.notes && <Typography>{item.notes}</Typography>}</Box>)}
      </AccordionDetails></Accordion>
      {/* Движения остатков по заказу. */}
      <Accordion><AccordionSummary expandIcon={<ExpandMoreRoundedIcon />}><Typography sx={{ fontWeight: 750 }}>Движения остатков ({order.inventory_movements?.length ?? 0})</Typography></AccordionSummary><AccordionDetails>
        {(order.inventory_movements ?? []).map((item) => <Box key={item.id} className={styles.dividerRow}><Typography sx={{ fontWeight: 700 }}>{item.product?.name ?? "Товар"} · {item.type}</Typography><Typography className={styles.meta}>{dateTime(item.created_at)} · физ.: {item.physical_delta}, online reserve: {item.reserved_online_delta}, seller reserve: {item.reserved_seller_delta}</Typography></Box>)}
      </AccordionDetails></Accordion>
      {/* Аудит правок менеджера. */}
      <Accordion><AccordionSummary expandIcon={<ExpandMoreRoundedIcon />}><Typography sx={{ fontWeight: 750 }}>Правки менеджера ({order.manager_adjustments?.length ?? 0})</Typography></AccordionSummary><AccordionDetails>
        {(order.manager_adjustments ?? []).map((item) => <Box key={item.id} className={styles.dividerRow}><Typography sx={{ fontWeight: 700 }}>{item.action}</Typography><Typography>{item.reason}</Typography><Typography className={styles.meta}>{dateTime(item.created_at)} · {item.manager?.name ?? "менеджер"} · {item.operation_id}</Typography></Box>)}
      </AccordionDetails></Accordion>
    </Box>
  );
}
