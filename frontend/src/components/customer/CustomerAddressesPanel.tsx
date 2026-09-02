import { useCallback, useEffect, useState } from "react";
import { Alert, Box, Button, CircularProgress, TextField, Typography } from "@mui/material";
import AddLocationAltOutlinedIcon from "@mui/icons-material/AddLocationAltOutlined";
import HomeWorkOutlinedIcon from "@mui/icons-material/HomeWorkOutlined";
import RefreshRoundedIcon from "@mui/icons-material/RefreshRounded";

import { addressApi } from "../../api/addressAPI";
import type { Address } from "../../interfaces/checkout";
import { extractError, translateError } from "../../utils/translateError";
import styles from "../../scss/pages/CustomerAddressesPanel.module.scss";

const EMPTY_ADDRESS = { city: "", street: "", postal_code: "" };

function addressText(address: Address): string {
  return address.full_address || [address.postal_code, address.city, address.street].filter(Boolean).join(", ");
}

export default function CustomerAddressesPanel() {
  const [addresses, setAddresses] = useState<Address[]>([]);
  const [form, setForm] = useState(EMPTY_ADDRESS);
  const [formOpen, setFormOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  const loadAddresses = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const response = await addressApi.getAddresses();
      setAddresses(response.addresses);
      setFormOpen(response.addresses.length === 0);
    } catch (err) {
      setError(translateError(extractError(err)));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void loadAddresses(); }, [loadAddresses]);

  const saveAddress = async () => {
    const payload = {
      city: form.city.trim(),
      street: form.street.trim(),
      postal_code: form.postal_code.trim(),
    };
    if (!payload.city || !payload.street || !payload.postal_code) {
      setError("Заполните город, улицу с домом и почтовый индекс");
      return;
    }

    setSaving(true);
    setError("");
    setNotice("");
    try {
      const response = await addressApi.createAddress(payload);
      setAddresses((current) => [...current.filter((address) => address.id !== response.address.id), response.address]);
      setForm(EMPTY_ADDRESS);
      setFormOpen(false);
      setNotice("Адрес доставки сохранён");
    } catch (err) {
      setError(translateError(extractError(err)));
    } finally {
      setSaving(false);
    }
  };

  return (
    <section className={styles.panel} aria-labelledby="delivery-addresses-title">
      <header className={styles.header}>
        <span className={styles.headerIcon} aria-hidden="true"><AddLocationAltOutlinedIcon /></span>
        <div className={styles.headerCopy}>
          <Typography component="h2" id="delivery-addresses-title" className={styles.title}>Адреса доставки</Typography>
          <Typography className={styles.subtitle}>Сохранённые адреса можно выбрать при оформлении заказа.</Typography>
        </div>
        {!formOpen && (
          <Button variant="outlined" className={styles.addButton} onClick={() => { setFormOpen(true); setNotice(""); }}>
            Добавить адрес
          </Button>
        )}
      </header>

      {error && <Alert severity="error" className={styles.alert}>{error}</Alert>}
      {notice && <Alert severity="success" className={styles.alert} role="status">{notice}</Alert>}

      {loading ? (
        <div className={styles.state} role="status"><CircularProgress size={28} /><span>Загружаем адреса…</span></div>
      ) : (
        <>
          {addresses.length > 0 ? (
            <div className={styles.list} aria-label="Сохранённые адреса доставки">
              {addresses.map((address) => (
                <article key={address.id} className={styles.card}>
                  <span className={styles.cardIcon} aria-hidden="true"><HomeWorkOutlinedIcon /></span>
                  <div>
                    <Typography component="h3" className={styles.cardTitle}>{address.city}</Typography>
                    <address className={styles.address}>{addressText(address)}</address>
                  </div>
                </article>
              ))}
            </div>
          ) : !formOpen ? (
            <div className={styles.state}><span>Сохранённых адресов пока нет.</span></div>
          ) : null}

          {formOpen && (
            <Box
              component="form"
              className={styles.form}
              onSubmit={(event) => { event.preventDefault(); void saveAddress(); }}
            >
              <Typography component="h3" className={styles.formTitle}>Новый адрес</Typography>
              <TextField required label="Город" autoComplete="address-level2" value={form.city} onChange={(event) => setForm((current) => ({ ...current, city: event.target.value }))} />
              <TextField required label="Улица, дом, квартира" autoComplete="street-address" value={form.street} onChange={(event) => setForm((current) => ({ ...current, street: event.target.value }))} />
              <TextField required label="Почтовый индекс" autoComplete="postal-code" value={form.postal_code} onChange={(event) => setForm((current) => ({ ...current, postal_code: event.target.value }))} />
              <div className={styles.actions}>
                {addresses.length > 0 && <Button disabled={saving} onClick={() => { setFormOpen(false); setError(""); setForm(EMPTY_ADDRESS); }}>Отмена</Button>}
                <Button type="submit" variant="contained" disabled={saving}>
                  {saving ? "Сохраняем…" : "Сохранить адрес"}
                </Button>
              </div>
            </Box>
          )}
        </>
      )}

      {!loading && error && (
        <Button className={styles.retryButton} startIcon={<RefreshRoundedIcon />} onClick={() => void loadAddresses()}>
          Повторить загрузку
        </Button>
      )}
    </section>
  );
}
