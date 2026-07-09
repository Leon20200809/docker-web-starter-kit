# Docker Web Starter Kit

WSL2 Ubuntu 上で Docker Compose を使い、Web 開発に必要な実行環境を段階的に構築する学習用スターターキットです。

Nginx による静的 HTML 表示から始め、PHP-FPM、MySQL、WordPress、Laravel、Next.js、Reverse Proxy による複数アプリのドメイン振り分けまでを、実際に手を動かしながら確認します。

このリポジトリの目的は、Docker を「便利な魔法」として使うことではありません。  
Laragon や Local by Flywheel のようなローカル開発環境が裏側で行っている Web サーバー、PHP 実行環境、DB、ポート公開、ドメイン振り分けの役割を、Docker Compose で段階的に再現し、仕組みとして理解することを目的としています。

```text
LG流 本物のLinux開発環境 楽ちんセット

WSL2 Ubuntu
↓
Docker Engine
↓
Docker Compose
↓
Nginx / PHP-FPM / MySQL
↓
WordPress / Laravel / Next.js
↓
Reverse Proxyでドメイン振り分け
```

---

## このリポジトリで学ぶこと

このリポジトリでは、Web アプリケーションがブラウザに表示されるまでの流れを、コンテナ単位で分解して確認します。

- Nginx が HTTP リクエストを受け取る仕組み
- 静的 HTML と PHP の処理の違い
- Nginx と PHP-FPM の役割分担
- PHP から MySQL へ接続する仕組み
- Docker volume による DB データの永続化
- MySQL 初期化 SQL の実行タイミング
- WordPress / Laravel / Next.js の実行環境の違い
- 複数アプリを 1 つの入口からドメイン名で振り分ける Reverse Proxy 構成
- Docker network によるコンテナ間通信

---

## 対象者

このリポジトリは、次のような人を想定しています。

- WordPress や PHP のローカル開発環境を、仕組みから理解したい人
- Laragon / Local by Flywheel / XAMPP などの裏側を知りたい人
- Laravel や Next.js を Docker 上で動かす基礎を掴みたい人
- VPS や本番サーバーの構成を理解する前段階として、Linux 上で環境構築を練習したい人
- Docker Compose の `image` / `build` / `ports` / `volumes` / `networks` を、実例で学びたい人

---

## 前提環境

このリポジトリは、主に以下の環境での学習を想定しています。

```text
Windows
↓
WSL2 Ubuntu
↓
Docker Engine
↓
Docker Compose
```

必要なもの:

- WSL2 Ubuntu
- Docker Engine
- Docker Compose
- Git
- ブラウザ
- VS Code などのエディタ

Docker Desktop 前提ではなく、WSL2 Ubuntu 上の Linux 環境で Docker を扱うことを想定しています。

---

## ディレクトリ構成

```text
docker-web-starter-kit/
├─ 01-nginx-html/
├─ 02-nginx-php-fpm/
├─ 03-nginx-php-mysql/
├─ 04-wordpress/
├─ 05-laravel/
├─ 06-reverse-proxy/
├─ 07-nextjs/
└─ README.md
```

各ディレクトリは独立した学習ステップです。  
番号順に進めることで、Web 開発環境の構成要素を段階的に理解できるようにしています。

---

## 学習ステップ

### 01-nginx-html

Nginx コンテナで静的 HTML を表示する最小構成です。

学ぶこと:

- Docker Compose の基本
- Nginx コンテナの起動
- ホスト側ポートとコンテナ側ポートの対応
- HTML ファイルをコンテナにマウントして表示する仕組み

構成イメージ:

```text
Browser
↓
localhost:8081
↓
Nginx
↓
HTML
```

---

### 02-nginx-php-fpm

Nginx と PHP-FPM を別コンテナに分離し、PHP を実行する構成です。

学ぶこと:

- Nginx と PHP-FPM の役割分担
- 静的ファイルと PHP ファイルの処理の違い
- `fastcgi_pass` による PHP-FPM への処理委譲
- Docker Compose のサービス名によるコンテナ間通信

構成イメージ:

```text
Browser
↓
Nginx
↓
PHP-FPM
↓
PHP実行
```

---

### 03-nginx-php-mysql

02 の構成に MySQL を追加し、PHP から PDO で MySQL に接続する構成です。

学ぶこと:

- MySQL コンテナの追加
- PHP 拡張 `pdo_mysql` の導入
- PHP から MySQL へ PDO 接続する方法
- Docker volume による DB データの永続化
- `/docker-entrypoint-initdb.d` による初期 SQL 実行
- MySQL 文字化けトラブルの調査方法

