# Argon 主题下的 PJAX 兼容方案

当 Argon 主题打开 PJAX 时，直接点击站内链接只会更新部分 DOM，而不会重新执行 WordPress 插入的初始化脚本，像 AutoSaleVPS 这类需要全量初始化的插件就会卡在“正在载入”状态。可以通过在主题中注入一个脚本，强制指定链接改为整页刷新。

## 操作步骤

1. 登录 WordPress 后台，进入 **外观 → Argon 主题 → 主题设置 → 自定义脚本（或页脚代码注入区）**。
2. 粘贴下面的脚本并保存。脚本会拦截所有 PJAX 请求，只要目标链接匹配 `forceReloadRules` 中的任意规则，就直接 `window.location.href` 强制刷新（默认配置为整站）。

```html
<script>
  (function ($) {
    if (!$ || !$.pjax) return;

    // 规则写成完整 URL，可选用 * 作为前缀匹配结尾。
    // 例如：
    //   'https://blognas.hwb0307.com/ad'    -> 仅刷新 /ad
    //   'https://blognas.hwb0307.com/vps/*' -> 刷新 /vps 及其子路径
    //   'https://blognas.hwb0307.com/*'     -> 整站
    var forceReloadRules = [
      'https://blognas.hwb0307.com/*'
    ];

    function matchRule(url) {
      if (!url) return false;
      try {
        var target = new URL(url, window.location.origin);
        return forceReloadRules.some(function (rule) {
          if (!rule) return false;
          var hasWildcard = rule.endsWith('*');
          var normalized = hasWildcard ? rule.slice(0, -1) : rule;
          if (!normalized) return false;
          if (!hasWildcard) {
            return target.href.replace(/\/+$/, '') === normalized.replace(/\/+$/, '');
          }
          return target.href.indexOf(normalized) === 0;
        });
      } catch (err) {
        return false;
      }
    }

    function forceReload(url) {
      window.location.href = url || window.location.href;
    }

    $(document).on('pjax:send.force-asv', function (event, xhr, options) {
      var nextUrl =
        (options && (options.url || options.requestUrl || options.href)) ||
        (xhr && xhr.responseURL) ||
        '';
      if (!matchRule(nextUrl)) return;

      if (xhr && typeof xhr.abort === 'function') xhr.abort();
      event.preventDefault();
      forceReload(nextUrl);
    });

    $(document).on('pjax:popstate.force-asv', function () {
      if (matchRule(window.location.href)) {
        forceReload();
      }
    });
  })(window.jQuery);
</script>
```

3. 如需恢复部分页面的 PJAX 行为，只要保留 `forceReloadRules` 中需要强刷的路径即可。例如仅 `/ad` 强制整页刷新：`['https://blognas.hwb0307.com/ad']`。

保存后，无论从哪个页面跳转到 `forceReloadRules` 命中的 URL，都会整页加载，AutoSaleVPS 可以正常获取 WordPress 生成的 `ASV_BOOTSTRAP` 数据，不再卡在加载状态。

