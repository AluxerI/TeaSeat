import { useState } from "react";
import { act, fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import ConstructorCarousel, { initialCatalogBrowse } from "./ConstructorCarousel";
import { useConstructorDrag } from "../../hooks/useConstructorDrag";
import { testBox, testSize } from "./testFixtures";
import type { ConstructorProductSize } from "../../interfaces/giftConstructor";

const sizes: ConstructorProductSize[] = [testSize(11, "tea", 2, 1), { ...testSize(12), label: "100 г" },
  testSize(21, "sweet"), { ...testSize(31), constructor_role: "general", product: { ...testSize(31).product, name: "Открытка" } }];
function Harness({ options = sizes, selected = [], cellSizeMm = 10 }: { options?: ConstructorProductSize[]; selected?: number[]; cellSizeMm?: number | null }) {
  const [browse, onBrowse] = useState(() => initialCatalogBrowse(options));
  const drag = useConstructorDrag({ box: testBox, sizes: options, items: [], onSelect: vi.fn(), onCommit: vi.fn(), onError: vi.fn() });
  return <ConstructorCarousel box={testBox} options={options} selected={selected} limit={40} onAdd={vi.fn()} drag={drag} browse={browse} onBrowse={onBrowse} cellSizeMm={cellSizeMm} />;
}

describe("сеточная карусель конструктора", () => {
  it("показывает только реальные роли backend и сетку карточек выбранного типа", () => {
    render(<Harness />);
    expect(screen.getAllByRole("tab")).toHaveLength(3);
    expect(screen.getByRole("tab", { name: /Чай/ })).toHaveAttribute("aria-selected", "true");
    expect(screen.getAllByRole("article")).toHaveLength(2);
    expect(screen.getByRole("article", { name: "Ассам, 50 г" })).toBeInTheDocument();
    expect(screen.getByRole("article", { name: "Ассам, 100 г" })).toBeInTheDocument();
    fireEvent.click(screen.getByRole("tab", { name: /Другое/ }));
    expect(screen.getByRole("article", { name: "Открытка, 50 г" })).toBeInTheDocument();
    expect(screen.queryByRole("article", { name: /Ассам/ })).not.toBeInTheDocument();
  });

  it("выбор карточки делает её активной и помнит формат каждого типа", () => {
    render(<Harness />);
    expect(screen.getByRole("article", { name: "Ассам, 100 г" })).toHaveAttribute("data-active", "false");
    act(() => screen.getByRole("article", { name: "Ассам, 100 г" }).focus());
    expect(screen.getByRole("article", { name: "Ассам, 100 г" })).toHaveAttribute("data-active", "true");
    fireEvent.click(screen.getByRole("tab", { name: /Сладости/ }));
    fireEvent.click(screen.getByRole("tab", { name: /Чай/ }));
    expect(screen.getByRole("article", { name: "Ассам, 100 г" })).toHaveAttribute("data-active", "true");
  });

  it("вкладки и сетка доступны клавиатурой", () => {
    const many = Array.from({ length: 18 }, (_, i) => ({ ...testSize(100 + i), label: `${i + 1} г` }));
    render(<Harness options={many} />);
    const tea = screen.getByRole("tab", { name: /Чай/ });
    tea.focus();
    fireEvent.keyDown(tea, { key: "ArrowRight" });
    expect(screen.getByRole("tab", { name: /Сладости/ })).toHaveFocus();
    fireEvent.keyDown(document.activeElement!, { key: "Home" });
    const grid = screen.getByRole("grid", { name: /Сетка форматов/ });
    grid.focus();
    fireEvent.keyDown(grid, { key: "ArrowRight" });
    expect(screen.getByText("Форматы 10–18 из 18")).toBeInTheDocument();
    fireEvent.keyDown(grid, { key: "Home" });
    expect(screen.getByText("Форматы 1–9 из 18")).toBeInTheDocument();
  });

  it("показывает реальный след без выдуманных миллиметров", () => {
    render(<Harness cellSizeMm={null} />);
    expect(screen.getByText("2 × 1 кл.")).toBeInTheDocument();
    expect(screen.queryByText(/мм/)).not.toBeInTheDocument();
  });

  it("пустой поиск не ломает индекс", () => {
    render(<Harness />);
    const search = screen.getByRole("searchbox");
    fireEvent.change(search, { target: { value: "нет такого" } });
    expect(screen.queryByRole("article")).not.toBeInTheDocument();
    expect(screen.getByText(/В этом типе ничего не найдено/)).toBeInTheDocument();
    fireEvent.change(search, { target: { value: "" } });
    expect(screen.getAllByRole("article")).toHaveLength(2);
  });

  it("при обновлении options удалённый формат заменяется первым доступным", () => {
    const { rerender } = render(<Harness />);
    act(() => screen.getByRole("article", { name: "Ассам, 100 г" }).focus());
    rerender(<Harness options={sizes.filter((size) => size.id !== 12)} />);
    expect(screen.getByRole("article", { name: "Ассам, 50 г" })).toHaveAttribute("data-active", "true");
  });

  it("не подставляет моки в пустую вкладку", () => {
    render(<Harness options={[]} />);
    expect(screen.getAllByRole("tab")).toHaveLength(3);
    expect(screen.getByText(/В этом типе пока нет доступных форматов/)).toBeInTheDocument();
    expect(screen.queryByRole("article")).not.toBeInTheDocument();
  });

  it("сразу выбирает непустой тип, когда чая нет", () => {
    render(<Harness options={[sizes[2]]} />);
    expect(screen.getByRole("tab", { name: /Сладости/ })).toHaveAttribute("aria-selected", "true");
    expect(screen.getByRole("article", { name: "Пастила, 50 г" })).toBeInTheDocument();
  });

  it("при лимите добавление заблокировано, но просмотр сети доступен", () => {
    render(<Harness selected={Array(40).fill(11)} />);
    expect(screen.getByRole("button", { name: "Добавить Ассам, 50 г" })).toBeDisabled();
    expect(screen.getByText(/Достигнут лимит 40 позиций/)).toBeInTheDocument();
  });

  it("не интерпретирует названия и поиск как HTML или SVG", () => {
    const unsafe = '<svg onload="alert(1)">';
    const { container } = render(<Harness options={[{ ...sizes[0], product: { ...sizes[0].product, name: unsafe } }]} />);
    expect(screen.getByText(unsafe)).toBeInTheDocument();
    fireEvent.change(screen.getByRole("searchbox"), { target: { value: unsafe } });
    expect(screen.getAllByRole("article")).toHaveLength(1);
    expect(container.querySelector("[onload]")).toBeNull();
  });

  it("показывает все карточки в ленте и листает по страницам сетки", () => {
    const many = Array.from({ length: 25 }, (_, i) => ({ ...testSize(100 + i), label: `${i + 1} г` }));
    render(<Harness options={many} />);
    // Вся лента рендерится в DOM (нужно для горизонтального скролла), видима страница из GRID_COLS^2 карточек.
    expect(screen.getAllByRole("article")).toHaveLength(25);
    expect(screen.getByText("Форматы 1–9 из 25")).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Следующая страница" }));
    expect(screen.getByText("Форматы 10–18 из 25")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Предыдущая страница" })).toBeEnabled();
  });
});
