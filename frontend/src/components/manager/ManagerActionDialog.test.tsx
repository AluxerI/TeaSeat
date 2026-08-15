import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { StaffApiError } from "../../staff/errors";
import { ManagerActionDialog } from "./ManagerActionDialog";

describe("ManagerActionDialog", () => {
  it("показывает 422 возле соответствующего поля", async () => {
    const onSubmit = vi.fn().mockRejectedValue(new StaffApiError(
      "validation",
      "Проверьте данные",
      422,
      undefined,
      { reason: ["Причина должна содержать минимум 3 символа"] },
    ));
    render(<ManagerActionDialog open title="Отмена" fieldName="reason" fieldLabel="Причина" confirmLabel="Отменить" onClose={vi.fn()} onSubmit={onSubmit} />);
    fireEvent.change(screen.getByLabelText("Причина"), { target: { value: "нет" } });
    fireEvent.click(screen.getByRole("button", { name: "Отменить" }));
    expect(await screen.findByText("Причина должна содержать минимум 3 символа")).toBeVisible();
  });

  it("после 429 показывает countdown и блокирует повтор", async () => {
    const onSubmit = vi.fn().mockRejectedValue(new StaffApiError("throttled", "Подождите", 429, undefined, undefined, 5_000));
    render(<ManagerActionDialog open title="Заметка" fieldName="comment" fieldLabel="Комментарий" confirmLabel="Сохранить" onClose={vi.fn()} onSubmit={onSubmit} />);
    fireEvent.change(screen.getByLabelText("Комментарий"), { target: { value: "Проверено" } });
    fireEvent.click(screen.getByRole("button", { name: "Сохранить" }));
    expect(await screen.findByText(/Повтор через 5 сек/)).toBeVisible();
    await waitFor(() => expect(screen.getByRole("button", { name: "Сохранить" })).toBeDisabled());
  });

  it("не прячет 422 без errors map", async () => {
    const onSubmit = vi.fn().mockRejectedValue(new StaffApiError("validation", "Интервал больше недоступен", 422));
    render(<ManagerActionDialog open title="Перенос" fieldName="reason" fieldLabel="Причина" confirmLabel="Сохранить" onClose={vi.fn()} onSubmit={onSubmit} />);
    fireEvent.change(screen.getByLabelText("Причина"), { target: { value: "Клиент попросил" } });
    fireEvent.click(screen.getByRole("button", { name: "Сохранить" }));
    expect(await screen.findByText("Интервал больше недоступен")).toBeVisible();
  });
});
