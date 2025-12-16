# CLAUDE.md

本文件为 Claude Code (claude.ai/code) 在处理本仓库代码时提供指导。

## 项目概述

AutoSaleVPS 是一个使用 LLM 自动推广和验证 VPS 产品的 WordPress 插件。插件解析 TOML 配置文件，从供应商 URL 获取 VPS 数据，使用 AI 生成推广内容并检查可用性，同时向访问者展示卡片并提供管理界面。

## 开发命令

### 构建和打包
```bash
npm install          # 安装 Node 依赖
composer install     # 安装 PHP 依赖

npm run build        # 构建前端资源（TypeScript → JavaScript/CSS）

# ZIP文件打包流程
npm run package      # 构建并创建带时间戳版本的分发 ZIP 包
```

#### ZIP测试文件生成详细流程

执行 `npm run package` 会触发以下自动化流程：

1. **自动版本生成** (`scripts/package.mjs`)
   - 生成时间戳版本号：`vYYYYMMDDHHmm`（如：`v202512161332`）
   - 生成短格式版本：`vyymmddhhmm`（如：`v2512161332`）

2. **版本信息更新**
   - 写入 `version.md` 文件，记录版本和生成时间
   - 临时修改 `autosalevps.php` 中的版本号（两处）：
     - 插件头部注释的版本
     - `VERSION` 常量值

3. **ZIP文件创建**
   - 使用系统 `zip` 命令打包
   - 输出固定文件名：`AutoSaleVPS.zip`
   - **排除内容**：
     - `node_modules/` - Node依赖
     - `.git/` - Git历史
     - `tests/` - 测试文件
     - `docs/` - 文档
     - `scripts/` - 构建脚本
     - `.DS_Store` - macOS系统文件

4. **源码恢复**
   - ZIP创建后，恢复 `autosalevps.php` 原始内容
   - 确保源代码不受影响，可重复执行

**生成的文件**：
- `AutoSaleVPS.zip` - 可分发的插件包
- `version.md` - 版本记录文件

### 开发
```bash
npm run dev -- --host  # 启动开发服务器（支持热重载）
npm run lint          # 运行 ESLint + Stylelint
npm run test          # 运行 Vitest 测试 + PHP 可用性服务测试
npm run test:watch    # 以监听模式运行 Vitest
composer lint         # 运行 Composer 检查
```

### 测试单个组件
```bash
# 运行单个 TypeScript 测试文件
npx vitest run tests/ui/metaUtils.spec.ts

# 运行 PHP 测试
php tests/php/availability_service.php
```

## 架构概览

### WordPress 插件架构
- **入口文件**: `autosalevps.php` - 启动插件、注册钩子、初始化服务
- **配置仓库** (`class-asv-config-repository.php`): 使用 WordPress 选项和 TOML 文件的中央数据存储
- **REST 控制器** (`class-asv-rest-controller.php`): 前端通信的 API 端点
- **定时任务调度器** (`class-asv-cron-scheduler.php`): VPS 可用性检查的后台 WordPress 定时任务

### 前端架构（TypeScript）
- **ASVApp**: 主应用程序类，管理 UI 状态和 API 调用
- **ASVModal**: 配置编辑器的模态框组件
- **ASVLogPanel**: 带时区支持的系统日志查看器
- **Meta 工具**: 从供应商页面提取和格式化 VPS 元数据

### 核心服务
1. **可用性服务**: 通过 HTTP + LLM 分析检查 VPS 可用性
2. **推广服务**: 使用 LLM 生成推广文案
3. **元数据服务**: 提取和格式化 VPS 规格
4. **销售解析器**: 从供应商页面抓取 VPS 元数据

### 数据流程
1. 从 `wp-content/uploads/autosalevps/config.toml` 加载配置
2. 处理 VPS 定义 → 获取供应商 URL
3. LLM 分析页面 → 确定可用性 + 生成内容
4. 结果存储在 WordPress 选项中（`autosalevps_statuses`、`autosalevps_vps_snapshot`）
5. 前端通过 REST API 端点展示

### 重要：基于定时任务的后台处理
插件使用 WordPress 定时任务（`ASV_Cron_Scheduler`）自动检查 VPS：
- 在 `autosalevps_check_vps_availability` 钩子上运行
- 遵循配置中每个供应商的 `valid_interval_time`
- 不再需要浏览器打开（从客户端 setInterval 迁移）
- 通过管理界面中的"定时任务管理"按钮管理

## 配置文件

### config.toml 结构
- `[aff.<vendor>]` - 联盟代码
- `[url.<vendor>]` - URL 模板和时间设置
  - `valid_interval_time`: 检查间隔秒数
  - `valid_vps_time`: VPS 检查之间的延迟范围（如 "5-10"）
- `[vps.<vendor>.<pid>]` - VPS 方案定义

### model.toml 结构
- `[model_providers]` - LLM 提供商配置
- 提示词用于：验证、推广生成、元数据格式化

## 关键实现细节

### TOML 配置管理
- `config/` 目录中的默认配置
- 运行时配置存储在 `wp-content/uploads/autosalevps/`
- 管理员可通过模态框编辑

### 使用的 WordPress 选项
- `autosalevps_api_key` - OpenAI API 密钥
- `autosalevps_statuses` - VPS 可用性缓存
- `autosalevps_vps_snapshot` - 前端完整 VPS 数据
- `autosalevps_timezone` - 管理员时区偏好

### 安全注意事项
- API 密钥仅在悬停时显示
- 所有管理操作都需要 `manage_options` 权限
- TOML 解析对格式错误的输入进行清理