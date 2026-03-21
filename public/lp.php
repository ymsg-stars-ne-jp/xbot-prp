<?php
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; connect-src 'self'; img-src 'self' data: https:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self';");
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>X Bot Checker LP</title>
  <link rel="stylesheet" href="./styles.css" />
</head>
<body>
  <main class="container">
    <section class="card">
      <h1>X Bot Checker</h1>
      <p class="lead">不正・業者アカウント対策向けのセキュアな監視ツールです。</p>
      <ul>
        <li>初回セットアップ: `.env` 自動作成</li>
        <li>Xログインで本人の権限内のみ操作</li>
        <li>リアルタイム監視 + 自動ブロック + 自動報告</li>
      </ul>
      <div class="monitor-actions">
        <button id="initEnvBtn" class="btn">初回セットアップ実行</button>
        <a href="./index.php" class="btn">機能画面へ</a>
        <a href="../api/oauth_start.php" class="btn">Xでログイン</a>
      </div>
      <p id="lpMsg" class="muted"></p>
    </section>
  </main>
  <script>
    async function runInit() {
      const msg = document.getElementById('lpMsg');
      try {
        const res = await fetch('../api/init_env.php');
        const data = await res.json();
        msg.textContent = data.message || '完了しました。';
      } catch (e) {
        msg.textContent = 'セットアップに失敗しました。';
      }
    }
    document.getElementById('initEnvBtn').addEventListener('click', runInit);
  </script>
</body>
</html>
