# AutoSaleVPS
AutoSaleVPS 是一个基于WordPress和大语言模型的商品（比如VPS等具有固定链接模式[affiliate ID和product ID]）推广插件，负责：

- 读取 `config/config.toml` 中的多家商家与 PID，自动生成推广卡片；
- 通过 `valid_format` 校验商品是否还能下单，并在售罄时高亮提醒；
- 读取 `model.toml` 中的 LLM 配置，生成推广话术、解析销售页 Meta；
- 在前台展示访客友好的卡片，在后台提供配置编辑、日志、巡检工具。

## 必要环境 & 仓库结构

- Node.js 18+、npm（用于构建前端资产）
- PHP 8.1+、Composer（用于 WordPress 插件依赖）
- WordPress 6.x 站点

项目遵循以下结构：

```
autosalevps.php      # 插件入口（WordPress 根目录下的主文件）
config/              # 默认的 config.toml 与 model.toml
src/                 # TypeScript / PHP 源码
assets/              # Vite 构建出的 main.js / main.css
scripts/package.mjs  # 打包 ZIP 的脚本
AutoSaleVPS.zip      # 运行 npm run package 后生成的安装包
```

## 使用教程

> 具体见我的博客文章： [Docker系列 WordPress系列 AutoSaleVPS插件](https://blognas.hwb0307.com/linux/docker/6601)

1. **获取安装包**
   - 下载仓库或直接使用我们提供的 `AutoSaleVPS.zip`。若你是开发者，可以运行 `npm run package` 生成最新 ZIP。

2. **在 WordPress 后台安装插件**
   - 登录 WP 后台 → 插件 → 安装插件 → 上传插件 → 选择 `AutoSaleVPS.zip` → 安装并启用。

3. **创建展示页面**
   - 新建一个页面或文章，内容里输入短代码：
     ```
     [AutoSaleVPS]
     ```
   - 发布后，访客就能看到推广卡片；管理员会额外看到配置按钮与日志面板。

4. **配置 API Key（必需步骤）**
   - 在含短代码的页面顶部点击「添加KEY」。
   - 输入 `sk-` 开头的密钥；眼睛图标仅在鼠标悬停时短暂显示内容，鼠标移开立刻隐藏，防止泄露。

5. **导入/修改 TOML 配置**
   - 点击「编辑 VPS 配置」打开 `config.toml`，在弹窗中粘贴你的推广信息（示例见下节）。
   - 点击「编辑 模型配置」调整 LLM 服务、模型、提示词。
   - 每次点击「保存配置」都会立即触发一次全量校验，并在日志面板记录结果。

6. **运行巡检**
   - 点「检查可用性」会即时测试网络、LLM、以及所有 `valid_format` 链接。
   - 平时你可以依赖插件的自动巡检：遵循 `valid_interval_time` 周期，并在每个 VPS 之间等待 `valid_vps_time`（随机值）以防访问被判为爬虫。

7. **观察访客界面**
   - 访客只会看到卡片、推广按钮和 LLM 生成的文案。
   - 如果某个 VPS 售罄，卡片会变成离线状态，但信息仍可查看。

8. **日志与时区**
   - 右上角可切换时区（默认北京时间），影响日志时间与巡检日志。
   - 日志窗会记录配置保存、可用性检测、LLM 调用等关键事件，方便排查。

## 示例配置

### `config.toml`

```toml
[aff]
[aff.rn]
code = '4886'

[url]
[url.rn]
sale_format = 'https://my.racknerd.com/aff.php?aff={aff}&pid={pid}'
valid_format = 'https://my.racknerd.com/cart.php?a=add&pid={pid}'
valid_interval_time = '172800' # 每 48 小时校验一次
valid_vps_time = '5-10'        # 每个 VPS 之间随机等待 5-10 秒

[vps]
[vps.rn.923]
pid = '923'
human_comment = '比较基础的一款VPS'

[vps.rn.924]
pid = '924'
human_comment = '2025年黑色星期五活动。CPU弱点，但内存还行。预算有限的人推荐选。'

[vps.rn.925]
pid = '925'
human_comment = '2025年黑色星期五活动。作为主流机型推荐，内存、CPU、磁盘空间的均衡很好。'
```

关键字段说明：

- `aff.<厂商>.code`：联盟 ID。
- `url.<厂商>.sale_format`：前台展示的推广链接模板，支持 `{aff}`、`{pid}` 占位符。
- `url.<厂商>.valid_format`：**仅用于巡检** 的链接，避免浪费真实推广点击。
- `url.<厂商>.valid_interval_time`：多久跑一次全量巡检（秒）。
- `url.<厂商>.valid_vps_time`：单次巡检中不同 VPS 的请求间隔范围（`"5-10"` 表示随机 5~10 秒）。
- `vps.<厂商>.<pid>`：具体商品配置；`human_comment` 会与 LLM 输出合并为推广语。

### `model.toml`

```toml
[model_providers]
[model_providers.omg]
base_url = 'https://api.ohmygpt.com/v1'
model = "gpt-4.1-mini"
prompt_valid = '基于输入判断VPS是否已经卖完或下架；如果已经卖完或下架，请返回FALSE；否则，请返回TRUE'
prompt_vps_info = '基于输入给出一段推销VPS的广告，字数限定在30字左右。推广要求贴合VPS的实际，不能无脑推，要像一个优秀的VPS推广商那样推广产品。不要添加推广链接，我已经在其它地方展示推广链接了。'
prompt_meta_layout = '请将输入JSON整理成固定的8行中文，依次为：厂商、CPU、内存、存储、带宽、网络、价格、地理位置。每一行必须使用“字段：内容”格式，字段名需与上述完全一致，如信息缺失则填“-”，不要输出其他文字。'
```

当你点击「保存配置」或执行巡检时，插件会按照这里的 `base_url` 和 `model` 调用 LLM 来：

- 解析销售页面 HTML，生成规范的元信息；
- 结合 `human_comment` 与元信息产出 30 字左右的中文推广语；
- 依据 `prompt_valid` 的指令判断该 VPS 是否仍有库存。

## 常见操作 & 提示

- **不要把 `sale_format` 用于可用性验证**：插件只会用 `valid_format`，保证真实推广链接不会被巡检误点。
- **售罄状态**：如果 LLM/规则识别到包含 “Out of Stock”“Oops…” 等字样，或返回 `FALSE`，卡片会标红并在日志中记录。
- **多商家支持**：`rn` 只是示例命名；可以用 `vps.hetzner.1234`、`vps.hwvps.88` 等形式扩展。
- **日志截图**：如需手工测试 WordPress 后台行为，请把步骤记录到 `docs/test-notes.md`，方便团队同步。

## 本地开发、构建与打包

```bash
# 1. 安装依赖
npm install
composer install

# 2. 开发模式（提供热更新 UI）
npm run dev -- --host

# 3. 质量保障
npm run lint         # ESLint + Stylelint
npm run test         # Vitest
composer lint        # PHP_CodeSniffer 等

# 4. 生产构建 & 打包
npm run build        # 生成 assets/css & assets/js
npm run package      # 产出 AutoSaleVPS.zip + 带版本号的 ZIP
```

`npm run package` 会：

1. 自动运行 `npm run build`;
2. 将 `autosalevps.php`、`assets/`、`config/` 等文件打入 ZIP；
3. 生成形如 `AutoSaleVPS-v202511291706.zip` 的版本化文件，并复制一份 `AutoSaleVPS.zip` 供上传。