構成イメージ:

```text
Browser
↓
Nginx
↓
PHP-FPM
↓
PDO
↓
MySQL
```

このステップでは、DB 由来の日本語文字化けが発生した際に、以下のように層を分けて調査しました。

```text
HTML charset
↓
PHPファイルの文字コード
↓
htmlspecialchars()
↓
fetchAll()
↓
PDO接続時のcharset
↓
MySQL接続変数
↓
DB保存データのHEX確認
↓
初期SQL投入時の文字コード指定
```

結果として、SQL ファイル自体ではなく、MySQL 初期化時の接続文字コード指定が重要であることを確認しました。

対策例:

```sql
USE starter_db;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

### 04-wordpress

03 の Nginx + PHP-FPM + MySQL 構成をベースに、WordPress を動かす予定のステップです。

学ぶこと:

- WordPress 公式 Docker イメージの利用
- WordPress と MySQL の接続
- WordPress 初期インストール画面の表示
- WordPress 本体、テーマ、プラグイン、アップロードファイルの永続化
- Docker 上で WordPress 開発環境を構築する基本

構成イメージ:

```text
Browser
↓
Nginx
↓
WordPress PHP-FPM
↓
MySQL
```

03 で自作 PHP から MySQL に接続した流れを、WordPress に置き換える位置付けです。

---

### 05-laravel

Laravel アプリケーションを Docker 上で動かす予定のステップです。

学ぶこと:

- Laravel の `public` ディレクトリを DocumentRoot にする理由
- Nginx + PHP-FPM による Laravel 実行環境
- Composer 依存関係の扱い
- `.env` と DB 接続設定
- Laravel と MySQL の接続
- Laravel の内部ファイルを公開しない構成

構成イメージ:

```text
Browser
↓
Nginx
↓
Laravel public/index.php
↓
PHP-FPM
↓
MySQL
```

Laravel では、WordPress と違い、公開ディレクトリを必ず `public` に限定することが重要です。

```text
Laravel:
DocumentRoot = /path/to/laravel/public
```

---

### 06-reverse-proxy

04 WordPress と 05 Laravel を同時に起動し、1 つの入口コンテナからドメイン名で振り分ける予定のステップです。

学ぶこと:

- Reverse Proxy の役割
- Nginx の `server_name` による振り分け
- `proxy_pass` による別コンテナへの転送
- 共通 Docker network の利用
- 複数アプリを同じ 80 番ポートで扱う考え方
- Laragon / Local by Flywheel のようなローカル環境の裏側

完成イメージ:

```text
http://wordpress.localhost → WordPress
http://laravel.localhost   → Laravel
```

構成イメージ:

```text
Browser
↓
Reverse Proxy Nginx
├─ wordpress.localhost → WordPress用Nginx
└─ laravel.localhost   → Laravel用Nginx
```

このステップの本質は、ポート番号でアプリを分ける段階から、ドメイン名で振り分ける段階へ進むことです。

```text
入口を増やすな。
案内係を置け。
```

---

### 07-nextjs

Next.js アプリケーションを Docker 上で起動し、06 の Reverse Proxy 配下で表示する予定のステップです。

学ぶこと:

- Node.js 系アプリケーションの Docker 化
- Next.js 開発サーバーのコンテナ起動
- PHP-FPM 型アプリと Node.js アプリサーバーの違い
- Reverse Proxy から Next.js コンテナへ転送する構成
- WordPress / Laravel / Next.js を同時に扱うローカル開発環境

完成イメージ:

```text
http://wordpress.localhost → WordPress
http://laravel.localhost   → Laravel
http://next.localhost      → Next.js
```

PHP 系アプリとの違い:

```text
WordPress / Laravel:
Nginx
↓
PHP-FPM

