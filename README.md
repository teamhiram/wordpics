# WordPics — 御言画像ライブラリ

**公開 URL:** <https://wordpics.amana.top>

聖書の御言を美しいデザインで配布するリソースサイト。
閲覧・ダウンロードに加えて、ユーザーが自分で作った御言画像を投稿・公開できます（管理者承認制）。

- フロント: 静的 HTML + CSS + バニラ JS（ビルド不要・フレームワーク非依存）
- バックエンド: **PHP 8 + MySQL**（Xserver スタンダード対応）
- 画像生成: ChatGPT のカスタム GPT「[回復訳御言画像生成君](https://chatgpt.com/g/g-69e8f8fc4230819185aba2cd319c309c-hui-fu-yi-yu-yan-hua-xiang-sheng-cheng-jun)」
- 認証: メールのマジックリンク（パスワード不要）
- メール送信: PHP `mail()`（Xserver SMTP 経由）、必要に応じて Resend API に切替可能

---

## 機能

- 御言画像ギャラリー（フリーワード検索・サイズ / 向き / タグ / 書 / 出典で絞り込み）
- ユーザー投稿（PNG/JPG・最大 10MB・承認制）
- 投稿には「**ユーザー生成**」バッジ表示
- 誤字報告ボタン（未ログインでも可・匿名 IP ハッシュで重複制御）
- 管理画面（承認・却下・改訂版アップロード・非公開化・報告処理）
- マイページ（自分の投稿の状態・却下理由・改訂版表示）

---

## ディレクトリ構成

```
wordpics/                          # Xserver の public_html にそのままアップロード
├── index.html                     # ギャラリー
├── submit.html  submit.js         # 投稿ページ
├── how-to-use.html                # My GPT 使い方ガイド
├── me.html                        # マイページ
├── admin.html   admin.js          # 管理画面
├── app.js                         # ギャラリー本体
├── styles.css
├── assets/                        # 静的アセット（ロゴ等）
├── data/
│   ├── books.json                 # 66 書のメタ
│   └── tags.json                  # タグ分類
├── pics/                          # 公式画像（既存 15 枚）
├── uploads/                       # ユーザー投稿（PHP が自動生成）
│   └── submissions/{id}/original.{png|jpg}
│                     /revised.{png|jpg}    # 管理者が改訂版を上げた場合
├── api/
│   ├── auth/{request,verify,logout,me}.php
│   ├── pics.php                   # 公開ギャラリーのデータソース
│   ├── report.php                 # 誤字報告
│   ├── submit.php                 # 投稿
│   ├── me/submissions.php
│   └── admin/{submissions,submission_action,reports,report_action}.php
├── lib/                           # Web 非公開の共通 PHP
│   ├── bootstrap.php  config.php  db.php  util.php  session.php  email.php
├── db/
│   ├── schema.sql                 # MySQL スキーマ
│   └── seed.sql                   # 既存 15 枚をシード
├── config.local.php               # 本番設定（Git 管理外）
├── config.local.php.example       # 上記のテンプレート
├── .htaccess                      # lib/ と db/ の保護・uploads/ の PHP 無効化
└── README.md
```

---

## 動作要件

| 項目 | 必須 |
|---|---|
| PHP | **8.0 以上**（`match` 式・`strict_types` を使用） |
| MySQL | 5.7 以上（JSON 型・`utf8mb4` を使用） |
| Apache | `.htaccess` 有効（Xserver は標準で可） |
| HTTPS | 必須（セッション Cookie が `Secure` 属性で送られるため） |

Xserver スタンダードのデフォルト設定であれば要件を満たしています（サーバーパネル →「PHP Ver.切替」で 8.x が選ばれていることだけ確認）。

---

## 初回デプロイ手順

> 既に動いているサイトのアップデートだけの場合は、変更のあったファイルを FTP で上書きするだけでOK（`uploads/` は絶対に上書きしないこと）。

### 1. MySQL データベースを作る

サーバーパネル → **MySQL 設定** で:

1. **MySQL 追加**：データベースを作成（文字コード `utf8mb4`）
2. **MySQL ユーザー追加**：新規ユーザー作成
3. **MySQL 一覧 → アクセス権所有ユーザー**：1 のデータベースに 2 のユーザーを追加
4. **MySQL 一覧**に表示される **ホスト名**（`mysqlXXXX.xserver.jp`）をメモ

### 2. SSL を有効化

サーバーパネル → **SSL 設定** → 対象ドメインに「独自 SSL（Let's Encrypt 無料）」を追加。

### 3. ファイル一式をアップロード

FTP またはファイルマネージャーで、`wordpics.amana.top` の `public_html/` 直下に全ファイルを転送。

### 4. `config.local.php` を作成

```bash
cp config.local.php.example config.local.php
```

中身で埋めるべきは以下:

- `db.host` / `db.name` / `db.user` / `db.password` ← 手順 1 で発行した値
- `site_origin` → `https://wordpics.amana.top`
- `admin_emails` / `admin_notify_email` → 管理者のメール
- `session_secret` → `openssl rand -hex 32` の出力

### 5. スキーマとシードを適用

サーバーパネルの **phpMyAdmin** から対象 DB を開き、「SQL」タブで順に実行:

1. `db/schema.sql` の内容を貼り付けて実行（テーブル作成）
2. `db/seed.sql` の内容を貼り付けて実行（既存 15 枚のメタ登録）

### 6. 動作確認

- <https://wordpics.amana.top/> ← ギャラリー表示
- <https://wordpics.amana.top/submit.html> ← メール入力 → ログインリンク受信 → リンク押下で投稿可能に
- 管理者メール（`tatsuya.n@gmail.com`）でログインすると **管理** リンクが出て `/admin.html` にアクセス可

---

## GitHub Actions で Xserver へ自動デプロイ

`main` ブランチへ `push` すると、GitHub Actions が Xserver へ自動アップロードします。

ワークフロー: `.github/workflows/deploy-xserver.yml`

### 1. GitHub Secrets を設定

リポジトリの **Settings → Secrets and variables → Actions** で以下を追加:

- `XSERVER_FTP_HOST`（例: `svXXXX.xserver.jp`）
- `XSERVER_FTP_USER`（FTP ユーザー名）
- `XSERVER_FTP_PASSWORD`（FTP パスワード）
- `XSERVER_FTP_REMOTE_DIR`（例: `/home/<server-id>/<domain>/public_html/`）

### 2. 自動デプロイの動作

- トリガー: `main` への `push`（手動実行 `workflow_dispatch` も可）
- 転送方式: FTP over TLS（FTPS, port 21）
- 除外: `.git` / `.github` / `_prep` / `.env` / `config.local.php` / `uploads/`

> 重要: `uploads/` はサーバー上の投稿データを保持するため、ワークフローから除外しています。
> 既存データを消さないため、この設定は変更しないでください。

### 3. 初回確認

1. `main` にコミットを push
2. GitHub の **Actions** タブで `Deploy to Xserver` が成功することを確認
3. サイトを開いて変更が反映されていることを確認

## 📧 メール送信の設定について

### 基本: Xserver の標準 `mail()` でまず試す

`config.local.php` に初期設定されている `mail_from: noreply@amana.top` のままで、
Xserver が `amana.top` の DNS を管理していれば SPF が自動で効き、多くの受信先に届きます。

### 届かない・迷惑メール扱いされる場合

**A. Xserver でメールアカウントを作成**  
サーバーパネル → **メールアカウント設定** → `amana.top` → **メールアカウント追加** で
`noreply@amana.top` を作成（パスワードは任意・受信はしない）。
From ヘッダのドメインに実アカウントが存在することで、Gmail 側の判定を通りやすくなります。

**B. Resend に切替（Gmail を含め確実に届かせたい場合）**

1. [resend.com](https://resend.com) に登録（無料枠: 月 3,000 通）
2. Domains に `amana.top` を追加 → 表示される SPF / DKIM / DMARC レコードを
   Xserver の **DNS レコード設定** に追加
3. 認証通過後、API キーを発行
4. `config.local.php` の `resend_api_key` に貼って再アップロード

コード変更なしで自動的に Resend 経由の送信に切り替わります（`lib/email.php` が自動判別）。

### SPF 確認コマンド

```bash
dig amana.top TXT +short
```

---

## 📁 フォルダ権限について

**Xserver は PHP がユーザー権限で動く**（suEXEC）ため、特別なパーミッション操作は不要です。

- FTP でアップロードしたデフォルト（ディレクトリ `755` / ファイル `644`）で動く
- `uploads/submissions/{id}/` は PHP が自動で `mkdir(0755, recursive)` する
- 投稿時に「アップロード先ディレクトリを作成できませんでした」と出る場合のみ、
  `uploads/` を `755`（必要なら `707`）に手動で変更

---

## 管理者の操作

管理画面 (`/admin.html`) で以下が可能:

- **承認** — `pending` → `approved` にして公開
- **却下** — 理由を入れて `rejected` に（投稿者にメール通知）
- **改訂版アップロード** — PNG/JPG を上げると `revised_path` が設定され、公開側はそちらを表示（原本は保持）
- **非公開** — 一度公開した画像を取り下げ
- **誤字報告の処理** — 対応済 / 却下 / 再オープン

---

## ローカル開発

### 最速: ギャラリー閲覧だけしたい（PHP/MySQL 不要）

`/api/pics.php` にアクセスできない環境（純粋な静的サーバー・`file://` 以外）
では、`app.js` が自動で `data/pics.json` にフォールバックします。

```bash
# Python 3 ならこれだけで OK
python3 -m http.server 8000
# → http://localhost:8000/

# Node なら
npx serve .
```

表示されるのは `data/pics.json` に含まれる公式画像のみ（ユーザー投稿やログイン・
投稿・管理機能は PHP が必要なので動きません）。コンソールに
`API が利用できないためフォールバックします` と表示されたらフォールバック動作中です。

> 注: `file://` でそのまま `index.html` を開くと fetch が CORS で弾かれます。
> 必ずローカルサーバー経由で開いてください。

### フル機能（投稿・ログイン・管理まで動かす）

PHP + MySQL が動く環境が必要（XAMPP / MAMP / Laravel Valet / `php -S` + ローカル MySQL 等）。

```bash
php -S localhost:8000
# → http://localhost:8000/
```

`config.local.php` をローカル用に別途用意:

```php
'site_origin' => 'http://localhost:8000',
'db'          => [ /* ローカル MySQL 接続情報 */ ],
```

スキーマ適用はローカル MySQL クライアントから:

```bash
mysql -u root wordpics < db/schema.sql
mysql -u root wordpics < db/seed.sql
```

---

## 新規 Book / Tag の追加

- `data/books.json` に書を追加すれば、投稿フォームの書セレクトにも自動で現れる
- `data/tags.json` にタグカテゴリを追加すれば、ギャラリー絞り込みで分類表示される
- どちらも静的 JSON なので、デプロイは FTP で上書きするだけ（DB 変更不要）

---

## セキュリティ

- `config.local.php` は `.gitignore` で除外（Git にコミットされない）
- `/lib/` と `/db/` は `.htaccess` で直接アクセスを完全ブロック
- `/uploads/` 以下は `php_flag engine off` により PHP 実行不可
- CSRF トークンは `/api/auth/me.php` のレスポンスで発行（POST 系 API で検証）
- セッション Cookie は `HttpOnly` / `SameSite=Lax` / HTTPS 上で `Secure`
- マジックリンクは **15 分で失効**、1 回使用で消費、同一メールあたり **5 分に 3 回まで**のレート制限
- 誤字報告は同一 IP ハッシュで **1 分に 5 件まで**のレート制限
- アップロード画像は `finfo` で MIME 判定（拡張子偽装を防止）・寸法チェック（最小 300px / 最大 6000px）

---

## データソース

- 66 書一覧: `book_database.md`（書名・ふりがな・英語フル/略称・中国語フル/略称）
- 聖句本文: 回復訳聖書（© 日本福音書房）
