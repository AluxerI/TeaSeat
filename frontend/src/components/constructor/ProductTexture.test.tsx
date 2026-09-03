import { fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import ProductTexture from "./ProductTexture";
import { testSize } from "./testFixtures";

describe("ProductTexture", () => {
  it("uses the packaging template and keeps the product identifiable", () => {
    const size = {
      ...testSize(1),
      packaging_template: {
        id: 7,
        code: "tea-pouch",
        name: "Пакет чая",
        kind: "pouch",
        image_url: "https://backend.test/storage/constructor-packaging/pouch.png",
      },
    };

    const { container } = render(<ProductTexture size={size} />);

    expect(container.querySelector("img")).toHaveAttribute(
      "src",
      "/storage/constructor-packaging/pouch.png",
    );
    expect(screen.getByText("Ассам")).toBeInTheDocument();
  });

  it("falls back to the product image when the packaging asset fails", () => {
    const base = testSize(2);
    const size = {
      ...base,
      packaging_template: {
        id: 8,
        code: "broken-pouch",
        name: "Пакет",
        kind: "pouch",
        image_url: "/storage/constructor-packaging/missing.png",
      },
      product: { ...base.product, image: "https://backend.test/storage/products/tea.png" },
    };
    const { container } = render(<ProductTexture size={size} />);
    const image = container.querySelector("img");

    expect(image).not.toBeNull();
    fireEvent.error(image!);
    expect(image).toHaveAttribute("src", "/storage/products/tea.png");
  });
});
