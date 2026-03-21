# X Bot Checker (PHP / HTML / CSS / JS)

X（旧Twitter）ログインで利用できる、業者・不正アカウント検知向けのWebツールです。
LP（ランディングページ）から初回セットアップ・ログイン・機能画面へ進めます。

## 新機能
- LP: `public/lp.php`
  - 初回セットアップボタン（`.env.example` から `.env` を自動作成）
  - ログイン導線 / 機能画面導線
- セキュリティ強化
  - セキュアCookie + SameSite=Strict
  - CSP / X-Frame-Options / X-Content-Type-Options / Referrer-Policy
  - CSRFトークン検証（POST系API）
  - レート制限（APIごと）
  - 入力バリデーション（Xユーザー名）
  - エラーメッセージの情報漏えい抑制
- 監視機能
  - 単体診断
  - リアルタイム監視（45秒）
  - 高リスク時の自動ブロック / 自動報告（ログ + 任意Webhook）

## 配置URL
- LP: `/public/lp.php`
- 機能画面: `/public/index.php`
- `index.html` は `index.php` へリダイレクト

## セットアップ
1. 公開サーバーに配置
2. LPの「初回セットアップ実行」ボタンを押す（`.env` 自動作成）
3. `.env` の値を本番値に更新
4. X Developer Portal で OAuth Callback URL を設定
   - 例: `https://www.yamiyo-sigeru.com/app/xbot-pro/api/oauth_callback.php`
5. OAuth Scope に `tweet.read users.read follows.read block.write offline.access` を含める

## 注意
- 自動報告はX公式report APIではなく、サーバーログ + Webhook通知です。
- 不正判定は参考値であり、運用時は必ず人による監査を実施してください。
