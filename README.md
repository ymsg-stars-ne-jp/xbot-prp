# X Bot Checker (PHP / HTML / CSS / JS)

X（旧Twitter）でログインしたユーザーが、指定したアカウントを簡易スコアで判定し、
リアルタイム監視で高リスクアカウントを自動対応（自動ブロック / 自動報告）できるサンプルです。

## 主な機能
- OAuth2 (PKCE) でXログイン
- 単体アカウント診断（スコア + 理由表示）
- リアルタイム監視（45秒ごと）
- 高リスク時の自動ブロック（X API `block.write` スコープが必要）
- 違法アカウントの自動報告（`logs/illegal_reports.jsonl` へ保存 + 任意Webhook通知）

## ファイル構成
- `public/index.html` - UI
- `public/styles.css` - スタイル
- `public/app.js` - フロント処理
- `api/bootstrap.php` - 共通処理
- `api/oauth_start.php` - OAuth開始
- `api/oauth_callback.php` - OAuthコールバック
- `api/session_status.php` - セッション状態確認
- `api/analyze_user.php` - 単体ユーザー判定
- `api/monitor_tick.php` - リアルタイム監視1サイクル実行
- `api/logout.php` - ログアウト

## セットアップ
1. `.env.example` を `.env` にコピーして値を設定する
2. X Developer Portal で OAuth2 Callback URL を設定する
3. Scope に `tweet.read users.read follows.read block.write offline.access` を含める
4. `X_REDIRECT_URI` を実URLに合わせる

### ドメイン設定例
`https://www.yamiyo-sigeru.com/app/xbot-pro/` に配置する場合:

- `public/index.html` を公開
- `.env` の `X_REDIRECT_URI` を
  `https://www.yamiyo-sigeru.com/app/xbot-pro/api/oauth_callback.php`
  に設定

## 注意
- 判定は参考情報です。誤判定の可能性があるため運用時は監査してください。
- 自動報告はX公式のreport API連携ではなく、ログ保存+Webhook通知です。
- APIプラン/権限により動作範囲が異なります。
