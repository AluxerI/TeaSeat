import { Box, Typography, type SvgIconProps } from "@mui/material";
import type { ReactElement } from "react";
import styles from "../../scss/pages/CourierShared.module.scss";

export function CourierEmptyState({
  icon,
  title,
  description,
}: {
  icon: ReactElement<SvgIconProps>;
  title: string;
  description: string;
}) {
  return (
    <Box className={styles.emptyState}>
      <Box className={styles.emptyIcon}>{icon}</Box>
      <Typography component="h2" className={styles.emptyTitle}>
        {title}
      </Typography>
      <Typography className={styles.emptyDescription}>{description}</Typography>
    </Box>
  );
}
