import './style.css';
import { ASVApp } from './ui/ASVApp';

type JQueryLike = {
  (selector: Document | HTMLElement): {
    on: (events: string, handler: () => void) => void;
  };
};

declare global {
  interface Window {
    ASV_BOOTSTRAP?: {
      restUrl: string;
      nonce: string;
      isAdmin: boolean;
      timezone: string;
      version: string;
      hasKey: boolean;
      options: string[];
      extraCss: string;
    };
    pjaxLoaded?: () => void;
    __ASV_PJAX_BRIDGE__?: boolean;
    jQuery?: JQueryLike;
    $?: JQueryLike;
  }
}

const mountApps = () => {
  const bootstrap = window.ASV_BOOTSTRAP;

  if (!bootstrap) {
    return;
  }

  const roots = Array.from(document.querySelectorAll<HTMLElement>('.asv-root'));

  if (!roots.length) {
    return;
  }

  roots.forEach((root) => {
    if (root.dataset.asvMounted === 'true') {
      return;
    }

    const app = new ASVApp(root, bootstrap);
    app.init();
    root.dataset.asvMounted = 'true';
  });
};

const attachPjaxBridge = () => {
  if (window.__ASV_PJAX_BRIDGE__) {
    return;
  }

  window.__ASV_PJAX_BRIDGE__ = true;

  const previous = typeof window.pjaxLoaded === 'function' ? window.pjaxLoaded : undefined;

  window.pjaxLoaded = () => {
    if (previous) {
      try {
        previous.call(window);
      } catch (error) {
        console.error('AutoSaleVPS: 其他 pjaxLoaded 钩子执行失败', error);
      }
    }

    mountApps();
  };

  const jq = window.jQuery || window.$;

  if (typeof jq === 'function') {
    const jqDoc = jq(document);
    if (jqDoc && typeof jqDoc.on === 'function') {
      jqDoc.on('pjax:complete.autosalevps pjax:end.autosalevps', mountApps);
      return;
    }
  }

  document.addEventListener('pjax:complete', mountApps as EventListener);
};

const bootstrap = window.ASV_BOOTSTRAP;

if (bootstrap) {
  mountApps();
  attachPjaxBridge();
} else {
  console.warn('AutoSaleVPS: 缺少初始化数据');
}
