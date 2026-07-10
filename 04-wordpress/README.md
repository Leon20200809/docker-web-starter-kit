# 04 WordPress Docker Lab

Nginx + WordPress PHP-FPM + MySQL を Docker Compose で分隊構成し、WordPress をローカル環境で起動する演習です。

この演習の目的は、単に WordPress を動かすことではありません。

- Webサーバー
- PHP実行環境
- データベース
- 永続化
- 書き込み権限
- 起動順制御
- Docker volume の破壊と再作成

これらを、実際に壊して戻せる形で理解することを目的とします。

---

## 構成

```text
ブラウザ
  ↓ http://localhost:8082
Nginx
  ↓ php:9000
WordPress PHP-FPM
  ↓ mysql:3306
MySQL
```

---

## 使用イメージ

| サービス | イメージ             | 役割                              |
| -------- | -------------------- | --------------------------------- |
| nginx    | nginx:stable-alpine  | HTTPリクエストを受けるWebサーバー |
| php      | wordpress:php8.3-fpm | WordPress本体 + PHP-FPM           |
| mysql    | mysql:8.0            | WordPress用データベース           |

---

## ディレクトリ構成

```text
04-wordpress/
├── README.md
├── docker-compose.yml
├── mysql/
│   └── init/
│       └── .gitkeep
├── nginx/
│   └── default.conf
├── php/
├── src/
└── wordpress/
```

### 補足

`src/` と `php/` は前段階の PHP / MySQL 演習から残っている作業用ディレクトリです。

今回の WordPress 構成では、主に以下を使います。

```text
docker-compose.yml
nginx/default.conf
mysql/init/
wordpress/
```

---

## 重要な考え方

### Nginx側は読み取り専用

```yaml
- ./wordpress:/var/www/html:ro
```

NginxはWordPressファイルを読むだけです。

そのため、Nginx側のマウントは `:ro` を付けて読み取り専用にします。

```text
Nginx
＝ 門番
＝ ファイルを読むだけ
```

---

### WordPress PHP-FPM側は書き込み可能

```yaml
- ./wordpress:/var/www/html
```

WordPressは管理画面から以下のようなファイル操作を行います。

```text
wp-config.php 作成
wp-content/uploads/ への画像保存
wp-content/languages/ への言語ファイル保存
wp-content/plugins/ へのプラグイン追加
wp-content/themes/ へのテーマ追加
```

そのため、PHP-FPM側のマウントには `:ro` を付けません。

```text
WordPress PHP-FPM
＝ WordPress本体
＝ 読む・書く・作る
```

---

### MySQLはDocker volumeで永続化

```yaml
mysql_data:/var/lib/mysql
```

MySQLのデータはDocker volumeに保存します。

```text
docker compose down
→ コンテナは消えるがDBデータは残る

docker compose down -v
→ DB volumeも消える
→ WordPressの投稿・設定・ユーザー情報も消える
```

---

## docker-compose.yml の要点

```yaml
services:
  nginx:
    image: nginx:stable-alpine
    ports:
      - "8082:80"
    volumes:
      - ./wordpress:/var/www/html:ro
      - ./nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - php

  php:
    image: wordpress:php8.3-fpm
    environment:
      WORDPRESS_DB_HOST: mysql:3306
      WORDPRESS_DB_NAME: starter_db
      WORDPRESS_DB_USER: starter_user
      WORDPRESS_DB_PASSWORD: starter_pass
    volumes:
      - ./wordpress:/var/www/html
    depends_on:
      mysql:
        condition: service_healthy

  mysql:
    image: mysql:8.0
    command:
      - --character-set-server=utf8mb4
      - --collation-server=utf8mb4_unicode_ci
    environment:
      MYSQL_ROOT_PASSWORD: root_pass
      MYSQL_DATABASE: starter_db
      MYSQL_USER: starter_user
      MYSQL_PASSWORD: starter_pass
    volumes:
      - mysql_data:/var/lib/mysql
      - ./mysql/init:/docker-entrypoint-initdb.d:ro
    ports:
      - "3307:3306"
    healthcheck:
      test:
        [
          "CMD-SHELL",
          "mysqladmin ping -h 127.0.0.1 -uroot -p$${MYSQL_ROOT_PASSWORD}",
        ]
      interval: 10s
      timeout: 5s
      retries: 30
      start_period: 300s

volumes:
  mysql_data:
```

