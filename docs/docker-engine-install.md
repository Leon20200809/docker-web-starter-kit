# Docker Engine インストール（WSL2 Ubuntu）

## 目的

Docker Desktopではなく、**Docker公式APTリポジトリ**から Docker Engine を導入し、WSL2上で実務と同じ構成を再現する。

## 成功条件

```bash
docker --version
docker compose version
sudo docker run hello-world
```

## 手順

1. Ubuntu・CPUアーキテクチャ確認
2. 古いDocker系パッケージ削除
3. `ca-certificates` と `curl` を導入
4. Docker公式GPGキーを登録
5. Docker公式APTリポジトリを追加
6. `docker-ce`・`containerd`・Compose Pluginをインストール
7. `systemctl status docker` でEngine確認
8. `sudo docker run hello-world` で動作確認

## なぜ公式リポジトリなのか

Ubuntu標準の `docker.io` ではなく、Docker公式版を利用するため。

- Docker Engine
- Docker Compose Plugin
- Buildx

を実務と同じ更新経路で管理できる。

## トラブル診断

### dockerコマンドがない

```bash
which docker
docker --version
```

### Docker Engineへ接続できない

```bash
sudo systemctl status docker
sudo systemctl start docker
```

### WSL2でsystemdエラー

`/etc/wsl.conf`

```text
[boot]
systemd=true
```

設定後

```powershell
wsl --shutdown
```

## 次に読む

Dockerを一般ユーザーで安全に使う設定は

`docs/docker-permission.md`
