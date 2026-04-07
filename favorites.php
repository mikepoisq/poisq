<?php
session_start();
require_once __DIR__ . '/config/database.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Избранное — Poisq</title>
<meta name="robots" content="noindex">
<script src="/assets/js/theme.js"></script>
<link rel="stylesheet" href="/assets/css/theme.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); color: var(--text); }
@keyframes slideFromBottom {
  from { transform: translateY(100%); opacity: 0; }
  to   { transform: translateY(0); opacity: 1; }
}
.fav-page {
  display: flex; flex-direction: column;
  min-height: 100vh;
  background: #F8FAFC;
  max-width: 430px; margin: 0 auto;
}
.fav-header {
  display: flex; align-items: center; gap: 10px;
  padding: 0 16px; height: 58px;
  background: #ffffff;
  border-bottom: 1px solid #E2E8F0;
  position: sticky; top: 0; z-index: 100;
}
.fav-header-icon { font-size: 20px; }
.fav-header-title { font-size: 16px; font-weight: 700; color: var(--text); letter-spacing: -0.3px; flex: 1; }
.fav-close {
  width: 34px; height: 34px; border-radius: 50%;
  border: none; background: #F1F5F9;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0;
}
.fav-close svg { width: 17px; height: 17px; stroke: #475569; fill: none; stroke-width: 2.5; }
.fav-content { flex: 1; padding: 16px; display: flex; flex-direction: column; gap: 12px; }
.ann-header {
  display: flex; align-items: center; gap: 10px;
  padding: 0 16px; height: 58px;
  background: var(--bg);
  border-bottom: 1px solid var(--border-light);
  flex-shrink: 0;
}
.ann-header-icon { font-size: 20px; }
.ann-title { font-size: 16px; font-weight: 700; color: var(--text); letter-spacing: -0.3px; flex: 1; }
.ann-close {
  width: 34px; height: 34px; border-radius: 50%;
  border: none; background: var(--bg-secondary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0; transition: background 0.15s;
}
.ann-close:active { background: var(--border); }
.ann-close svg { width: 17px; height: 17px; stroke: var(--text-secondary); fill: none; stroke-width: 2.5; }
.ann-content { flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; padding: 16px 14px; }
.ann-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; gap: 14px; }
.spinner { width: 28px; height: 28px; border: 3px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.ann-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; gap: 12px; text-align: center; }
.ann-empty-icon { width: 56px; height: 56px; border-radius: 50%; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; }
.ann-empty-icon svg { width: 28px; height: 28px; stroke: var(--text-light); fill: none; }
.ann-empty h3 { font-size: 17px; font-weight: 700; color: var(--text); }
.ann-empty p { font-size: 14px; color: var(--text-secondary); line-height: 1.5; }
.ann-add-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 11px 20px; border-radius: 12px;
  background: var(--primary); color: white;
  font-size: 14px; font-weight: 700; text-decoration: none; margin-top: 8px;
}
.ann-add-btn svg { width: 16px; height: 16px; stroke: white; fill: none; stroke-width: 2; }
</style>
</head>
<body>
<div class="fav-page">
  <div class="fav-header">
    <span class="fav-header-icon">❤️</span>
    <span class="fav-header-title">Избранное</span>
    <button class="fav-close" onclick="closeFavoritesModal()">
      <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="fav-content" id="favContent">
    <div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>
  </div>
</div>

<script>
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function closeFavoritesModal() {
  if (window.history.length > 1) {
    window.history.back();
  } else {
    window.location.href = '/';
  }
}

async function loadFavorites() {
  const content = document.getElementById('favContent');
  content.innerHTML = '<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>';
  try {
    const res  = await fetch('/api/favorites.php?action=list');
    const data = await res.json();
    if (!data.success || !data.items || data.items.length === 0) {
      content.innerHTML = `
        <div class="ann-empty">
          <div class="ann-empty-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
          </div>
          <h3>Пока пусто</h3>
          <p>Нажимайте ❤️ на карточках сервисов,<br>чтобы сохранять их здесь</p>
          <a href="/" class="ann-add-btn">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            Найти сервисы
          </a>
        </div>`;
      return;
    }
    let html = '<div style="display:flex;flex-direction:column;gap:12px;">';
    data.items.forEach(item => {
      const photo = item.photo || '';
      const rating = parseFloat(item.rating || 0);
      const cityMeta = [item.city_name, item.subcategory || item.category].filter(Boolean).join(' · ');
      html += `
        <div style="display:flex;align-items:center;gap:12px;background:#ffffff;border-radius:12px;padding:12px;border:1px solid #E2E8F0;box-shadow:0 1px 3px rgba(0,0,0,0.07);">
          ${photo
            ? `<img src="${escHtml(photo)}" alt="" style="width:64px;height:64px;border-radius:10px;object-fit:cover;flex-shrink:0;" onerror="this.style.display='none'">`
            : `<div style="width:64px;height:64px;border-radius:10px;flex-shrink:0;background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:var(--primary);">${escHtml(item.name.charAt(0).toUpperCase())}</div>`
          }
          <div style="flex:1;min-width:0;">
            <div style="font-size:14.5px;font-weight:700;color:var(--text);margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(item.name)}</div>
            <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(cityMeta)}</div>
            ${rating > 0 ? `<span style="font-size:12px;font-weight:700;color:#F59E0B;">★ ${rating.toFixed(1)}</span>` : ''}
          </div>
          <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;">
            <a href="/service/${item.id}-${escHtml(item.slug || item.id)}" style="display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;background:#3B6CF4;color:white;text-decoration:none;">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><path d="M9 18l6-6-6-6"/></svg>
            </a>
            <button onclick="removeFavorite(${item.id}, this)" style="display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;background:#FFF0F0;border:none;cursor:pointer;">
              <svg viewBox="0 0 24 24" fill="#EF4444" stroke="#EF4444" stroke-width="2" width="16" height="16"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </button>
          </div>
        </div>`;
    });
    html += '</div>';
    content.innerHTML = html;
  } catch(e) {
    content.innerHTML = '<div class="ann-empty"><p>Ошибка загрузки. Попробуйте ещё раз.</p></div>';
  }
}

async function removeFavorite(serviceId, btn) {
  const card = btn.closest('div[style*="display:flex"]');
  if (card) card.style.opacity = '0.5';
  try {
    const fd = new FormData();
    fd.append('service_id', serviceId);
    const res  = await fetch('/api/favorites.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      if (card) {
        card.style.transition = 'opacity 0.2s';
        card.style.opacity = '0';
        setTimeout(() => card.remove(), 200);
      }
      const badge = document.querySelector('.menu-badge');
      if (badge) {
        const n = Math.max(0, parseInt(badge.textContent) - 1);
        if (n <= 0) badge.remove(); else badge.textContent = n;
      }
    }
  } catch(e) {
    if (card) card.style.opacity = '1';
  }
}

document.addEventListener('DOMContentLoaded', loadFavorites);
</script>
</body>
</html>
