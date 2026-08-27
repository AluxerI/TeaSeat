import { createTheme } from "@mui/material/styles";
import "@fontsource-variable/manrope";
import "@fontsource-variable/unbounded";

// Палитра PWA продавца: матча + карамель + крем. Живой чайный характер при
// полной читаемости на планшете кассы (контраст текста >= 4.5:1).
export const sellerColors = {
  matcha: "#87A617",
  matchaDark: "#6B8F2F",
  matchaLight: "#A8C75A",
  caramel: "#4A3428",
  caramelDark: "#3A2C22",
  caramelHover: "#5C4033",
  cream: "#FAF5EE",
  sand: "#ECE2D4",
  textPrimary: "#3A2C22",
  textSecondary: "#8A7D6F",
  danger: "#C9564E",
  success: "#3E7C4F",
  warning: "#C98A2D",
};

export const sellerTheme = createTheme({
  palette: {
    mode: "light",
    primary: {
      main: sellerColors.matcha,
      light: sellerColors.matchaLight,
      dark: sellerColors.matchaDark,
      contrastText: "#FFFFFF",
    },
    secondary: {
      main: sellerColors.caramel,
      dark: sellerColors.caramelDark,
      light: sellerColors.caramelHover,
      contrastText: "#FFFFFF",
    },
    background: {
      default: sellerColors.cream,
      paper: "#FFFFFF",
    },
    divider: sellerColors.sand,
    text: {
      primary: sellerColors.textPrimary,
      secondary: sellerColors.textSecondary,
    },
    error: { main: sellerColors.danger },
    success: { main: sellerColors.success },
    warning: { main: sellerColors.warning },
  },
  shape: { borderRadius: 16 },
  typography: {
    fontFamily: [
      "Manrope Variable",
      "Manrope",
      "-apple-system",
      "BlinkMacSystemFont",
      "Segoe UI",
      "sans-serif",
    ].join(","),
    h1: {
      fontFamily: "Unbounded Variable, Unbounded, sans-serif",
      fontWeight: 700,
      fontSize: "1.75rem",
      lineHeight: 1.2,
    },
    h2: {
      fontFamily: "Unbounded Variable, Unbounded, sans-serif",
      fontWeight: 700,
      fontSize: "1.35rem",
      lineHeight: 1.25,
    },
    h3: {
      fontFamily: "Unbounded Variable, Unbounded, sans-serif",
      fontWeight: 700,
      fontSize: "1.1rem",
      lineHeight: 1.3,
    },
    h4: {
      fontFamily: "Unbounded Variable, Unbounded, sans-serif",
      fontWeight: 600,
      fontSize: "1rem",
    },
    h5: {
      fontFamily: "Unbounded Variable, Unbounded, sans-serif",
      fontWeight: 600,
      fontSize: "0.95rem",
    },
    h6: {
      fontFamily: "Unbounded Variable, Unbounded, sans-serif",
      fontWeight: 600,
      fontSize: "0.9rem",
    },
    // Те же размеры, что приняты в manager PWA: основной текст 18px,
    // компактные подписи/контролы 15px. Это убирает разнобой между ролями.
    body1: { fontWeight: 500, fontSize: "18px" },
    body2: { fontWeight: 500, fontSize: "15px" },
    button: { textTransform: "none", fontWeight: 700, fontSize: "15px" },
  },
  components: {
    MuiPaper: {
      styleOverrides: {
        root: {
          backgroundImage: "none",
          border: "1px solid",
          borderColor: sellerColors.sand,
          boxShadow: "0 1px 2px rgba(74, 52, 40, 0.06)",
        },
      },
    },
    MuiButton: {
      defaultProps: { disableElevation: true },
      styleOverrides: {
        root: {
          borderRadius: 12,
          padding: "10px 20px",
          fontWeight: 700,
          fontSize: "15px",
        },
        containedPrimary: {
          "&:hover": { backgroundColor: sellerColors.matchaDark },
        },
      },
    },
    MuiTextField: {
      defaultProps: { variant: "outlined", size: "small" },
      styleOverrides: {
        root: {
          "& .MuiOutlinedInput-root": {
            borderRadius: 12,
            backgroundColor: "#FFFFFF",
          },
        },
      },
    },
    MuiChip: {
      styleOverrides: {
        root: { fontWeight: 700, borderRadius: 8, fontSize: "15px" },
      },
    },
    MuiTab: {
      styleOverrides: { root: { fontSize: "15px" } },
    },
    MuiInputLabel: {
      styleOverrides: { root: { fontSize: "15px" } },
    },
    MuiFormLabel: {
      styleOverrides: { root: { fontSize: "15px" } },
    },
    MuiMenuItem: {
      styleOverrides: { root: { fontSize: "15px" } },
    },
    MuiBottomNavigationAction: {
      styleOverrides: {
        root: {
          borderRadius: 16,
          fontSize: "15px",
          // Стандартная MUI-иконка 24px; +4px лучше читается в мобильной PWA.
          "& .MuiSvgIcon-root": { fontSize: "28px" },
        },
        label: {
          fontWeight: 700,
          fontSize: "15px",
          "&.Mui-selected": { fontSize: "15px" },
        },
      },
    },
    MuiDialog: {
      styleOverrides: { paper: { borderRadius: 20 } },
    },
  },
});
