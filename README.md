<h1 align="center">AutoSaleVPS</h1>

<p align="center">
  <a href="https://github.com/huangwb8/AutoSaleVPS/releases/latest">Download plugin</a> ·
  <a href="docs/browser-fetching.md">Browser fetching</a> ·
  <a href="README_ZH.md">简体中文</a>
</p>

AutoSaleVPS is a WordPress plugin that turns VPS specs, stock status, and affiliate copy into product cards you can embed on a page. Administrators configure vendor links, refresh data, and view logs. Visitors see the saved WordPress snapshot without sending a new request to the vendor on each page load.

## Quick start

1. Download `AutoSaleVPS.zip` from the [latest release](https://github.com/huangwb8/AutoSaleVPS/releases/latest). In WordPress, go to Plugins → Add Plugin → Upload Plugin, then install and activate it. When updating an existing site, choose to replace the installed plugin.
2. Open AutoSaleVPS in the dashboard. Use the VPS configuration editor (编辑VPS配置) to enter your affiliate code, product IDs, and links. For optional LLM features, configure the provider in the model editor (编辑模型配置), then save the key under 添加KEY. The preview reveals the key only while you hover over the eye icon.
3. Click 查看VPS状态 to fetch the first snapshot, then add this shortcode to a public page:

   ```text
   [AutoSaleVPS]
   ```

   Reload the page to see the product specs, stock labels, and purchase links. If the site has no snapshot yet, run the first refresh from the dashboard.

## Configuration

Copy the example from [config/config.toml](config/config.toml) and replace the sample affiliate code and product IDs. The purchase button uses `sale_format`. Stock and specs are fetched through `valid_format`. Both templates require `{pid}`; the purchase URL can also use `{aff}` for the affiliate code.

| Setting | Purpose |
| --- | --- |
| `aff.<vendor>.code` | Affiliate code for the vendor. |
| `vps.<vendor>.<pid>` | Product entry. Use `human_comment` to add a note. |
| `url.<vendor>.valid_interval_time` | Time in seconds before the scheduled job considers a product's stock due for another check. |
| `url.<vendor>.valid_vps_time` | Random wait between requests to the same domain, such as `5-10` seconds. |

[config/model.toml](config/model.toml) includes an example for an OpenAI compatible endpoint. The model writes promo copy, formats specs, and can assess stock when a normal product page is inconclusive. On first run, WordPress copies the TOML templates to `wp-content/uploads/autosalevps/`. Updates preserve existing site configuration. WordPress stores the API key and browser service address in its database.

## Fetching and stock status

The plugin starts with the WordPress HTTP API. A FlareSolverr browser service can handle challenge pages or failed requests. Set its address under 采集设置 (Fetching settings); the default is `http://asv-browser:8191`. An HTTP 429 response puts the vendor domain into cooldown. See the [browser fetching guide](docs/browser-fetching.md) for setup and troubleshooting.

Only confirmed results update stock. After a failed check, the plugin keeps the last confirmed result and timestamp and marks it for review. Products with no previous result are marked unconfirmed. Use 定时任务管理 (Scheduled checks) to view, start, pause, or reschedule WP-Cron without keeping a page open. The log shows recent events first.

## Development and documentation

The plugin runs on WordPress and PHP and was verified on a site running PHP 8.3. Development uses Node.js 22+, PHP CLI, Composer, and `zip`. These build tools are unnecessary when installing the ZIP.

```bash
npm ci
composer install
npm run lint
npx tsc --noEmit
npm run test
npm run package
```

`npm run package` builds the frontend and creates `AutoSaleVPS.zip` with an `AutoSaleVPS/` directory. The PHP plugin header, runtime version constant, and `package.json` must agree. Use `npm run dev -- --host` to start Vite; WordPress still provides the backend. Run `composer lint` to check PHP coding standards; see the [test notes](docs/test-notes.md) for known findings.

More documentation: [security](docs/安全建议.md) · [Argon/PJAX compatibility](docs/argon_support.md) · [custom CSS example](docs/额外CSS示例.css) · [localization plan](docs/多语言支持.md) · [release log](log.md).

## License

[Apache License 2.0](LICENSE).
