const authStatus = document.getElementById('authStatus');
const loginBtn = document.getElementById('loginBtn');
const logoutBtn = document.getElementById('logoutBtn');
const scanCard = document.getElementById('scanCard');
const scanForm = document.getElementById('scanForm');
const loading = document.getElementById('loading');
const result = document.getElementById('result');

async function fetchJSON(url, options = {}) {
  const res = await fetch(url, options);
  const data = await res.json();
  if (!res.ok) {
    throw new Error(data.message || 'APIエラーが発生しました。');
  }
  return data;
}

function renderAuthState(profile) {
  if (profile?.authenticated) {
    authStatus.textContent = `ログイン中: @${profile.username} (${profile.name})`;
    loginBtn.classList.add('hidden');
    logoutBtn.classList.remove('hidden');
    scanCard.classList.remove('hidden');
  } else {
    authStatus.textContent = '未ログインです。';
    loginBtn.classList.remove('hidden');
    logoutBtn.classList.add('hidden');
    scanCard.classList.add('hidden');
  }
}

async function loadSession() {
  try {
    const data = await fetchJSON('../api/session_status.php');
    renderAuthState(data);
  } catch (err) {
    authStatus.textContent = `セッション確認に失敗: ${err.message}`;
  }
}

function escapeHtml(str) {
  return str
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

scanForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const username = document.getElementById('username').value.trim();
  if (!username) return;

  loading.classList.remove('hidden');
  result.classList.add('hidden');

  try {
    const data = await fetchJSON('../api/analyze_user.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username })
    });

    const score = data.score;
    const levelClass = score >= 70 ? 'high' : score >= 40 ? 'medium' : 'low';
    const levelText = score >= 70 ? '高リスク' : score >= 40 ? '中リスク' : '低リスク';

    result.innerHTML = `
      <div class="badge ${levelClass}">${levelText} (${score}/100)</div>
      <p><strong>@${escapeHtml(data.user.username)}</strong> / ${escapeHtml(data.user.name)}</p>
      <p>${escapeHtml(data.summary)}</p>
      <ul>${data.reasons.map((r) => `<li>${escapeHtml(r)}</li>`).join('')}</ul>
    `;
    result.classList.remove('hidden');
  } catch (err) {
    result.innerHTML = `<p style="color:#ffb4b1;">${escapeHtml(err.message)}</p>`;
    result.classList.remove('hidden');
  } finally {
    loading.classList.add('hidden');
  }
});

loginBtn.addEventListener('click', () => {
  window.location.href = '../api/oauth_start.php';
});

logoutBtn.addEventListener('click', async () => {
  await fetchJSON('../api/logout.php', { method: 'POST' });
  await loadSession();
});

loadSession();
