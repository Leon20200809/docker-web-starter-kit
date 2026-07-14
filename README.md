# Docker Web Starter Kit

Windows上のWSL2 Ubuntuを使い、LinuxベースのWeb開発環境をDocker Composeで再現する学習用リポジトリです。

WordPress、Laravel、Next.jsを個別に動かし、最後はNginxリバースプロキシで複数サービスを1つの入口へ統合します。

```text
Windows
└── WSL2 Ubuntu
    └── Docker Engine
        ├── WordPress
        ├── Laravel
        ├── Next.js
        └── Reverse Proxy
```

## このプロジェクトを作った理由

Laragonやレンタルサーバーは、Webサーバー、PHP、データベース、名前解決などを裏側で用意してくれます。

便利な一方で、問題が起きたときに次の境界が見えにくくなります。

```text
どのサービスが止まっているのか
どのポートへ接続しているのか
どのユーザーがファイルを作ったのか
どのコンテナがデータを持っているのか
どこでHTTPリクエストが止まったのか
```

そこで、普段は隠れている仕組みを小さく分解し、自分の手で構築しました。

このリポジトリの目的は、Dockerコマンドを暗記することではありません。

> Linux、ネットワーク、Webサーバー、実行環境を理解し、同じ環境を安全に何度でも再現できるようにする。

これが本プロジェクトの目的です。

## この構成を採用した理由

### 1. 役割ごとにコンテナを分ける

```text
Nginx
→ HTTP受付

PHP-FPM
→ PHP・Laravel・WordPress実行

MySQL
→ データ保存

Node.js
→ Vite・Next.js・フロントエンドビルド

Reverse Proxy
→ ドメイン名による振り分け
```

1つのコンテナへ全部詰め込まず、責務ごとに分離しています。

これにより、障害箇所の切り分け、再利用、構成変更がしやすくなります。

### 2. ソースコードと実行環境を分ける

アプリケーションのソースコードはbind mountでホスト側に残し、コンテナは使い捨て可能な実行環境として扱います。

```text
ホスト側
→ 編集するコードを保存

コンテナ側
→ PHP、Node.js、Nginxなどを実行
```

コンテナを削除・再作成しても、ソースコードは失われません。

### 3. データはnamed volumeへ保存する

MySQLなどの永続データはnamed volumeへ保存します。

```text
コンテナ
→ 削除可能

DBデータ
→ volumeへ保持
```

実行環境とデータの寿命を分けています。

### 4. UID・GIDを合わせる

コンテナ内で作成したファイルが、ホスト側でroot所有にならないようにします。

```yaml
user: "1000:1000"
```

Linuxはユーザー名ではなくUID・GIDの数字で所有者を判断します。

### 5. 直通経路と共通入口を両方残す

学習中は各サービスへ直接アクセスできるポートを残しています。

```text
http://localhost:8082 → WordPressへ直通
http://localhost:8083 → Laravelへ直通
http://localhost:3000 → Next.jsへ直通
```

同時に、06のリバースプロキシ経由でもアクセスできます。

```text
http://wordpress.test
http://laravel.test
http://next.test
```

直通は成功するが`.test`だけ失敗する場合、問題はリバースプロキシまたは共有ネットワーク側だと判断できます。

## 誰向けか

このリポジトリは、少しLinuxコマンドを触ったことがあり、次の疑問を持つ人向けです。

- Docker Composeの中で何が起きているか理解したい
- WordPressやLaravelをDockerで動かしたい
- PHP、MySQL、Node.js、Nginxの役割を整理したい
- bind mount、volume、network、UID/GIDを体験したい
- Laragonなどが裏側で行う処理を理解したい
- 新しいPCでも同じ開発環境をすぐ再現したい
- チーム内の環境差分を減らしたい

特に、アプリケーション開発は経験したものの、実行環境やLinux側の仕組みをもう一段理解したい人を想定しています。

## 必要環境

```text
Windows PC
WSL2 Ubuntu
VS Code
Docker Engine
Git
```

本プロジェクトでは、Docker Desktopではなく、WSL2 UbuntuへDocker公式版のDocker Engineを導入しています。

導入手順:

```text
docs/docker-engine-install.md
```

権限、UID/GID、bind mount、Windows hosts:

```text
docs/docker-permission.md
```

学習順序:

```text
docs/learning-roadmap.md
```

## ディレクトリ構成

```text
docker-web-starter-kit/
├── README.md
├── docs/
│   ├── docker-engine-install.md
│   ├── docker-permission.md
│   └── learning-roadmap.md
├── 01-*/
├── 02-*/
├── 03-*/
├── 04-wordpress/
├── 05-laravel/
├── 06-reverse-proxy/
└── 07-nextjs/
```

