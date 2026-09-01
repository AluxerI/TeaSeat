import { Link } from "react-router-dom";
import styles from "../../scss/components/MarketingHeading.module.scss";

interface Props {
  eyebrow: string;
  title: string;
  text?: string;
  action?: { label: string; to: string };
  align?: "left" | "center";
  level?: 1 | 2;
}

export default function MarketingHeading({ eyebrow, title, text, action, align = "left", level = 1 }: Props) {
  const Title = level === 1 ? "h1" : "h2";
  return (
    <header className={styles.heading} data-align={align}>
      <span className={styles.eyebrow}>{eyebrow}</span>
      <Title>{title}</Title>
      {text && <p>{text}</p>}
      {action && <Link className={styles.action} to={action.to}>{action.label} <span aria-hidden="true">→</span></Link>}
    </header>
  );
}
