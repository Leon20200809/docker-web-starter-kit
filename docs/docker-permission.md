# Docker 権限・UID/GID・ローカル開発

## 目的

Dockerを**sudoなし**で扱い、ホスト側にも正しい所有者でファイルを生成できるようにする。

## dockerグループ

```bash
sudo usermod -aG docker $USER
newgrp docker
```

確認

```bash
groups
docker run hello-world
```

## docker.sock

```text
/var/run/docker.sock
```

Docker Engineとの通信窓口。

```bash
ls -l /var/run/docker.sock
```

通常

```text
srw-rw---- root docker
```

## UID/GID

Linuxは名前ではなく数字で管理する。

```text
ホスト
UID1000 = espo

コンテナ
UID1000 = node
```

表示名は違っても同じ所有者として扱われる。

Composeでは

```yaml
user: "1000:1000"
```

を利用し、bind mount先へroot所有ファイルを作らない。

## bind mount確認

```bash
find src ! -user "$USER" -ls | head
```

何も表示されなければ正常。

## Next.jsで学んだこと

`create-next-app` は作成先だけでなく**親ディレクトリの書き込み権限**も確認する。

そのため `/app` ではなく、一時的に

```text
/workspace
```

へbind mountして生成した。

## Windows hosts

場所

```text
C:\Windows\System32\drivers\etc\hosts
```

追加

```text
127.0.0.1 wordpress.test
127.0.0.1 laravel.test
127.0.0.1 next.test
```

通常は保存で即反映。

必要時のみ

```cmd
ipconfig /flushdns
```

確認

```powershell
ping next.test
curl.exe -I http://next.test
```

## 本質

hosts はWindowsの住所録。

Reverse ProxyはHostヘッダーを見てWordPress・Laravel・Next.jsへ振り分ける門番。
