# 商品采集与浏览器备用服务

插件先通过 WordPress HTTP API 访问 `valid_format` 商品地址。遇到 Cloudflare 验证或 HTTP 请求失败时，可以改用 FlareSolverr 内的 Chromium 获取渲染后的页面。规格解析和库存校验共用此入口，不访问推广链接。浏览器与 WordPress 插件分别发布；插件 ZIP 可单独安装。

## 部署

将 [browser.compose.yml](../deploy/browser.compose.yml) 放到 WordPress 的 Compose 目录，确认其中外部网络名与 WordPress 相同。默认网络为 `npm_default`，其他网络可通过 `ASV_DOCKER_NETWORK` 指定。

```sh
docker compose -f browser.compose.yml -p autosalevps up -d
```

## 在插件界面设置地址

管理员可以点击“查看VPS状态”右侧的“采集设置”，填写 WordPress 服务器可访问的服务地址，例如 `http://asv-browser:8191`，然后点击“保存设置”。默认值为 `http://asv-browser:8191`；“恢复默认”填回默认地址，点击保存后生效。留空保存也恢复默认值。

设置保存在 WordPress 数据库中，页面重载和插件升级后仍然保留。插件自动补上 API 路径 `/v1`，也兼容粘贴完整的 `/v1` 地址。仅管理员可以读取和修改设置，保存时验证 HTTP/HTTPS 地址。

更换地址后不会沿用旧采集服务的失败冷却；厂商返回 429 时的限流冷却仍然生效。

后台保存值优先于旧版 mu-plugin 常量；尚未在后台保存过时，可以继续使用自定义 mu-plugin（例如 `wp-content/mu-plugins/autosalevps-browser.php`）中的端点：

```php
<?php
if ( ! defined( 'ASV_BROWSER_ENDPOINT' ) ) {
    define( 'ASV_BROWSER_ENDPOINT', 'http://asv-browser:8191/v1' );
}
```

服务只加入 Docker 网络，不映射宿主机端口。镜像使用 `ghcr.io/flaresolverr/flaresolverr:v3.5.2`，Compose 固定其 OCI 索引摘要。该索引包含 `linux/amd64`、`linux/386`、`linux/arm64`、`linux/arm/v7`。服务的内存上限为 768 MB，CPU 上限为 1 核。使用前应确认服务器有足够资源。浏览器可能仍然无法处理人工验证码或持续封禁，此时库存会标记为待确认。

## 运行方式

- 成功页面缓存 120 秒，规格解析与紧接着的库存检查可复用请求。
- 同一域名的手动检查、规格采集与 Cron 使用共享请求锁，遵循商品的 `valid_vps_time` 间隔。
- 429 遵守 `Retry-After`，不立即尝试浏览器；其他采集失败进入 5 分钟域名冷却。
- HTTP 200 中的挑战页也会被拒绝，不交给 LLM 判断库存，不作为商品页面缓存。
- 正常 WHMCS 商品配置表单可以直接确认可下单；明确缺货信息确认售罄。其他正常页面按需交给 LLM。
- 检查失败保留上次确认的库存和确认时间，另存本次尝试时间与失败原因。没有历史结果时显示“待确认”；保留的历史结果显示“待复核”。
- 成功提取的规格保留 7 天，用于短时故障回退；已有快照中的规格也会保留。浏览器 Cookie 不发送给 LLM，也不写入插件日志。

## 验证与恢复

先从 WordPress 容器查询服务健康接口，例如：

```sh
docker compose exec wordpress-app curl -fsS http://asv-browser:8191/health
```

将 `wordpress-app` 替换为实际 Compose 服务名。返回 `{"status":"ok"}` 表示 WordPress 能连接浏览器服务。再在 WordPress 后台点击“查看VPS状态”，确认规格、库存及日志。主站首页可访问不代表 `my.racknerd.com` 商品页可访问，应以商品请求结果为准。

停用备用服务容器时运行：

```sh
docker compose -f browser.compose.yml -p autosalevps stop
```

普通 HTTP 采集仍可使用，需要浏览器的页面会暂时显示待确认。迁移服务后在“采集设置”中保存新地址。需要回滚代码时，恢复升级前的整个 `AutoSaleVPS/` 插件目录；站点配置和状态保存在 uploads 或 WordPress 数据库中。
