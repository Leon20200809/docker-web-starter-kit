# 02-nginx-php-fpm

## 目的

Docker Composeを使って、NginxコンテナとPHP-FPMコンテナを分けて起動する。

このステップでは、NginxをWebサーバー役、PHP-FPMをPHP実行役として分離する。

```text
ブラウザ
↓
Nginxコンテナ
↓
PHPファイルならPHP-FPMコンテナへ渡す
↓
PHP-FPMがPHPを実行
↓
Nginx経由でブラウザへ返す
```

---

## 構成

```text
02-nginx-php-fpm/
├─ docker-compose.yml
├─ nginx/
│  └─ default.conf
├─ php/
│  └─ Dockerfile
└─ src/
   ├─ index.php
   └─ assets/
      ├─ css/
      │  └─ style.css
      └─ img/
         └─ docker-nginx.svg
```

---

## 起動方法

```bash
cd 02-nginx-php-fpm
docker compose up -d --build
```

ブラウザで確認。

```text
http://localhost:8081
```

---

## 停止方法

```bash
docker compose down
```

---

## 状態確認

Compose管理下のコンテナを見る。

```bash
docker compose ps
```

Docker Engine全体で動いているコンテナを見る。

```bash
docker ps
```

ローカルに保存されているDockerイメージを見る。

```bash
docker images
```

PHPコンテナ内のPHPバージョンを見る。

```bash
docker compose exec php php -v
```

---

## docker-compose.yml の本質

```yaml
services:
  nginx:
    image: nginx:stable-alpine
    container_name: docker-web-starter-nginx-php
    ports:
      - "8081:80"
    volumes:
      - ./src:/var/www/html:ro
      - ./nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - php

  php:
    build:
      context: ./php
    container_name: docker-web-starter-php-fpm
    volumes:
      - ./src:/var/www/html:ro
```

### `nginx`

Webサーバー役。  
ブラウザからのHTTPリクエストを受ける入口。

HTML、CSS、画像、SVGなどの静的ファイルは、Nginxがそのまま返す。

### `php`

PHP実行役。  
Nginxから渡されたPHPファイルをPHP-FPMが実行する。

Nginx自身はPHPを実行できない。  
そのため、`.php` へのアクセスはPHP-FPMへ渡す必要がある。

---

## `volumes` の意味

```yaml
- ./src:/var/www/html:ro
```

ローカルの `./src` を、コンテナ内の `/var/www/html` として見せる。

```text
ローカル
./src/index.php

↓

Nginxコンテナ内
/var/www/html/index.php

↓

PHP-FPMコンテナ内
/var/www/html/index.php
```

NginxとPHP-FPMの両方が、同じPHPファイルを同じパスで見られるようにする。

`:ro` は read only。  
コンテナ側からは読み取り専用。

---

## nginx/default.conf の本質

Nginxに対して、PHPファイルをPHP-FPMへ渡すルールを書く。

```nginx
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass php:9000;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

### `fastcgi_pass php:9000`

`php` は `docker-compose.yml` のサービス名。  
Docker Compose内では、サービス名でコンテナ同士が通信できる。

```text
Nginxコンテナ
↓
php:9000
↓
PHP-FPMコンテナ
```

---

## php/Dockerfile の本質

```Dockerfile
FROM php:8.3-fpm-alpine

WORKDIR /var/www/html
```

Docker Hubの公式PHP-FPMイメージを土台にして、自分用のPHP-FPMイメージを作る。

```text
php:8.3-fpm-alpine
↓
Dockerfileでカスタム
↓
ローカルのPHP-FPMイメージ
↓
PHP-FPMコンテナ起動
```

今回のカスタムは、作業ディレクトリを `/var/www/html` にするだけ。

今後、WordPressやLaravelに進むと、PHP拡張やComposerなどをDockerfileに追加していく。

---

## `image` と `build` の違い

```yaml
image: nginx:stable-alpine
```

既製品のDockerイメージをそのまま使う。

```yaml
build:
  context: ./php
```

Dockerfileを読んで、自分用のDockerイメージを作る。

```text
image = 既製品の箱を使う
build = Dockerfileから自分用の箱を作る
```

---

## 静的ファイルとPHPの違い

CSSや画像はNginxがそのまま返す。

```text
/assets/css/style.css
/assets/img/docker-nginx.svg
↓
Nginxが処理
```

PHPファイルはPHP-FPMへ渡される。

```text
/index.php
↓
Nginx
↓
PHP-FPM
↓
PHP実行結果を返す
```

ブラウザのDevToolsで確認すると、PHPファイルには以下のようなヘッダーが出る。

```text
server: nginx/1.30.3
x-powered-by: PHP/8.3.31
```

画像やCSSには `x-powered-by: PHP` は出ない。  
これはPHP-FPMを通っていない証拠。

---

## 第一形態との違い

第一形態。

```text
NginxだけでHTMLを返す
```

第二形態。

```text
Nginxが入口になり、PHP-FPMがPHPを実行する
```

第一形態では、Nginx公式イメージのデフォルト設定だけでHTMLを表示した。

第二形態では、NginxとPHP-FPMを接続するために `nginx/default.conf` を自分で書いた。

---

## このステップの本質

```text
WebサーバーとPHP実行環境を別パーツとして分ける
```

Dockerでは、ApacheにPHPを追加武装する感覚ではなく、役割ごとのコンテナを組み合わせる。

```text
Nginx
  = HTTPの入口

PHP-FPM
  = PHP実行専用部隊
```

この責務分離が、WordPressやLaravelのDocker環境につながる。
