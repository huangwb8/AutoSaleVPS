<h1 align="center">AutoSaleVPS</h1>

<p align="center">
  <a href="https://github.com/huangwb8/AutoSaleVPS/releases/latest">下载插件</a> ·
  <a href="docs/browser-fetching.md">浏览器采集</a> ·
  <a href="README.md">English</a>
</p>

AutoSaleVPS 是一个 WordPress 插件，把 VPS 商品的规格、库存和推广文案整理成可嵌入页面的商品卡片。管理员可以配置厂商链接、刷新数据和查看日志；访客从 WordPress 已保存的快照读取卡片，打开页面无需现场请求厂商。

## 快速开始

1. 从 [Release](https://github.com/huangwb8/AutoSaleVPS/releases/latest) 下载 `AutoSaleVPS.zip`，在 WordPress 的「插件 → 安装插件 → 上传插件」中安装并启用。已有站点可在上传时选择替换现有插件。
2. 在后台打开「AutoSaleVPS」，用「编辑VPS配置」填写自己的推广码、商品 PID 和链接。可选：用「编辑模型配置」设置 LLM 服务，再通过「添加KEY」保存密钥；密钥只在鼠标悬停预览图标时显示。
3. 点击「查看VPS状态」拉取第一份快照，再把短码放到公开页面：

   ```text
   [AutoSaleVPS]
   ```

   刷新后应看到商品规格、库存标记和购买按钮。首次还没有快照时，先回后台执行一次刷新。

## 配置模板

从 [config/config.toml](config/config.toml) 复制样例，并替换示例推广码和 PID。商品链接 `sale_format` 用于购买按钮；库存和规格只从 `valid_format` 获取。两种模板都需要 `{pid}`，购买链接可用 `{aff}` 插入推广码。

| 配置 | 用途 |
| --- | --- |
| `aff.<vendor>.code` | 厂商的推广码。 |
| `vps.<vendor>.<pid>` | 商品条目，可用 `human_comment` 附上人工说明。 |
| `url.<vendor>.valid_interval_time` | 后台定时任务判断单项库存何时到期的间隔，单位为秒。 |
| `url.<vendor>.valid_vps_time` | 同一域名请求的随机间隔，例如 `5-10` 秒。 |

[config/model.toml](config/model.toml) 包含 OpenAI 兼容端点的样例。模型用于生成推广文案、整理规格，也可在正常商品页证据不足时辅助判断库存。站点首次运行时会把 TOML 模板复制到 `wp-content/uploads/autosalevps/`，后续升级保留已有配置；API key 和浏览器服务地址保存在 WordPress 数据库中。

## 采集与库存

采集优先走 WordPress HTTP API。遇到验证页或网络失败时，可通过 FlareSolverr 浏览器服务回退；在后台「采集设置」中填写地址，默认值为 `http://asv-browser:8191`。如厂商返回 HTTP 429，插件会等待该域名冷却。安装和故障排查见[浏览器采集指南](docs/browser-fetching.md)。

只有确认的结果会更新库存。失败会保留上次确认的结果和时间，并显示「待复核」；没有历史结果时显示「待确认」。后台「定时任务管理」可查看、启动、暂停或重排 WP-Cron，页面无需持续打开。日志按最近事件优先显示。

## 开发与文档

运行插件由 WordPress 和 PHP 提供环境，已在 PHP 8.3 站点验证。开发使用 Node.js 22+、PHP CLI、Composer 和 `zip`；通过 ZIP 安装时不需要这些构建工具。

```bash
npm ci
composer install
npm run lint
npx tsc --noEmit
npm run test
npm run package
```

`npm run package` 会先构建前端，再生成包含 `AutoSaleVPS/` 目录的 `AutoSaleVPS.zip`。PHP 插件头、运行时版本常量和 `package.json` 的版本必须一致。使用 `npm run dev -- --host` 可启动 Vite；后台接口仍由 WordPress 提供。PHP 风格检查可用 `composer lint`，现有检查状态见[测试记录](docs/test-notes.md)。

更多文档：[安全建议](docs/安全建议.md) · [Argon/PJAX 兼容](docs/argon_support.md) · [额外 CSS 示例](docs/额外CSS示例.css) · [多语言计划](docs/多语言支持.md) · [版本记录](log.md)。

## 许可证

[Apache License 2.0](LICENSE)。
