import { useState } from "react";
import { useNavigate } from "react-router-dom";
import {
  Box,
  Typography,
  TextField,
  Button,
  InputAdornment,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Divider,
  Paper,
} from "@mui/material";
import PersonOutlineIcon from "@mui/icons-material/PersonOutline";
import Inventory2OutlinedIcon from "@mui/icons-material/Inventory2Outlined";
import PlaceOutlinedIcon from "@mui/icons-material/PlaceOutlined";
import FavoriteBorderIcon from "@mui/icons-material/FavoriteBorder";
import PercentOutlinedIcon from "@mui/icons-material/PercentOutlined";
import ChatBubbleOutlineIcon from "@mui/icons-material/ChatBubbleOutline";
import LogoutIcon from "@mui/icons-material/Logout";
import CalendarTodayOutlinedIcon from "@mui/icons-material/CalendarTodayOutlined";
import AdminPanelSettingsIcon from '@mui/icons-material/AdminPanelSettings';
import SellIcon from '@mui/icons-material/Sell';
import ManageAccountsIcon from '@mui/icons-material/ManageAccounts';
import LocalShippingIcon from '@mui/icons-material/LocalShipping';

import Header from "../ui/header/header";
import Footer from "../ui/footer/Footer";
import { useAuth } from "../hooks/useAuth";
import { authApi } from "../api/authAPI";
import { translateError, extractError } from "../utils/translateError";
import type { MenuKey, MenuItem, ProfileFormState, PasswordFormState } from "../interfaces/profile";
import styles from "../scss/pages/ProfilePage.module.scss";

const MENU_ITEMS: MenuItem[] = [
  { key: "profile", label: "Профиль", icon: <PersonOutlineIcon fontSize="medium" /> },
  { key: "orders", label: "Мои заказы", icon: <Inventory2OutlinedIcon fontSize="medium" /> },
  { key: "addresses", label: "Адреса доставки", icon: <PlaceOutlinedIcon fontSize="medium" /> },
  { key: "favorites", label: "Избранное", icon: <FavoriteBorderIcon fontSize="medium" /> },
  { key: "discounts", label: "Скидки и бонусы", icon: <PercentOutlinedIcon fontSize="medium" /> },
  { key: "reviews", label: "Мои отзывы", icon: <ChatBubbleOutlineIcon fontSize="medium" /> },


];

const SECTION_CONFIG: Record<MenuKey, { title: string; subtitle: string }> = {
  profile: {
    title: "Личные данные",
    subtitle: "Управляйте информацией о себе и настройками безопасности",
  },
  orders: {
    title: "Мои заказы",
    subtitle: "История и статус ваших заказов",
  },
  addresses: {
    title: "Адреса доставки",
    subtitle: "Управляйте адресами доставки",
  },
  favorites: {
    title: "Избранное",
    subtitle: "Товары, которые вам понравились",
  },
  discounts: {
    title: "Скидки и бонусы",
    subtitle: "Ваши персональные предложения и купоны",
  },
  reviews: {
    title: "Мои отзывы",
    subtitle: "Ваши отзывы о товарах",
  }
};

const EMPTY_PROFILE: ProfileFormState = {
  fullName: "",
  email: "",
  phone: "",
  birthDate: "",
};

const EMPTY_PASSWORDS: PasswordFormState = {
  currentPassword: "",
  newPassword: "",
};

