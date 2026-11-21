# Test Notes

- 手工校验：尚未对 WordPress 后台的短码输出执行手动截图，待接入真实站点后补充。
- 自动化：`npm run test` 覆盖了配置解析逻辑；前端 UI 依赖 Vitest + Vite 资产。

## 2026-11-21 商品采集修复（开发阶段）

新增传输、库存状态和展示回归测试，覆盖普通 HTTP、403 后浏览器回退、HTTP 200 挑战页、429 冷却、跨商品域名冷却、页面缓存、规格故障回退、导航误提取、未知状态、历史库存保留及失败后的 Cron 重试资格。全部通过；`npm run build`、`npm run package`、变更 PHP 文件语法检查及变更 TypeScript 文件的 ESLint 检查通过。

线上浏览器实际取得正常的 RackNerd WHMCS 配置页。核对页面时发现 `A description (tagline) is not available for this product.` 被原先宽泛的 `not available` 规则误判为缺货；已用真实文案建立回归用例，改为只匹配商品明确不可售或缺货的证据。

全量质量命令仍有已有问题：JavaScript lint 报告 `src/main.ts` 类型声明中的参数未使用，Stylelint 报告原有选择器顺序及格式问题；Composer 安装完成，但仓库未依赖 WordPress PHPCS 标准，`composer --no-plugins lint` 无法找到该标准。额外 TypeScript 检查还发现原有 Vite 模块解析配置和定时任务面板调用不存在的 `rest.fetch` 方法。上述全量检查没有宣称通过。

本次未进行登录后台的浏览器截图验收；线上验证通过 WordPress 容器中的实际服务调用及公开 REST 缓存接口完成。

线上逐商品复核：PID 968、969、970、971 均通过浏览器取得正常页面，库存检查返回 `available=true`、`check_status=ok`，4 个商品均有规格与展示文本。已清理 8 条带明确 Cloudflare／403 原因的历史误判；替换前的代码及状态备份保留在部署服务器的私有备份目录。

## 2026-11-21 采集设置弹窗（开发阶段）

“查看VPS状态”右侧新增管理员可见的“采集设置”。弹窗可读取、保存服务地址，支持恢复默认，保存值优先于旧 mu-plugin 常量。后端拒绝非 HTTP/HTTPS、内嵌凭据、查询参数、片段及非法端口，并规范化尾部 `/v1`。

自动化：11 个 Vitest 用例及 3 组 PHP 回归脚本通过，覆盖按钮位置与访客不可见、加载/保存/错误提示、恢复默认、配置优先级、地址验证及实际传输使用保存地址。变更 TypeScript 的 ESLint、PHP 语法检查和构建/打包通过。全量 lint 仍有上节记录的已有问题；本轮未修改 `Prompts.md` 中用户已有改动。

Chrome 本地预览检查通过保存、重新打开回显和恢复默认；1360px 桌面与 390px 手机视口均可使用，已修复窄屏下的弹窗溢出。这一轮预览使用了测试数据，未登录生产后台。

线上接口验证：访客读取返回 401，管理员读取/保存返回 200，非法地址返回 400；保存自定义容器地址后实际连通服务，随后恢复默认值。通过新设置再次采集 PID 968，返回 `available=true`、`check_status=ok`、`source=browser`。设置变更会隔离旧服务失败冷却，但不会绕过厂商 429 限流。

## v1.0.0 发布检查

发布检查覆盖 ESLint、Stylelint、TypeScript、Vitest、三组 PHP 回归、PHP 语法及标准 WordPress ZIP 的目录、文件和版本。新增回归覆盖 Cron 暂停后不会自动重启、管理员操作按原会话发送，以及商品 URL 模板校验。

Composer 已安装 WordPress PHPCS 标准；`composer lint` 能执行，但全量检查仍有 WordPress 代码风格和 API 使用建议，例如历史 PHP 文件的文档注释与命名。它不替代 PHP 语法和功能回归。部署复核使用真实站点的短码页面、公开快照和浏览器服务健康检查；后台界面的交互由自动化测试覆盖。
