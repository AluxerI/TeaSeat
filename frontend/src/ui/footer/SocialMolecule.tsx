import React, { useEffect, useRef, useState, useCallback } from 'react';
import styles from './SocialMolecule.module.scss';

interface SocialItem {
  id: string;
  label: string;
  color: string;
  href: string;
  angle: number;
  dist: number;
  icon: React.ReactNode;
}

const TgIcon = () => (
  <svg viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <path d="M21 4L2 10.5l6.5 2L17 7l-6.5 7 7 4z" />
    <path d="M8.5 12.5L10 18l3-4" />
  </svg>
);

const VkIcon = () => (
  <svg viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
    <path d="M20.436 4H3.564C3.253 4 3 4.253 3 4.564v14.872c0 .311.253.564.564.564h16.872c.311 0 .564-.253.564-.564V4.564C21 4.253 20.747 4 20.436 4zm-2.467 10.143h-1.498c-.567 0-.74-.452-1.755-1.478-.882-.865-1.27-.982-1.487-.982-.302 0-.389.086-.389.503v1.348c0 .36-.115.576-1.066.576-1.57 0-3.312-.95-4.538-2.72C5.568 9.406 5.2 7.873 5.2 7.527c0-.216.086-.418.503-.418h1.499c.374 0 .517.173.661.576.73 2.1 1.943 3.942 2.446 3.942.187 0 .273-.086.273-.56V9.234c-.058-1.007-.59-1.093-.59-1.452 0-.173.143-.36.374-.36h2.36c.316 0 .43.173.43.546v2.937c0 .316.143.43.23.43.187 0 .345-.114.69-.46 1.065-1.194 1.826-3.033 1.826-3.033.1-.216.273-.417.647-.417h1.499c.45 0 .548.23.45.546-.187.863-2.015 3.452-2.015 3.452-.158.258-.215.374 0 .661.158.216.676.661.99 1.065.633.777.93 1.437.676 1.953z" />
  </svg>
);

const WaIcon = () => (
  <svg viewBox="0 0 24 24" fill="white">
    <path d="M17.5 14.4c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.76-1.66-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51-.17-.01-.37-.01-.57-.01s-.52.07-.79.37c-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.31 1.27.49 1.7.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35z" />
    <path d="M12 2C6.48 2 2 6.48 2 12c0 1.9.52 3.67 1.44 5.17L2 22l4.98-1.31A9.95 9.95 0 0012 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18a7.96 7.96 0 01-4.06-1.11l-.29-.17-3.02.79.8-2.95-.19-.3A7.96 7.96 0 014 12c0-4.42 3.58-8 8-8s8 3.58 8 8-3.58 8-8 8z" />
  </svg>
);

const MailIcon = () => (
  <svg viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <rect x="2" y="4" width="20" height="16" rx="2" />
    <polyline points="2,4 12,14 22,4" />
  </svg>
);

const ShareIcon = () => (
  <svg viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.2" strokeLinecap="round">
    <circle cx="18" cy="5" r="2.5" />
    <circle cx="6" cy="12" r="2.5" />
    <circle cx="18" cy="19" r="2.5" />
    <line x1="8.4" y1="13.4" x2="15.6" y2="17.6" />
    <line x1="15.6" y1="6.4" x2="8.4" y2="10.6" />
  </svg>
);

const SOCIALS: SocialItem[] = [
  { id: 'tg',   label: 'Telegram',   color: '#229ED9', href: 'https://t.me/',             angle: -135, dist: 115, icon: <TgIcon />   },
  { id: 'vk',   label: 'ВКонтакте', color: '#4680C2', href: 'https://vk.com/',            angle: -45,  dist: 115, icon: <VkIcon />   },
  { id: 'wa',   label: 'WhatsApp',   color: '#25D366', href: 'https://wa.me/',             angle:  135, dist: 115, icon: <WaIcon />   },
  { id: 'mail', label: 'Почта',      color: '#E05A3A', href: 'mailto:info@teacoffee.ru',   angle:  45,  dist: 115, icon: <MailIcon /> },
];

interface NodeState {
  x: number;
  y: number;
  tx: number;
  ty: number;
  cx: number;
  cy: number;
  t: number;
}

const lerp = (a: number, b: number, t: number) => a + (b - a) * t;

