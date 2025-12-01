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

let missingBootstrapWarned = false;
let bootstrapRetryTimer: number | undefined;
let mutationObserver: MutationObserver | undefined;

const mountApps = (): boolean => {
  const bootstrap = window.ASV_BOOTSTRAP;

  if (!bootstrap) {
    if (!missingBootstrapWarned) {
      console.warn('AutoSaleVPS: 缺少初始化数据');
      missingBootstrapWarned = true;
    }
    return false;
  }

  missingBootstrapWarned = false;

  const roots = Array.from(document.querySelectorAll<HTMLElement>('.asv-root'));

  if (!roots.length) {
    return false;
  }

  roots.forEach((root) => {
    if (root.dataset.asvMounted === 'true') {
      return;
    }

    const app = new ASVApp(root, bootstrap);
    app.init();
    root.dataset.asvMounted = 'true';
  });

  return true;
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

const attachDomReadyHooks = () => {
  if (document.readyState === 'loading') {
    document.addEventListener(
      'DOMContentLoaded',
      () => {
        mountApps();
      },
      { once: true }
    );
  }
  window.addEventListener('load', () => {
    mountApps();
  });
};

const startMutationObserver = () => {
  if (mutationObserver || typeof MutationObserver === 'undefined') {
    return;
  }

  const begin = () => {
    if (!document.body) {
      return;
    }

    mutationObserver = new MutationObserver((mutations) => {
      for (const mutation of mutations) {
        if (!mutation.addedNodes.length) {
          continue;
        }

        for (const node of Array.from(mutation.addedNodes)) {
          if (!(node instanceof HTMLElement)) {
            continue;
          }

          if (node.classList.contains('asv-root') || node.querySelector('.asv-root')) {
            mountApps();
            return;
          }
        }
      }
    });

    mutationObserver.observe(document.body, { childList: true, subtree: true });
  };

  if (document.body) {
    begin();
  } else {
    document.addEventListener(
      'DOMContentLoaded',
      () => {
        begin();
      },
      { once: true }
    );
  }
};

const ensureBootstrapLater = () => {
  if (bootstrapRetryTimer || typeof window === 'undefined') {
    return;
  }

  bootstrapRetryTimer = window.setInterval(() => {
    if (window.ASV_BOOTSTRAP) {
      if (bootstrapRetryTimer) {
        window.clearInterval(bootstrapRetryTimer);
        bootstrapRetryTimer = undefined;
      }
      mountApps();
    }
  }, 250);
};

attachDomReadyHooks();
startMutationObserver();
attachPjaxBridge();

if (!mountApps()) {
  ensureBootstrapLater();
}
