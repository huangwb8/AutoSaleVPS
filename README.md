# AutoSaleVPS
基于 WordPress 的 VPS 推广插件，负责管理推广配置、验证上架状态并调用 LLM 生成营销文案。前端通过 `[AutoSaleVPS]` 短代码挂载，在一个界面里完成配置编辑、巡检和日志查看。

## 使用方法
1. **准备依赖**：在仓库根目录运行 `npm install` 安装前端依赖，并执行 `composer install` 拉取 PHP 工具。
2. **配置默认数据**：根据自己的推广计划修改 `config/config.toml`（联盟 ID、商品 PID、校验延迟/间隔）与 `config/model.toml`（模型地址、提示词）。这些文件会被打包进插件，也可以在 WordPress 后台通过内置编辑器再次修改。
3. **构建并打包**：执行 `npm run build` 生成 `assets/js/main.js` 与 `assets/css/main.css`，随后运行 `npm run package` 得到形如 `AutoSaleVPS-v202511291333.zip` 的版本化安装包（同时会复制一份为 `AutoSaleVPS.zip` 方便上传），且插件内部版本号也会自动写成 `vyyyymmddhhmm` 以便 WordPress 后台展示。
4. **安装插件**：在 WordPress 后台上传 `AutoSaleVPS.zip` 并启用插件。
5. **嵌入页面**：在任意文章或页面插入短代码 `[AutoSaleVPS]`。前台访客可看到已验证的 VPS 列表，管理员额外拥有配置和诊断面板。

## WordPress 内的操作
- **首次启动**：管理员打开含有短代码的页面即可看到工具栏。点击“编辑 VPS 配置”或“编辑模型配置”可以直接在弹窗里修改 TOML，保存后立即触发一次可用性扫描。
- **设置 API KEY**：点击“添加/更新 KEY”输入 LLM 服务密钥（须以 `sk-` 开头）。输入框旁的眼睛按钮为悬停预览，离开即隐藏，可避免泄露。
- **时区切换**：右上角下拉框可选择 WordPress 支持的任意时区，影响日志时间戳和巡检计划。
- **可用性验证**：使用“检查可用性”按钮触发诊断，或在每张卡片里点击“验证”手动刷新单个 VPS。系统会遵循 `valid_vps_time` 的延迟和 `valid_interval_time` 的巡检周期，避免过度请求。
- **推广卡片**：每张卡片包含推广链接、LLM 生成的话术、解析自销售页的 Meta 信息，以及手写备注。当某款 VPS 下架时卡片会标红并显示售罄原因。

## 进阶配置
- `aff.<vendor>.code`：设置不同供应商的联盟 ID。
- `url.<vendor>.sale_format` / `valid_format`：推广链接与存活校验链接模板，支持 `{aff}` 和 `{pid}` 占位符。
- `url.<vendor>.valid_interval_time`：巡检周期（秒），若未填写默认 24 小时。
- `url.<vendor>.valid_vps_time`：可用性请求之间的随机延迟区间（如 `5-10` 秒）。
- `vps.<vendor>.<pid>`：列出具体商品及备注文案。
- `model_providers`：定义 LLM Base URL、模型名以及 `prompt_valid`、`prompt_vps_info` 提示词，在后台生成状态检测与推广话术时会调用这些内容。

## 本地开发与调试
- `npm run dev -- --host`：以 Vite 热更新方式调试前端 UI。
- `npm run test`：运行 Vitest 覆盖核心逻辑与 UI 状态。
- `npm run lint`、`composer lint`：保持 JS/CSS 与 PHP 代码风格一致。
- `npm run package`：输出用于 WordPress 安装的 ZIP，包含构建产物与配置文件。

通过以上流程即可在 WordPress 站点中快速部署 AutoSaleVPS，实时掌握 VPS 商品的可用性并输出定制化推广内容。
