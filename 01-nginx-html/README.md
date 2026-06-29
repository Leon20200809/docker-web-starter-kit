# 01-nginx-html

## 目的

Docker Composeを使って、Nginxコンテナから静的HTMLを表示する最小構成。

このステップでは、PCやWSLにNginxを直接インストールせず、DockerイメージからNginx入りのコンテナを起動して、ローカルのHTMLファイルをブラウザで表示する。

## 構成

```text
01-nginx-html/
├─ docker-compose.yml
└─ html/
   └─ index.html
```

## 起動方法

```bash
cd 01-nginx-html
docker compose up -d
```

ブラウザで確認。

```text
http://localhost:8080
```

## 停止方法

```bash
docker compose down
```

## 状態確認

```bash
docker compose ps
```

Docker Engine全体のコンテナを見る場合。

```bash
docker ps
```

## docker-compose.yml の本質

```yaml
services:
  nginx:
    image: nginx:stable-alpine
    container_name: docker-web-starter-nginx-html
    ports:
      - "8080:80"
    volumes:
      - ./html:/usr/share/nginx/html:ro
```

### `image: nginx:stable-alpine`

Nginx公式イメージを使う。

`stable-alpine` は、Alpine Linuxベースの軽量なNginxイメージ。

これは「Nginxだけの裸の実行ファイル」ではなく、Nginxを動かすための最小Linux部品を含んだDockerイメージ。

ただし、Linuxカーネルを丸ごと持っているわけではない。
カーネルはホスト側、今回はWSL2 Ubuntu側のLinuxカーネルを共有している。

```text
WSL2 Ubuntu / Linuxカーネル
↓
Docker Engine
↓
nginx:stable-alpine コンテナ
  ├─ Alpine Linuxの最小ユーザーランド
  └─ Nginx
```

### `ports: "8080:80"`

ホスト側の8080番ポートを、コンテナ内の80番ポートへ接続する。

```text
ブラウザ
↓
http://localhost:8080
↓
コンテナ内のNginx 80番
```

左がホスト側、右がコンテナ側。

```text
8080:80
```

### `volumes: ./html:/usr/share/nginx/html:ro`

ローカルの `./html` フォルダを、コンテナ内のNginx公開フォルダに接続する。

```text
ローカル
./html

↓

コンテナ内
/usr/share/nginx/html
```

これにより、`html/index.html` を編集すると、NginxコンテナがそのHTMLを配信する。

`:ro` は read only の意味。
コンテナ側からは読み取り専用にする。

## 学んだこと

Dockerでは、WebサーバーをOSに直接インストールしなくても、Nginx入りのコンテナを起動して利用できる。

```text
従来:
OSにNginxをインストールする

Docker:
Nginx入りのイメージを取得して、コンテナとして起動する
```

Docker Composeは、コンテナの構成を定義する作戦書。

```text
どのイメージを使うか
どのポートをつなぐか
どのフォルダをコンテナに見せるか
どんな名前で起動するか
```

これらを `docker-compose.yml` に書く。

## image と container の違い

```text
image
  コンテナの元になる設計図・素材

container
  imageから実際に起動した実体
```

初回起動時、ローカルに `nginx:stable-alpine` がなければDocker Hubから取得される。
一度取得したimageはローカルに保存されるため、次回以降は基本的にローカルのimageを使って起動する。

## 確認コマンド

ローカルにあるDockerイメージを見る。

```bash
docker images
```

動いているコンテナを見る。

```bash
docker ps
```

Compose管理下のコンテナを見る。

```bash
docker compose ps
```

## このステップの本質

```text
ローカルのHTML
↓
Dockerコンテナ内のNginx
↓
ブラウザ表示
```

Nginxを直接インストールするのではなく、Nginx入りの箱を起動して使う。

これがDockerによるWeb開発環境の第一形態。
