# Docker Laravel Starter

Docker Compose で Laravel / Nginx / PHP-FPM / MySQL の開発環境を構築する学習用ディレクトリです。

## 構成

```text
05-laravel/
├── docker-compose.yml
├── nginx/
│   └── default.conf
├── php/
│   └── Dockerfile
├── mysql/
│   └── init/
└── src/
    └── Laravel本体
```

| Service | Role                             | Image / Build         |
| ------- | -------------------------------- | --------------------- |
| nginx   | Webサーバー                      | `nginx:stable-alpine` |
| php     | PHP-FPM / Composer / Laravel実行 | `php:8.3-fpm-alpine`  |
| mysql   | データベース                     | `mysql:8.0`           |

## 起動

```bash
docker compose up -d --build
docker compose ps
```

ブラウザ:

```text
http://localhost:8083
```

MySQLをホスト側から使う場合:

```text
localhost:3308
```

コンテナ間通信では `mysql:3306` を使用します。

## Laravel初回生成

`src/` が空であることを確認します。

```bash
ls -A src
```

PHPコンテナへ、ホストユーザーと同じUID・GIDで入ります。

```bash
docker compose exec --user "$(id -u):$(id -g)" php sh
```

Laravelを現在地 `/var/www/html` に生成します。

```sh
HOME=/tmp COMPOSER_HOME=/tmp/composer \
composer create-project laravel/laravel .
```

終了:

```sh
exit
```

UID・GIDを合わせる理由は、生成ファイルをホスト側で `espo:espo` 所有にするためです。

## MySQL接続

`src/.env` を変更します。

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel_db
DB_USERNAME=laravel_user
DB_PASSWORD=laravel_pass
```

設定キャッシュを消し、MySQLへテーブルを作成します。

```bash
docker compose exec php php artisan config:clear
docker compose exec php php artisan migrate
```

## Nginx設定の要点

Laravelの公開ディレクトリは `public/` です。

```nginx
root /var/www/html/public;
```

PHPはComposeのサービス名で接続します。

```nginx
fastcgi_pass php:9000;
```

設定変更後:

```bash
docker compose restart nginx
```

## VS Code

WSL内のコードはWSLモードで開きます。

```bash
cd ~/projects/docker-web-starter-kit/05-laravel/src
code .
```

左下に `WSL: Ubuntu` と表示されていることを確認します。

Laravel Extra Intellisenseでコンテナ内PHPを使う場合、`src/.vscode/settings.json` に設定します。

```json
{
  "LaravelExtraIntellisense.phpCommand": "docker compose -f ../docker-compose.yml exec -T -w /var/www/html php php -r \"{code}\"",
  "LaravelExtraIntellisense.basePathForCode": "/var/www/html"
}
```

## よく使うコマンド

```bash
docker compose ps
docker compose logs
docker compose logs php
docker compose logs nginx
docker compose logs mysql
docker compose exec php php artisan migrate
docker compose exec php composer --version
docker compose restart nginx
docker compose down
```

## 注意

```bash
docker compose down
```

コンテナとネットワークを削除します。MySQLのnamed volumeは残ります。

```bash
docker compose down -v
```

named volumeも削除します。DBを初期化したい場合以外は使用しません。

## この構成で学ぶこと

- Nginx / PHP-FPM / MySQLの責務分離
- Docker Composeのサービス間通信
- bind mountとLinux所有権
- Laravelの`.env`による接続先切り替え
- migrationによるDB構築
- WSL / VS Code / Dockerの実行環境の違い

`src/` は検証用Laravel本体のためGit管理対象外とし、空ディレクトリ維持用の `.gitkeep` のみ管理します。
