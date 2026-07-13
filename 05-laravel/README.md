# Docker Laravel Starter

Docker Compose で **Laravel / Nginx / PHP-FPM / MySQL / Node.js（Vite）** を分離して動かす学習用環境です。

Laravel本体はホスト側の `src/` に置き、PHP・Composer・npm・Viteはコンテナから利用します。

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

| Service | 役割                           | Image / Build         | 公開ポート  |
| ------- | ------------------------------ | --------------------- | ----------- |
| nginx   | HTTP受付・静的ファイル配信     | `nginx:stable-alpine` | `8083:80`   |
| php     | PHP-FPM・Composer・Laravel実行 | `php:8.3-fpm-alpine`  | なし        |
| mysql   | データベース                   | `mysql:8.0`           | `3308:3306` |
| node    | npm・Vite・Tailwindビルド      | `node:24-alpine`      | `5173:5173` |

```text
ブラウザ → localhost:8083 → Nginx → PHP-FPM → Laravel → MySQL
                                   ↘ Vite :5173
```

## 起動

```bash
docker compose config
docker compose up -d --build
docker compose ps
```

Laravel:

```text
http://localhost:8083
```

Viteは画面本体ではなく、CSS・JavaScript・HMRを配信します。

```text
http://localhost:5173
```

## Laravel初回生成

`src/` が空であることを確認します。

```bash
ls -A src
```

何も表示されなければ空です。

PHPコンテナへ、ホストと同じUID・GIDで入ります。

```bash
docker compose exec --user "$(id -u):$(id -g)" php sh
```

Laravelを `/var/www/html` に生成します。

```sh
HOME=/tmp COMPOSER_HOME=/tmp/composer \
composer create-project laravel/laravel .
```

```sh
exit
```

UID・GIDを合わせることで、生成ファイルがホスト側で `espo:espo` 所有になります。

## MySQL接続

Laravel初期状態はSQLiteを使用するため、`src/.env` をMySQLへ変更します。

```env
APP_URL=http://localhost:8083

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel_db
DB_USERNAME=laravel_user
DB_PASSWORD=laravel_pass
```

```bash
docker compose exec php php artisan config:clear
docker compose exec php php artisan migrate
```

`DB_HOST=mysql` はComposeのサービス名です。`3308` はホスト側から接続するときだけ使用します。

## Nginx設定の要点

Laravelは `public/` だけをWeb公開します。

```nginx
root /var/www/html/public;
index index.php index.html;
```

Laravelのルーティングへ渡します。

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

PHP-FPMはComposeのサービス名で接続します。

```nginx
fastcgi_pass php:9000;
fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
```

設定変更後:

```bash
docker compose restart nginx
```

## Node.js / Vite / Tailwind

Node.jsはPHPコンテナへ混ぜず、専用サービスとして動かします。

```yaml
node:
  image: node:24-alpine
  container_name: 05-node-vite-LaravelApp
  working_dir: /var/www/html
  user: "1000:1000"
  ports:
    - "5173:5173"
  volumes:
    - ./src:/var/www/html
  command: sh -c "npm install && npm run dev -- --host 0.0.0.0"
```

```text
PHPコンテナ  → Laravelを実行
Nodeコンテナ → Vite・Tailwindを実行
```

Nodeコンテナ起動中は、WSL側で別途 `npm run dev` を実行しません。二重起動すると5174番へ退避し、`public/hot` の接続先が競合します。

### ViteのDocker向け設定

`src/vite.config.js` の `server` 設定では、待受住所とブラウザ用住所を分けます。

```js
server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    origin: 'http://localhost:5173',

    hmr: {
        host: 'localhost',
    },

    watch: {
        ignored: ['**/storage/framework/views/**'],
    },
},
```

```text
0.0.0.0  → コンテナ外から接続を受ける待受住所
localhost → Windowsブラウザがアクセスする住所
```

変更後:

```bash
docker compose restart node
```

確認:

```bash
docker compose logs -f node
docker compose port node 5173
curl -I http://localhost:5173/@vite/client
cat src/public/hot
```

理想状態:

```text
Vite client → HTTP 200
public/hot  → http://localhost:5173
```

## Tailwind確認ページ

BladeでViteを読み込みます。

```blade
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

ルートを専用ページへ向ける例:

```php
Route::view('/', 'tailwind-test');
```

`http://localhost:8083` で色・余白・角丸・レスポンシブ表示が反映されれば、Laravel / Vite / Tailwindの接続成功です。

## VS Code

WSL内のコードは、WSLモードで開きます。

```bash
cd ~/projects/docker-web-starter-kit/05-laravel/src
code .
```

左下に `WSL: Ubuntu` と表示されていることを確認し、拡張機能もWSL側へインストールします。

Laravel Extra Intellisenseでコンテナ内PHPを使う場合、`src/.vscode/settings.json` に設定します。

```json
{
  "LaravelExtraIntellisense.phpCommand": "docker compose -f ../docker-compose.yml exec -T -w /var/www/html php php -r \"{code}\"",
  "LaravelExtraIntellisense.basePathForCode": "/var/www/html"
}
```

これにより、補完機能もWSL本体のPHPではなく、コンテナのPHP 8.3を使用します。

## よく使うコマンド

```bash
# 状態確認
docker compose ps
docker compose logs
docker compose logs -f node

# Laravel
docker compose exec php php artisan migrate
docker compose exec php php artisan config:clear
docker compose exec php composer --version

# Node / Vite
docker compose run --rm node npm install
docker compose run --rm node npm run build
docker compose restart node

# Nginx
docker compose restart nginx

# 停止・削除
docker compose down
```

Compose変更時は、通常これで変更されたサービスだけ再作成されます。

```bash
docker compose up -d
```

Dockerfile変更時:

```bash
docker compose up -d --build
```

## トラブル切り分け

| 症状                  | 主な確認箇所                                     |
| --------------------- | ------------------------------------------------ |
| `403 Forbidden`       | Nginxの `root` が `/var/www/html/public` か      |
| SQLite readonly       | `.env` がまだSQLiteになっていないか              |
| Tailwindが効かない    | `@vite`、`public/hot`、5173番、ブラウザのNetwork |
| `ERR_ADDRESS_INVALID` | ViteのURLが `0.0.0.0:5173` になっていないか      |
| PHP補完エラー         | VS CodeがWSLモードか、拡張機能がWSL側か          |
| 所有者がroot          | Composer / npmをUID・GID 1000で実行したか        |

## データ削除の注意

```bash
docker compose down
```

コンテナとネットワークを削除します。MySQLのnamed volumeは残ります。

```bash
docker compose down -v
```

named volumeも削除します。DBを初期化したい場合以外は使用しません。

## Git管理方針

このスターターキットでは、`src/` は検証時に生成するLaravel本体としてGit管理対象外にします。

```gitignore
/05-laravel/src/*
!/05-laravel/src/.gitkeep
```

確認:

```bash
git check-ignore -v 05-laravel/src/artisan
git ls-files 05-laravel/src
```

実案件のLaravelリポジトリでは、`app/`、`routes/`、`resources/` などは開発成果物なのでGit管理します。

## この構成で学ぶこと

- Nginx / PHP-FPM / MySQL / Node.jsの責務分離
- Docker Composeのサービス名によるコンテナ間通信
- bind mountとLinuxのUID・GID
- Laravelの `.env` とmigration
- Viteの待受住所とブラウザ用住所の違い
- Windows / WSL / Docker / VS Codeの実行環境の境界

深掘りするときは、各イメージのDockerfile、Composeのnetwork・volume、Laravel Vite Plugin、HMRのWebSocket通信を個別に確認します。