const SocialMolecule: React.FC = () => {
  const [open, setOpen] = useState(false);
  const sceneRef  = useRef<HTMLDivElement>(null);
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const btnRef    = useRef<HTMLButtonElement>(null);
  const nodesRef  = useRef<NodeState[]>([]);
  const nodeEls   = useRef<HTMLDivElement[]>([]);
  const rafRef    = useRef<number>(0);
  const openRef   = useRef(false);

  openRef.current = open;

  const getCenter = useCallback(() => {
    const r = sceneRef.current?.getBoundingClientRect();
    return r ? { cx: r.width / 2, cy: r.height / 2 } : { cx: 0, cy: 0 };
  }, []);

  const buildTargets = useCallback(() => {
    const { cx, cy } = getCenter();
    return SOCIALS.map(s => {
      const rad = (s.angle * Math.PI) / 180;
      return {
        tx: cx + Math.cos(rad) * s.dist,
        ty: cy + Math.sin(rad) * s.dist,
        cx, cy,
      };
    });
  }, [getCenter]);

  useEffect(() => {
    const { cx, cy } = getCenter();
    nodesRef.current = SOCIALS.map(() => ({
      x: cx, y: cy, tx: cx, ty: cy, cx, cy, t: 0,
    }));
  }, [getCenter]);

  useEffect(() => {
    const onResize = () => {
      const canvas = canvasRef.current;
      const scene  = sceneRef.current;
      if (!canvas || !scene) return;
      const r = scene.getBoundingClientRect();
      canvas.width  = r.width;
      canvas.height = r.height;
      const targets = buildTargets();
      nodesRef.current.forEach((n, i) => {
        Object.assign(n, targets[i]);
        if (!openRef.current) { n.x = n.cx; n.y = n.cy; }
      });
    };
    onResize();
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, [buildTargets]);

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (openRef.current && sceneRef.current && !sceneRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  const drawLines = useCallback(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    const { cx, cy } = getCenter();
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    nodesRef.current.forEach(n => {
      if (n.t < 0.01) return;
      const a = Math.min(n.t, 1) * 0.7;

      ctx.beginPath();
      ctx.arc(cx, cy, 4, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(210,190,160,${a * 0.9})`;
      ctx.fill();

      const grad = ctx.createLinearGradient(cx, cy, n.x, n.y);
      grad.addColorStop(0, `rgba(210,190,160,${a})`);
      grad.addColorStop(1, `rgba(210,190,160,${a * 0.1})`);
      ctx.beginPath();
      ctx.moveTo(cx, cy);
      ctx.lineTo(n.x, n.y);
      ctx.strokeStyle = grad;
      ctx.lineWidth = 2;
      ctx.stroke();

      ctx.beginPath();
      ctx.arc(n.x, n.y, 3, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(210,190,160,${a * 0.55})`;
      ctx.fill();
    });
  }, [getCenter]);

  const animate = useCallback(() => {
    let needsFrame = false;
    const isOpen = openRef.current;

    nodesRef.current.forEach((n, i) => {
      const targetT = isOpen ? 1 : 0;
      n.t = lerp(n.t, targetT, 0.1);
      const tx = isOpen ? n.tx : n.cx;
      const ty = isOpen ? n.ty : n.cy;
      n.x = lerp(n.x, tx, 0.12);
      n.y = lerp(n.y, ty, 0.12);

      const el = nodeEls.current[i];
      if (el) {
        el.style.left    = `${n.x}px`;
        el.style.top     = `${n.y}px`;
        el.style.opacity = `${n.t}`;
        el.style.pointerEvents = isOpen ? 'all' : 'none';
      }

      if (
        Math.abs(n.x - tx) > 0.3 ||
        Math.abs(n.y - ty) > 0.3 ||
        Math.abs(n.t - targetT) > 0.003
      ) needsFrame = true;
    });

    drawLines();
    if (needsFrame) rafRef.current = requestAnimationFrame(animate);
  }, [drawLines]);

  useEffect(() => {
    cancelAnimationFrame(rafRef.current);
    rafRef.current = requestAnimationFrame(animate);
    return () => cancelAnimationFrame(rafRef.current);
  }, [open, animate]);

  return (
    <div className={styles.scene} ref={sceneRef}>
      <canvas className={styles.canvas} ref={canvasRef} />

      {SOCIALS.map((s, i) => (
        <div
          key={s.id}
          className={styles.nodeWrap}
          ref={el => { if (el) nodeEls.current[i] = el; }}
          style={{ opacity: 0, pointerEvents: 'none' }}
          onClick={() => window.open(s.href, '_blank')}
          role="link"
          tabIndex={open ? 0 : -1}
          aria-label={s.label}
          onKeyDown={e => e.key === 'Enter' && window.open(s.href, '_blank')}
        >
          <div className={styles.bubble} style={{ background: s.color }}>
            {s.icon}
          </div>
          <span className={styles.label}>{s.label}</span>
        </div>
      ))}

      <button
        ref={btnRef}
        className={`${styles.centerBtn} ${open ? styles.active : ''}`}
        onClick={() => setOpen(v => !v)}
        aria-label="Социальные сети"
        aria-expanded={open}
      >
        <ShareIcon />
      </button>
    </div>
  );
};

export default SocialMolecule;
