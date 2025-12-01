# AutoSaleVPS

AutoSaleVPS is a WordPress plugin backed by large language models. It targets products that expose predictable URL patterns (affiliate ID + product ID, such as VPS plans) and it can:

- Parse `config/config.toml` for every vendor + PID and render promotion cards automatically.
- Use each vendor's `valid_format` URL to probe availability and highlight sold-out plans.
- Load LLM settings from `config/model.toml` to generate copy and extract sales-page metadata.
- Expose visitor-facing cards while giving admins modals for config editing, logs, and health checks.

## Required Environment & Repository Layout

- Node.js 18+ and npm (build UI assets with Vite)
- PHP 8.1+ and Composer (WordPress dependency management)
- WordPress 6.x site with plugin upload access

Project layout:

```
autosalevps.php      # WordPress entry that bootstraps the plugin
config/              # Default config.toml & model.toml shipped in the ZIP
src/                 # TypeScript + PHP source
assets/              # Vite output (main.js / main.css)
scripts/package.mjs  # ZIP packaging script
AutoSaleVPS.zip      # npm run package artifact ready to upload
```

## Usage Guide

> Full walkthrough (Chinese): [Docker Series / WordPress Series / AutoSaleVPS Plugin](https://blognas.hwb0307.com/linux/docker/6601)

1. **Get the archive**
   - Clone this repo or grab the packaged `AutoSaleVPS.zip`. Developers can run `npm run package` to rebuild it.
2. **Install the plugin in WordPress**
   - Dashboard -> Plugins -> Add New -> Upload Plugin -> choose `AutoSaleVPS.zip`, then install & activate.
3. **Create a display page**
   - Insert the shortcode below in a page/post and publish it:
     ```
     [AutoSaleVPS]
     ```
   - Visitors see promotion cards; admins also see config buttons and the log panel.
4. **Add your API Key (required)**
   - On the shortcode page, click "Add KEY", paste your `sk-` key. The eye icon only reveals secrets while hovered.
5. **Import or edit TOML config**
   - "Edit VPS Config" opens the contents of `config.toml`. Paste your vendors/PIDs there.
   - "Edit Model Config" controls the LLM provider, model, and prompts.
   - Saving triggers a full validation pass and logs the outcome.
6. **Run inspections**
   - "Check Availability" performs immediate probes (network, LLM, every `valid_format`).
   - Automatic inspections respect `valid_interval_time` and wait `valid_vps_time` between each VPS to avoid hammering vendors.
7. **Visitor experience**
   - Only cards, CTA buttons, and LLM-generated blurbs appear to visitors. Sold-out plans show an offline style but remain readable.
8. **Logs & timezone**
   - Switch the timezone (default: Asia/Shanghai) from the header to control log timestamps.
   - The log window captures config saves, inspections, and LLM calls for easier debugging.

## Sample Configuration

### `config.toml`

```toml
[aff]
[aff.rn]
code = '4886'

[url]
[url.rn]
sale_format = 'https://my.racknerd.com/aff.php?aff={aff}&pid={pid}'
valid_format = 'https://my.racknerd.com/cart.php?a=add&pid={pid}'
valid_interval_time = '172800'
valid_vps_time = '5-10'

[vps]
[vps.rn.923]
pid = '923'
human_comment = 'Entry-level VPS'

[vps.rn.924]
pid = '924'
human_comment = 'Black Friday 2025 pick. Weak CPU, decent RAM-best for tight budgets.'

[vps.rn.925]
pid = '925'
human_comment = 'Black Friday 2025 flagship. Balanced memory/CPU/disk for general workloads.'
```

Key fields:

- `aff.<vendor>.code`: affiliate ID.
- `url.<vendor>.sale_format`: public link template; supports `{aff}` and `{pid}` placeholders.
- `url.<vendor>.valid_format`: inspection-only URL to avoid spending real affiliate clicks.
- `url.<vendor>.valid_interval_time`: seconds between full inspection cycles.
- `url.<vendor>.valid_vps_time`: random wait range ("5-10" -> 5 to 10 seconds) between plan checks.
- `vps.<vendor>.<pid>`: plan definition; `human_comment` is merged with LLM output for copy.

### `model.toml`

```toml
[model_providers]
[model_providers.omg]
base_url = 'https://api.ohmygpt.com/v1'
model = "gpt-4.1-mini"
prompt_valid = '''
You are AutoSaleVPS's availability auditor. You will receive a JSON payload that contains:
- vendor / pid / valid_url
- HTTP status, selected response headers, body summary, truncated HTML snippet
- initial_state / initial_reason plus signals (keyword hit list)

Use every field-especially signals and body semantics-to judge whether the VPS can still be ordered. If evidence is insufficient, return unknown.
Output rules:
- Respond with a single JSON object, no extra prose.
- Fields: status (online/offline/unknown), reason (<=120 chars summarizing evidence), confidence (optional 0~1). You may add an evidence array listing critical clues.
- When terms such as "sold out" or "out of stock" appear, mark offline immediately; only label online after confirming purchase buttons or inventory hints.

Strictly follow JSON formatting and avoid natural-language commentary outside the object.
'''
prompt_vps_info = 'Generate ~40 characters of persuasive VPS copy grounded in the data. Evaluate suitability for blogging, AI, RSS, and Docker scenarios. Do not include promotion links.'
prompt_meta_layout = 'Format the input JSON into exactly 8 Chinese lines in this order: 厂商, CPU, 内存, 存储, 带宽, 网络, 价格, 地理位置. Each line must read "字段：内容". Use "-" for missing data and output nothing else.'
```

During every save or inspection, the plugin calls the configured LLM to:

- Parse the vendor HTML and normalize metadata.
- Combine metadata with `human_comment` into a concise promotion blurb.
- Decide whether a plan is online/offline/unknown based on `prompt_valid` rules.

## Tips & Best Practices

- Never reuse `sale_format` for validation traffic. Only `valid_format` URLs are probed.
- If "Sold Out"/"Out of Stock" is detected, the card turns red and the incident is logged.
- Vendor namespaces are arbitrary-use `vps.hetzner.1234`, `vps.hwvps.88`, etc., to scale.
- Manual WordPress QA steps + screenshots should live in `docs/test-notes.md` until automated tests ship.
- Secrets stay secret: hover-only previews hide immediately when the pointer leaves.

## Local Development, Build & Packaging

```bash
npm install
composer install

npm run dev -- --host

npm run lint
npm run test
composer lint

npm run build
npm run package
```

`npm run package` performs:

1. `npm run build`
2. Bundle `autosalevps.php`, `assets/`, `config/`, etc., into a timestamped ZIP like `AutoSaleVPS-v202511291706.zip`
3. Copy the archive to `AutoSaleVPS.zip` for quick uploads
