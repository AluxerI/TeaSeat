import FilterPanel from "../components/catalog-part/FilterList";
import { ProductList } from "../components/productList";
import Footer from "../ui/footer/footer";
import Header from "../ui/header/header";

export const PageCatalog = () => {
  return (
    <>
      <Header />
      <span className="filters-and-grid">
        <FilterPanel></FilterPanel>
        <ProductList></ProductList>
      </span>
      <Footer />
    </>
  );
};