export default function ProfilePage() {
  const navigate = useNavigate();
  const { user, loading, isAdmin, isSeller, isManager, isCourier, logout, refreshUser } = useAuth();

  const [activeKey, setActiveKey] = useState<MenuKey>("profile");
  const [profile, setProfile] = useState<ProfileFormState>(EMPTY_PROFILE);
  const [passwords, setPasswords] = useState<PasswordFormState>(EMPTY_PASSWORDS);
  const [saving, setSaving] = useState(false);
  const [savingPassword, setSavingPassword] = useState(false);
  const [error, setError] = useState("");
  const [passwordError, setPasswordError] = useState("");
  const [ok, setOk] = useState("");
  const [passwordOk, setPasswordOk] = useState("");

  useState(() => {
    if (user) {
      setProfile({
        fullName: user.name,
        email: user.email,
        phone: user.phone ?? "",
        birthDate: "",
      });
    }
  });

  const handleProfileChange =
    (field: keyof ProfileFormState) =>
    (event: React.ChangeEvent<HTMLInputElement>) => {
      setProfile((prev) => ({ ...prev, [field]: event.target.value }));
    };

  const handlePasswordChange =
    (field: keyof PasswordFormState) =>
    (event: React.ChangeEvent<HTMLInputElement>) => {
      setPasswords((prev) => ({ ...prev, [field]: event.target.value }));
    };

  const handleSaveChanges = async () => {
    setError("");
    setOk("");
    setSaving(true);
    try {
      await authApi.updateProfile({
        name: profile.fullName.trim(),
        email: profile.email.trim(),
        phone: profile.phone || null,
      });
      await refreshUser();
      setOk("Профиль обновлён");
    } catch (err: any) {
      setError(translateError(extractError(err)));
    } finally {
      setSaving(false);
    }
  };

  const handleUpdatePassword = async () => {
    setPasswordError("");
    setPasswordOk("");
    if (!passwords.currentPassword || !passwords.newPassword) {
      setPasswordError("Заполните оба поля");
      return;
    }
    setSavingPassword(true);
    try {
      await authApi.changePassword({
        current_password: passwords.currentPassword,
        new_password: passwords.newPassword,
        new_password_confirmation: passwords.newPassword,
      });
      setPasswordOk("Пароль изменён");
      setPasswords(EMPTY_PASSWORDS);
    } catch (err: any) {
      setPasswordError(translateError(extractError(err)));
    } finally {
      setSavingPassword(false);
    }
  };

  const handleLogout = async () => {
    await logout();
    navigate("/");
  };

  const handleAdmin = () =>{
    navigate("/admin");
  };
  
  const handleSeller = ()=>{
    navigate("/seller");
  }

  // ── Section: Profile ───────────────────────────────────────────────────────

  const renderProfile = () => (
    <>
      <Box className={styles.formGrid}>
        <TextField
          label="ФИО"
          value={profile.fullName}
          onChange={handleProfileChange("fullName")}
          className={styles.field}
          variant="outlined"
          fullWidth
        />
        <TextField
          label="Email"
          type="email"
          value={profile.email}
          onChange={handleProfileChange("email")}
          className={styles.field}
          variant="outlined"
          fullWidth
        />
        <TextField
          label="Телефон"
          value={profile.phone}
          onChange={handleProfileChange("phone")}
          className={styles.field}
          variant="outlined"
          fullWidth
        />
        <TextField
          label="Дата рождения"
          type="date"
          value={profile.birthDate}
          onChange={handleProfileChange("birthDate")}
          className={styles.field}
          variant="outlined"
          fullWidth
          InputLabelProps={{ shrink: true }}
          InputProps={{
            endAdornment: (
              <InputAdornment position="end">
                <CalendarTodayOutlinedIcon fontSize="small" className={styles.calendarIcon} />
              </InputAdornment>
            ),
          }}
        />
      </Box>

      <Divider className={styles.sectionDivider} />

      <Typography component="h3" className={styles.sectionTitle}>
        Смена пароля
      </Typography>

      {passwordError && <Typography className={styles.fieldError}>{passwordError}</Typography>}
      {passwordOk && <Typography className={styles.fieldOk}>{passwordOk}</Typography>}

      <Box className={styles.passwordGrid}>
        <TextField
          label="Текущий пароль"
          type="password"
          value={passwords.currentPassword}
          onChange={handlePasswordChange("currentPassword")}
          className={styles.field}
          variant="outlined"
          fullWidth
        />
        <TextField
          label="Новый пароль"
          type="password"
          value={passwords.newPassword}
          onChange={handlePasswordChange("newPassword")}
          className={styles.field}
          variant="outlined"
          fullWidth
        />
        <Button
          variant="outlined"
          className={styles.secondaryButton}
          onClick={handleUpdatePassword}
          disabled={savingPassword}
        >
          {savingPassword ? "Смена…" : "Обновить пароль"}
        </Button>
      </Box>
    </>
  );

  // ── Placeholder sections ───────────────────────────────────────────────────

  const sectionIcon = (key: MenuKey) => {
    switch (key) {
      case "orders":
        return <Inventory2OutlinedIcon className={styles.placeholderIcon} />;
      case "addresses":
        return <PlaceOutlinedIcon className={styles.placeholderIcon} />;
      case "favorites":
        return <FavoriteBorderIcon className={styles.placeholderIcon} />;
      case "discounts":
        return <PercentOutlinedIcon className={styles.placeholderIcon} />;
      case "reviews":
        return <ChatBubbleOutlineIcon className={styles.placeholderIcon} />;

      default:
        return null;
    }
  };

  const sectionAction = (key: MenuKey) => {
    switch (key) {
      case "orders":
        return (
          <Button variant="contained" className={styles.primaryButton} href="/catalog">
            Перейти в каталог
          </Button>
        );
      case "favorites":
        return (
          <Button variant="contained" className={styles.primaryButton} href="/catalog">
            В каталог
          </Button>
        );
      case "reviews":
        return (
          <Button variant="contained" className={styles.primaryButton} href="/catalog">
            В каталог
          </Button>
        );
      default:
        return null;
    }
  };

  const sectionDescription = (key: MenuKey): string => {
    switch (key) {
      case "orders":
        return "У вас пока нет оформленных заказов.";
      case "addresses":
        return "Нет сохранённых адресов доставки.";
      case "favorites":
        return "Список избранного пуст.";
      case "discounts":
        return "У вас нет активных скидок или купонов.";
      case "reviews":
        return "Вы ещё не оставляли отзывы о товарах.";
      default:
        return "";
    }
  };

  const renderPlaceholder = (key: MenuKey) => (
    <Paper className={styles.placeholderCard} elevation={0}>
      {sectionIcon(key)}
      <Typography className={styles.placeholderDesc}>{sectionDescription(key)}</Typography>
      {sectionAction(key)}
    </Paper>
  );

  // ── Section router ────────────────────────────────────────────────────────

  const renderContent = () => {
    const cfg = SECTION_CONFIG[activeKey];

    const showSaveButton = activeKey === "profile";

    return (
      <>
        <Box className={styles.contentHeader}>
          <Box>
            <Typography component="h1" className={styles.contentTitle}>
              {cfg.title}
            </Typography>
            <Typography className={styles.contentSubtitle}>
              {cfg.subtitle}
            </Typography>
          </Box>

          {showSaveButton && (
            <Button
              variant="contained"
              className={styles.primaryButton}
              onClick={handleSaveChanges}
              disabled={saving}
              disableElevation
            >
              {saving ? "Сохранение…" : "Сохранить изменения"}
            </Button>
          )}
        </Box>

        {error && <Typography className={styles.fieldError}>{error}</Typography>}
        {ok && <Typography className={styles.fieldOk}>{ok}</Typography>}

        {activeKey === "profile" ? renderProfile() : renderPlaceholder(activeKey)}
      </>
    );
  };

  // ── Loading / Guest ────────────────────────────────────────────────────────

  const sidebar = (
    <Box component="aside" className={styles.sidebar}>
      <Typography component="h2" className={styles.sidebarTitle}>
        Мой кабинет
      </Typography>

      <List className={styles.menuList} disablePadding>
        {MENU_ITEMS.map((item) => (
          <ListItemButton
            key={item.key}
            selected={activeKey === item.key}
            onClick={() => setActiveKey(item.key)}
            className={styles.menuItem}
            classes={{ selected: styles.menuItemActive }}
            disableRipple={true}
          >
            <ListItemIcon className={styles.menuIcon}>{item.icon}</ListItemIcon>
            <ListItemText
              primary={item.label}
              primaryTypographyProps={{ className: styles.menuLabel }}
            />
          </ListItemButton>
        ))}
      </List>
      {isAdmin && (
        <>
          <Divider className={styles.sidebarDivider} />
          <ListItemButton className={styles.adminItem} disableRipple onClick={() => navigate("/admin")}>
            <ListItemIcon className={styles.adminIcon}>
              <AdminPanelSettingsIcon fontSize="small" />
            </ListItemIcon>
            <ListItemText
              primary="Админка"
              primaryTypographyProps={{ className: styles.adminLabel }}
            />
          </ListItemButton>
        </>
      )}
      {isSeller && (
        <>
          <Divider className={styles.sidebarDivider} />
          <ListItemButton className={styles.sellItem} disableRipple onClick={() => navigate("/seller")}>
            <ListItemIcon className={styles.sellIcon}>
              <SellIcon fontSize="small" />
            </ListItemIcon>
            <ListItemText
              primary="Продажи"
              primaryTypographyProps={{ className: styles.sellLabel }}
            />
          </ListItemButton>
        </>
      )}
      {isManager && (
        <>
          <Divider className={styles.sidebarDivider} />
          <ListItemButton className={styles.managerItem} disableRipple onClick={() => navigate("/manager")}>
            <ListItemIcon className={styles.managerIcon}>
              <ManageAccountsIcon fontSize="small" />
            </ListItemIcon>
            <ListItemText
              primary="Менеджер"
              primaryTypographyProps={{ className: styles.managerLabel }}
            />
          </ListItemButton>
        </>
      )}
      {isCourier && (
        <>
          <Divider className={styles.sidebarDivider} />
          <ListItemButton className={styles.courierItem} disableRipple onClick={() => navigate("/courier")}>
            <ListItemIcon className={styles.courierIcon}>
              <LocalShippingIcon fontSize="small" />
            </ListItemIcon>
            <ListItemText
              primary="Доставка"
              primaryTypographyProps={{ className: styles.courierLabel }}
            />
          </ListItemButton>
        </>
      )}
      {user && (
        <>
          <Divider className={styles.sidebarDivider} />
          <ListItemButton className={styles.logoutItem} disableRipple onClick={handleLogout}>
            <ListItemIcon className={styles.logoutIcon}>
              <LogoutIcon fontSize="small" />
            </ListItemIcon>
            <ListItemText
              primary="Выйти"
              primaryTypographyProps={{ className: styles.logoutLabel }}
            />
          </ListItemButton>
        </>
      )}
    </Box>
  );

  if (loading) {
    return (
      <Box className={styles.page}>
        <Header />
        <Box className={styles.cabinet}>
          {sidebar}
          <Box component="section" className={styles.content}>
            <Typography className={styles.loadingText}>Загрузка…</Typography>
          </Box>
        </Box>
        <Footer />
      </Box>
    );
  }

  if (!user) {
    return (
      <Box className={styles.page}>
        <Header />
        <Box className={styles.cabinet}>
          {sidebar}
          <Box component="section" className={styles.content}>
            <Typography className={styles.loadingText}>Необходимо войти в аккаунт</Typography>
          </Box>
        </Box>
        <Footer />
      </Box>
    );
  }

  // ── Render ─────────────────────────────────────────────────────────────────

  return (
    <Box className={styles.page}>
      <Header />
      <Box className={styles.cabinet}>
        {sidebar}

        <Box component="section" className={styles.content}>
          {renderContent()}
        </Box>
      </Box>
      <Footer />
    </Box>
  );
}