---

## Nginx設定

`nginx/default.conf`

```nginx
server {
    # コンテナ内のNginxは80番で待ち受ける
    listen 80;

    # 公開フォルダ
    # docker-compose.yml で ./wordpress を /var/www/html に見せている
    root /var/www/html;

    # 最初に探すファイル
    index index.php index.html;

    # 通常アクセス
    # ファイルがあれば返す
    # なければ index.php に渡す
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # .php にアクセスされた時の処理
    # Nginx自身はPHPを実行できないので、PHP-FPMへ渡す
    location ~ \.php$ {
        include fastcgi_params;

        # php は docker-compose.yml のサービス名
        # 9000 はPHP-FPMが待ち受ける標準ポート
        fastcgi_pass php:9000;

        # 実行するPHPファイルのパスをPHP-FPMへ伝える
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

---

## 起動前チェック

Compose設定の最終形を確認します。

```bash
docker compose config
```

このコマンドはコンテナを起動せず、Composeが解釈した最終設定を表示します。

確認ポイント。

```text
nginx:
  ./wordpress → /var/www/html
  read_only: true

php:
  ./wordpress → /var/www/html
  read_onlyなし

mysql:
  mysql_data → /var/lib/mysql
```

---

## 起動

```bash
docker compose up -d
```

初回起動時はMySQLの初期化に時間がかかります。

確認できた例。

```text
✔ Container 04-docker-web-starter-mysql                 Healthy  254.3s
✔ Container 04-wordpress-php-fpm                        Started  255.4s
✔ Container 04-docker-web-starter-nginx-wordpress-mysql Started  256.3s
```

これは以下の順で起動できたことを示します。

```text
MySQL healthy
↓
WordPress PHP-FPM Started
↓
Nginx Started
```

---

## healthcheck の目的

通常の `depends_on` は、コンテナの起動順しか制御しません。

```yaml
depends_on:
  - mysql
```

これは以下の意味です。

```text
MySQLコンテナを先に起動する
```

しかし、これは以下を保証しません。

```text
MySQLが接続可能になっている
```

そのため、MySQLに `healthcheck` を追加し、PHP-FPM側で `service_healthy` を待ちます。

```yaml
depends_on:
  mysql:
    condition: service_healthy
```

これにより、WordPressがMySQLへ接続する前に、MySQLの準備完了を待てます。

---

## 初回アクセス

ブラウザでアクセスします。

```text
http://localhost:8082
```

WordPressの初期設定画面が表示されれば成功です。

入力例。

```text
サイトタイトル：
Docker WordPress Lab

ユーザー名：
admin_test

パスワード：
必ずメモする

メールアドレス：
test@example.com
```

---

## 動作確認チェックリスト

### 1. コンテナ状態

```bash
docker compose ps
```

確認ポイント。

```text
mysql   Up ... (healthy)
php     Up
nginx   Up
```

---

### 2. WordPress本体ファイル

```bash
ls -lah wordpress | head
```

確認ポイント。

```text
wp-admin
wp-content
wp-includes
wp-config.php
index.php
```

---

### 3. wp-content

```bash
ls -lah wordpress/wp-content
```

確認ポイント。

```text
languages
plugins
themes
uploads
upgrade
```

---

### 4. 管理画面ログイン

```text
http://localhost:8082/wp-admin
```

---

### 5. 投稿作成

投稿を1件作成します。

```text
タイトル：
Docker WordPress 起動確認

