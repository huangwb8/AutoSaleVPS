# Argon 主题 PJAX 适配

Argon 内置了自定义的 PJAX 实现（`assets/vendor/jquery-pjax-plus`），并要求第三方脚本在
页面无刷新切换时挂钩 `window.pjaxLoaded`。如果没有做这个处理，带有 `[AutoSaleVPS]`
简码的容器会一直停留在“正在载入 AutoSaleVPS...”的占位状态。

AutoSaleVPS 插件已经在前端输出了所有必须的信息（`.asv-root` 容器和 `ASV_BOOTSTRAP`
初始化数据），因此我们可以保持插件不变，只在 Argon 主题侧补上一段 PJAX glue 代码。

## 操作步骤

1. **复制辅助脚本**：获取仓库中的 `pjax_support.js`，放到主题目录（例如
   `wp-content/themes/argon/assets/js/autosalevps-pjax.js`）。这段脚本会监听 `.asv-root`
   节点，并在每次 PJAX 导航后伪造一次 `pjax:complete` 事件，促使官方的
   AutoSaleVPS 前端包重新挂载。

2. **在主题里加载脚本**：在子主题的 `functions.php`（或 Argon 的“自定义脚本”字段）中
   添加以下代码，让脚本在所有前端页面可用：

   ```php
   add_action( 'wp_enqueue_scripts', function () {
       $path = get_stylesheet_directory_uri() . '/assets/js/autosalevps-pjax.js';
       wp_enqueue_script( 'autosalevps-argon-pjax', $path, array(), '1.0.0', true );
   } );
   ```

   如果你偏好在后台配置，可以直接把 `pjax_support.js` 的源码粘贴到 **Argon 控制面板 → 自定义脚本**。
   这个位置位于 PJAX 生命周期钩子之后，正好符合主题在 `settings.php`
   （1205–1213 行）中对第三方脚本的建议。

3. **验证 PJAX 行为**：利用 Argon 的 PJAX 导航，从不含简码的页面跳转到包含
   `[AutoSaleVPS]` 的页面，确认占位提示能在一次 PJAX tick 内消失。如果 PJAX
   调用报错，你也会在浏览器控制台看到辅助脚本输出的单条错误信息。

通过这种方式，我们把所有 PJAX 逻辑都放在 Argon 主题侧，不需要改动 AutoSaleVPS
插件本身，并完全遵循主题对 `window.pjaxLoaded` 的扩展规范。
