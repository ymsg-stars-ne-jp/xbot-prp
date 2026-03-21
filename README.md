# X Bot Checker (PHP / HTML / CSS / JS)

X（旧Twitter）でログインしたユーザーが、指定したアカウントを簡易スコアで判定するサンプルです。

## ファイル構成
- `public/index.html` - UI
- `public/styles.css` - スタイル
- `public/app.js` - フロント処理
- `api/oauth_start.php` - OAuth開始
- `api/oauth_callback.php` - OAuthコールバック
- `api/session_status.php` - セッション状態確認
- `api/analyze_user.php` - ユーザー判定
- `api/logout.php` - ログアウト
- `api/bootstrap.php` - 共通処理

## セットアップ
1. `.env.example` を `.env` にコピーして値を設定する
2. X Developer Portal で OAuth2 の Callback URL を設定する
3. `X_REDIRECT_URI` を実URLに合わせる

### ドメイン設定例
`https://www.yamiyo-sigeru.com/app/xbot-pro/` に配置する場合は、以下のように設定します。

- `public/index.html` にアクセスできるようにする
- `.env` の `X_REDIRECT_URI` を
  `https://www.yamiyo-sigeru.com/app/xbot-pro/api/oauth_callback.php`
  に設定する

## 注意
- この判定は公開指標ベースの参考情報です。
- API利用プランや権限によって取得できるデータが異なります。
- 自動フォロー・自動ブロック等の操作は実装していません。