本文：
Nginx + WordPress PHP-FPM + MySQL 構成で起動確認。
```

投稿が保存され、再読み込み後も残ればMySQL保存成功です。

---

### 6. メディアアップロード

画像を1枚アップロードします。

成功すれば、以下への書き込みが確認できます。

```text
wordpress/wp-content/uploads/
```

---

## 完全初期化

WordPressを最初から作り直す場合。

```bash
docker compose down -v
find wordpress -mindepth 1 -maxdepth 1 -exec rm -rf {} +
docker compose up -d
```

### それぞれの意味

```text
docker compose down -v
→ コンテナ・ネットワーク・MySQL volumeを削除

find wordpress ...
→ wordpress/ フォルダの中身だけ削除

docker compose up -d
→ WordPress本体とMySQLを再構築
```

---

## 通常停止

DBを残して止める場合。

```bash
docker compose down
```

再起動。

```bash
docker compose up -d
```

`-v` を付けなければ、MySQLのDBデータは残ります。

---

## Docker volume確認

```bash
docker volume ls
```

例。

```text
local     04-wordpress_mysql_data
```

04のComposeプロジェクトで `down -v` を実行すると、基本的にこの volume が削除されます。

別プロジェクトの volume は通常削除されません。

例。

```text
03-nginx-php-mysql_mysql_data
```

これは03側のvolumeなので、04側の `down -v` では消えません。

---

## よくあるエラー

### Error establishing a database connection

原因候補。

```text
MySQLがまだ起動中
DB接続情報が違う
wp-config.php のDB設定が古い
MySQL volumeに古い状態が残っている
```

確認。

```bash
docker compose ps
docker compose logs mysql --tail=80
docker compose logs php --tail=80
grep "DB_" wordpress/wp-config.php
```

---

### dependency mysql failed to start

原因候補。

```text
MySQLがhealthyになる前にhealthcheckの上限に達した
MySQLの初期化が想定より遅い
healthcheck設定が厳しすぎる
```

対応例。

```yaml
healthcheck:
  test:
    [
      "CMD-SHELL",
      "mysqladmin ping -h 127.0.0.1 -uroot -p$${MYSQL_ROOT_PASSWORD}",
    ]
  interval: 10s
  timeout: 5s
  retries: 30
  start_period: 300s
```

---

## 学んだコマンド

### Compose設定確認

```bash
docker compose config
```

Composeが解釈した最終設定を見る。

---

### 起動

```bash
docker compose up -d
```

バックグラウンド起動。

---

### 状態確認

```bash
docker compose ps
docker ps
```

---

### ログ確認

```bash
docker compose logs mysql --tail=80
docker compose logs php --tail=80
docker compose logs mysql -f
```

`-f` は follow。ログを追跡します。

---

### 停止

```bash
docker compose down
```

DB volumeは残ります。

---

### 完全破壊

```bash
docker compose down -v
```

DB volumeも削除します。

---

## 今回の成功判定

以下を確認済み。

```text
WordPress初期インストール OK
日本語選択画面 OK
管理画面ログイン OK
投稿作成 OK
画像アップロード OK
wp-content/uploads 生成 OK
MySQL healthcheck OK
service_healthy 起動順制御 OK
```

---

## 重要まとめ

```text
Nginx
＝ 読むだけ
＝ ./wordpress:/var/www/html:ro

WordPress PHP-FPM
＝ WordPressを実行し、ファイルも書く
＝ ./wordpress:/var/www/html

MySQL
＝ 投稿・設定・ユーザー情報を保存
＝ mysql_data:/var/lib/mysql
```

```text
depends_on
＝ 起動順だけ

healthcheck + service_healthy
＝ 本当に使える状態になってから次を起動
```

---

## 一言メモ

WordPressは、ただ実行されるだけの固定プログラムではありません。

管理画面の操作によって、DBとファイルの両方に状態を蓄積するアプリケーションです。

そのため、Dockerでも以下の理解が重要になります。

```text
どのコンテナが読むだけか
どのコンテナが書く必要があるか
どのデータをvolumeに残すか
どのタイミングで次のサービスを起動するか
```

DockerでWordPressを動かすとは、単にコンテナを起動することではなく、Webサーバー・PHP実行環境・DB・ファイル権限・永続化を分隊として設計することです。