各番号フォルダのREADMEは、その技術固有の構築・確認・トラブル対応を担当します。

```text
ルートREADME
→ プロジェクト全体の案内

docs/
→ 共通知識

各番号フォルダのREADME
→ 個別環境の運用手順
```

## 学習ロードマップ

### 01〜03 基礎構成

Docker、Nginx、PHP、MySQLを小さく分けて動かし、Compose、network、volume、bind mountの基礎を確認します。

### 04 WordPress

Nginx、PHP-FPM、MySQLを組み合わせ、WordPressをコンテナ上で動かします。

### 05 Laravel

Composer、migration、MySQL、Node.js、Vite、Tailwind CSSを含むLaravel開発環境を構築します。

### 06 Reverse Proxy

複数のComposeプロジェクトを外部ネットワーク`web-gateway`で接続し、ドメイン名で各サービスへ振り分けます。

```text
wordpress.test → WordPress
laravel.test   → Laravel
next.test      → Next.js
```

### 07 Next.js

Node.js公式イメージ上でNext.jsを動かし、常駐コンテナと使い捨てコンテナの違いを確認します。

## 最短起動例

共有ネットワークを初回だけ作成します。

```bash
docker network create web-gateway
```

各環境を起動します。

```bash
cd 04-wordpress
docker compose up -d

cd ../05-laravel
docker compose up -d

cd ../07-nextjs
docker compose up -d

cd ../06-reverse-proxy
docker compose up -d
```

状態確認:

```bash
docker compose ps
```

## Windows hosts

次のファイルへローカルドメインを追加します。

```text
C:\Windows\System32\drivers\etc\hosts
```

```text
127.0.0.1 wordpress.test
127.0.0.1 laravel.test
127.0.0.1 next.test
```

保存後、通常はすぐ反映されます。

必要な場合のみ、管理者権限のPowerShellまたはコマンドプロンプトで実行します。

```cmd
ipconfig /flushdns
```

確認:

```powershell
ping next.test
curl.exe -I http://next.test
```

ブラウザではHTTPを明示します。

```text
http://wordpress.test
http://laravel.test
http://next.test
```

## このプロジェクトで頻繁に使うDockerコマンド

### Compose設定確認

```bash
docker compose config
```

YAMLとCompose設定を検査します。

### 起動

```bash
docker compose up -d
```

### Dockerfileを含めて再ビルド

```bash
docker compose up -d --build
```

### 状態確認

```bash
docker compose ps
```

停止済みも含める:

```bash
docker compose ps -a
```

### ログ確認

```bash
docker compose logs -f
```

サービス指定:

```bash
docker compose logs -f nginx
docker compose logs -f php
docker compose logs -f mysql
docker compose logs -f node
docker compose logs -f next
```

### コンテナ内でコマンド実行

```bash
docker compose exec php sh
docker compose exec next sh
```

### 使い捨てコンテナで単発処理

```bash
docker compose run --rm next npm install
docker compose run --rm next npm run build
```

### 停止

```bash
docker compose stop
```

### コンテナとネットワークを削除

```bash
docker compose down
```

### volumeも含めて削除

```bash
docker compose down -v
```

`-v`はMySQLなどの永続データも削除します。DBを初期化したい場合以外は使用しません。

### ネットワーク確認

```bash
docker network ls
docker network inspect web-gateway
```

### イメージ・コンテナ確認

```bash
docker images
docker ps
docker ps -a
```

## チーム開発への展開

このリポジトリでは、実行環境をDocker Composeと設定ファイルで共有します。

そのため、新しいPCや別メンバーの環境でも、次の差分を減らせます。

```text
PHP・Node.js・MySQLのバージョン差
各自のローカル設定差
Webサーバー設定差
必要パッケージの入れ忘れ
ポートや接続先の認識違い
```

Gitから取得し、必要な環境変数を用意してComposeを起動すれば、同じLinux環境を再現できます。

```text
Git clone
↓
環境変数を準備
↓
docker compose up -d
↓
共通環境で開発開始
```

実際のチーム開発では、次も追加対象になります。

- `.env.example`
- 初期化スクリプト
- migration・seed
- healthcheck
- CIによるbuild・test
- 本番用Dockerfile
- secrets管理

このリポジトリは、その一歩手前となるローカル開発環境の土台です。

## LG流まとめ

```text
環境を手作業で合わせるな。
設計図を共有して、同じ環境を再現しろ。
```

Dockerは魔法ではありません。

Linux上のWebサーバー、実行環境、データベース、ネットワークを小さく分け、Composeで配線したものです。

基礎を理解したうえで使えば、Dockerは新しいPCへの移行、チーム開発、検証環境の再構築を大幅に楽にします。
