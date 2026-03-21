const authStatus = document.getElementById('authStatus');
const loginBtn = document.getElementById('loginBtn');
const logoutBtn = document.getElementById('logoutBtn');
const scanCard = document.getElementById('scanCard');
const monitorCard = document.getElementById('monitorCard');
const scanForm = document.getElementById('scanForm');
const loading = document.getElementById('loading');
const result = document.getElementById('result');
const monitorUsers = document.getElementById('monitorUsers');
const riskThreshold = document.getElementById('riskThreshold');
const autoBlock = document.getElementById('autoBlock');
const autoReport = document.getElementById('autoReport');
const autoReportOnlyWhenBlocked = document.getElementById('autoReportOnlyWhenBlocked');
const startMonitorBtn = document.getElementById('startMonitorBtn');
const stopMonitorBtn = document.getElementById('stopMonitorBtn');
const monitorStatus = document.getElementById('monitorStatus');
const monitorLog = document.getElementById('monitorLog');

let timerId = null;
let monitorActive = false;
let monitorTickInFlight = false;
const monitorIntervalMs = 45000;

async function fetchJSON(url, options = {}) {
  const res = await fetch(url, options);
  const data = await res.json();
  if (!res.ok) throw new Error(data.message || 'APIエラーが発生しました。');
  return data;
}

function escapeHtml(str) {
  return String(str)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

function renderAuthState(profile) {
  if (profile?.authenticated) {
    authStatus.textContent = `ログイン中: @${profile.username} (${profile.name})`;
    loginBtn.classList.add('hidden');
    logoutBtn.classList.remove('hidden');
    scanCard.classList.remove('hidden');
    monitorCard.classList.remove('hidden');
  } else {
    authStatus.textContent = '未ログインです。';
    loginBtn.classList.remove('hidden');
    logoutBtn.classList.add('hidden');
    scanCard.classList.add('hidden');
    monitorCard.classList.add('hidden');
    stopMonitor();
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

function getMonitorSettings() {
  return {
    usernames: monitorUsers.value.split(',').map((v) => v.trim()).filter(Boolean),
    settings: {
      riskThreshold: Number(riskThreshold.value || 75),
      autoBlock: autoBlock.checked,
      autoReport: autoReport.checked,
      autoReportOnlyWhenBlocked: autoReportOnlyWhenBlocked.checked
    }
  };
}

function renderSingleResult(data) {
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
}

function renderMonitorTick(data) {
  const rows = data.results.map((r) => {
    const action = [];
    if (r.action?.blocked) action.push('自動ブロック実行');
    if (r.action?.reported) action.push('自動報告実行');
    if (action.length === 0) action.push('対応なし');

    return `<li>@${escapeHtml(r.username)} : score=${r.score ?? '-'} / ${escapeHtml(r.summary ?? r.status)} / ${action.join('・')}</li>`;
  }).join('');

  monitorLog.innerHTML = `<p>最終監視: ${escapeHtml(data.checked_at)}</p><ul>${rows}</ul>`;
  monitorLog.classList.remove('hidden');
}

async function runMonitorTick() {
  if (!monitorActive || monitorTickInFlight) return;

  const payload = getMonitorSettings();
  if (payload.usernames.length === 0) {
    monitorStatus.textContent = '監視対象が未入力です。';
    stopMonitor();
    return;
  }

  monitorTickInFlight = true;
  try {
    monitorStatus.textContent = '監視中...';
    const data = await fetchJSON('../api/monitor_tick.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    renderMonitorTick(data);
    monitorStatus.textContent = `監視中（${monitorIntervalMs / 1000}秒ごと）`;
  } catch (err) {
    monitorStatus.textContent = `監視エラー: ${err.message}`;
  } finally {
    monitorTickInFlight = false;

    if (monitorActive) {
      timerId = setTimeout(() => {
        runMonitorTick();
      }, monitorIntervalMs);
    }
  }
}

function startMonitor() {
  if (monitorActive) return;
  monitorActive = true;
  runMonitorTick();
}

function stopMonitor() {
  monitorActive = false;
  if (timerId) {
    clearTimeout(timerId);
    timerId = null;
  }
  monitorStatus.textContent = '停止中';
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
    renderSingleResult(data);
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
  stopMonitor();
  await fetchJSON('../api/logout.php', { method: 'POST' });
  await loadSession();
});

startMonitorBtn.addEventListener('click', startMonitor);
stopMonitorBtn.addEventListener('click', stopMonitor);

loadSession();
