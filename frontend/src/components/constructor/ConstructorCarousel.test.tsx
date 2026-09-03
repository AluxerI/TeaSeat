import { useState } from "react";
import { act, fireEvent, render, screen, within } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import ConstructorCarousel, { initialCatalogBrowse } from "./ConstructorCarousel";
import { useConstructorDrag } from "../../hooks/useConstructorDrag";
import { testBox, testSize } from "./testFixtures";
import type { ConstructorProductSize } from "../../interfaces/giftConstructor";
import ConstructorCatalog from "./ConstructorCatalog";

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
    expect(screen.getByText("Форматы 13–18 из 18")).toBeInTheDocument();
    fireEvent.keyDown(grid, { key: "Home" });
    expect(screen.getByText("Форматы 1–12 из 18")).toBeInTheDocument();
  });

  it("показывает размер картинкой относительно дна", () => {
    render(<Harness cellSizeMm={null} />);
    const diagram = screen.getByRole("img", { name: "Занимает 2 на 1 клетки из дна 4 на 3" });
    expect(diagram.querySelectorAll("rect")).toHaveLength(2);
    expect(diagram.querySelectorAll("line")).toHaveLength(5);
    expect(screen.queryByText(/кл\.|мм/)).not.toBeInTheDocument();
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

  it("показывает оставшийся онлайн-остаток и запрещает лишний повтор", () => {
    const limited = {
      ...sizes[0],
      product: { ...sizes[0].product, total_quantity: 100 },
    };
    render(<Harness options={[limited]} selected={[limited.id, limited.id]} />);

    const card = screen.getByRole("article", { name: "Ассам, 50 г" });
    expect(within(card).getByText("Доступно: 0 г")).toHaveAttribute("data-stock-empty", "true");
    expect(card).toHaveAttribute("data-unavailable", "true");
    expect(within(card).getByRole("button", { name: "Добавить Ассам, 50 г" })).toBeDisabled();
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
    // Только активная дюжина доступна клавиатуре и скринридеру.
    expect(screen.getAllByRole("article")).toHaveLength(12);
    expect(screen.getAllByRole("article", { hidden: true })).toHaveLength(25);
    expect(screen.getByText("Форматы 1–12 из 25")).toBeInTheDocument();
    const previous = screen.getByRole("button", { name: "Предыдущая страница форматов" });
    const next = screen.getByRole("button", { name: "Следующая страница форматов" });
    expect(previous.className).not.toBe(next.className);
    fireEvent.click(next);
    expect(screen.getByText("Форматы 13–24 из 25")).toBeInTheDocument();
    expect(previous).toBeEnabled();
    expect(screen.getByText("Страница 2 из 3")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Предыдущая страница" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Следующая страница" })).not.toBeInTheDocument();
    expect(screen.queryByText(/Тяните карточку/)).not.toBeInTheDocument();
  });

  it("держит Подробнее и Добавить в одной горизонтальной группе", () => {
    render(<Harness />);
    const card = screen.getByRole("article", { name: "Ассам, 50 г" });
    const details = within(card).getByRole("button", { name: "Подробнее о Ассам" });
    const add = within(card).getByRole("button", { name: "Добавить Ассам, 50 г" });
    expect(details.parentElement).toBe(add.parentElement);
    expect(card).toContainElement(details);
    expect(details).toHaveTextContent("");
    expect(add).toHaveTextContent("");
    expect(within(add).getByTestId("AddShoppingCartRoundedIcon")).toBeInTheDocument();
    expect(within(details).getByTestId("VisibilityOutlinedIcon")).toBeInTheDocument();
  });

  it("открывает компактные подробности без дополнительного описания", () => {
    const rich = { ...sizes[0], product: { ...sizes[0].product, description: "Длинное описание", ingredients: "Состав" } };
    render(<Harness options={[rich]} />);
    fireEvent.click(screen.getByRole("button", { name: "Подробнее о Ассам" }));
    expect(screen.getByRole("dialog")).toBeInTheDocument();
    expect(screen.queryByText("Длинное описание")).not.toBeInTheDocument();
    expect(screen.queryByText("Состав")).not.toBeInTheDocument();
    expect(within(screen.getByRole("dialog")).getByRole("img", { name: /Занимает 2 на 1/ })).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Закрыть подробности" }));
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });
});

describe("страницы простого каталога", () => {
  it("оставляет номера страниц без нижних стрелок", () => {
    const many = Array.from({ length: 21 }, (_, index) => ({ ...testSize(300 + index), label: `${index + 1} г` }));
    render(<ConstructorCatalog title="Чай" box={testBox} options={many} selected={[]} limit={30} onAdd={vi.fn()} />);
    expect(screen.queryByText(/← Назад|Далее →/)).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Страница 1" })).toHaveAttribute("aria-current", "page");
    fireEvent.click(screen.getByRole("button", { name: "Страница 2" }));
    expect(screen.getByLabelText("Количество Ассам, 21 г")).toBeInTheDocument();
    expect(screen.getAllByRole("listitem")).toHaveLength(1);
  });
});