Next.js:
Reverse Proxy
↓
Node.js / Next.js server
```

---

## 基本的な使い方

各ステップのディレクトリへ移動して、Docker Compose を起動します。

例:

```bash
cd 03-nginx-php-mysql
docker compose up -d --build
```

コンテナ状態確認:

```bash
docker compose ps
```

ログ確認:

```bash
docker compose logs
```

停止:

```bash
docker compose down
```

DB volume も含めて完全に削除する場合:

```bash
docker compose down -v
```

`down -v` は DB データも削除するため、実行時は注意してください。

---

## Docker Compose でよく使う要素

このリポジトリでは、Docker Compose の以下の要素を段階的に扱います。

| 要素          | 役割                                       |
| ------------- | ------------------------------------------ |
| `image`       | 既存の Docker イメージを使う               |
| `build`       | Dockerfile から独自イメージを作る          |
| `ports`       | ホスト側ポートとコンテナ側ポートを接続する |
| `volumes`     | ファイルや DB データを永続化する           |
| `depends_on`  | コンテナの起動順を指定する                 |
| `networks`    | コンテナ同士の通信経路を作る               |
| `environment` | コンテナ内で使う環境変数を渡す             |
| `command`     | コンテナ起動時のコマンドや設定を指定する   |

---

## ポートとドメイン振り分けの考え方

初期ステップでは、学習しやすいようにポート番号を分けています。

```text
01 → localhost:8081
02 → localhost:8082
03 → localhost:8083
```

一方で、実務や本番に近い構成では、1 つの入口で複数サイトを振り分けます。

```text
app.example.com  → Laravel
blog.example.com → WordPress
```

同じ 80 番 / 443 番ポートを使っていても、HTTP リクエストには `Host` ヘッダーが含まれます。

```text
Host: app.example.com
Host: blog.example.com
```

Nginx や Apache はこの Host 名を見て、どのアプリへ通信を渡すかを判断します。

Docker でも同じ考え方を Reverse Proxy で再現できます。

---

## このリポジトリの位置付け

このリポジトリは、本番インフラ運用をそのまま再現するものではありません。

目的は、Web 開発者として必要になるローカル開発環境、サーバー構成、DB 接続、アプリ実行環境の基礎を、Docker Compose を通じて体験的に理解することです。

言い換えると、次のような力を鍛えるためのリポジトリです。

```text
何が入口なのか
どこでPHPが実行されるのか
どこにDBがあるのか
どこにデータが保存されるのか
なぜlocalhostではなくサービス名で接続するのか
なぜLaravelはpublicだけを公開するのか
なぜ複数サイトが同じ80番で動くのか
```

---

## 実務とのつながり

このリポジトリで扱う構成要素は、実務の Web 開発でも頻繁に登場します。

| 実務の場面                   | このリポジトリで対応する学習 |
| ---------------------------- | ---------------------------- |
| WordPress のローカル環境構築 | 04-wordpress                 |
| Laravel のローカル環境構築   | 05-laravel                   |
| Next.js の開発環境構築       | 07-nextjs                    |
| DB 接続エラー調査            | 03-nginx-php-mysql           |
| 文字化け調査                 | 03-nginx-php-mysql           |
| 複数サイトの同時起動         | 06-reverse-proxy             |
| VPS の Nginx 設定理解        | 02 / 05 / 06                 |
| Docker Compose の基礎理解    | 全ステップ                   |

---

## 注意事項

このリポジトリは学習用です。

- 本番環境用のセキュリティ設定は最小限です
- DB パスワードなどは学習用の値です
- 公開サーバーにそのままデプロイすることは想定していません
- 必要に応じて `.env` 化、秘密情報管理、HTTPS、バックアップ、ログ管理、監視などを追加してください

本番運用では、少なくとも以下のような追加検討が必要です。

- HTTPS / SSL 証明書
- Firewall 設定
- DB ポートの外部非公開化
- 秘密情報の安全な管理
- バックアップ
- ログローテーション
- セキュリティアップデート
- ファイル権限
- CI/CD
- 監視

---

## リポジトリの狙い

このリポジトリは、単に「Docker でアプリを動かした」ことを示すためのものではありません。

Web 開発環境を構成する要素を、次のように段階的に分解して理解することを狙っています。

```text
HTMLを表示できる
↓
PHPを動かせる
↓
DBにつなげる
↓
WordPressを動かせる
↓
Laravelを動かせる
↓
複数アプリを1つの入口で振り分ける
↓
Next.jsも同じ開発環境に参加させる
```

便利ツールを使うだけで終わらず、その裏側にある構造を理解するための練習場です。

```text
魔法の杖を使う側から、
魔法陣の構造を読む側へ進む。
```

---

## 今後の予定

- 04 WordPress 環境の構築
- 05 Laravel 環境の構築
- 06 Reverse Proxy による複数アプリ振り分け
- 07 Next.js 環境の構築
- `.env` による環境変数管理
- Reverse Proxy の HTTPS 対応検証
- README と構成図の追加整理

---

## ひとことで言うと

WSL2 Ubuntu 上で、Nginx / PHP-FPM / MySQL / WordPress / Laravel / Next.js / Reverse Proxy を段階的に構築する、LG流 Linux Web 開発環境スターターキットです。

```text
楽をするために、まず仕組みを支配する。
```
