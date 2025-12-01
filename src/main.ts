import './style.css';
import { ASVApp } from './ui/ASVApp';

type ASVBootstrapPayload = {
  restUrl: string;
  nonce: string;
  isAdmin: boolean;
  timezone: string;
  version: string;
  hasKey: boolean;
  options: string[];
  extraCss: string;
};

declare global {
  interface Window {
    ASV_BOOTSTRAP?: ASVBootstrapPayload;
    AutoSaleVPSMount?: (target?: HTMLElement | null) => boolean;
  }
}

let missingBootstrapWarned = false;
let observer: MutationObserver | null = null;

const isMountReady = () => {
  if (window.ASV_BOOTSTRAP) {
    return true;
  }

  if (!missingBootstrapWarned) {
    console.warn('AutoSaleVPS: 缺少初始化数据');
    missingBootstrapWarned = true;
  }

  return false;
};

const findRoot = (node?: HTMLElement | null) => {
  if (node) {
    if (node.id === 'asv-root') {
      return node;
    }

    return node.querySelector<HTMLElement>('#asv-root');
  }

  return document.getElementById('asv-root');
};

const mountApp = (target?: HTMLElement | null) => {
  if (!isMountReady()) {
    return false;
  }

  const root = findRoot(target);

  if (!root || root.dataset.asvMounted === 'true') {
    return Boolean(root);
  }

  const app = new ASVApp(root, window.ASV_BOOTSTRAP!);
  app.init();
  root.dataset.asvMounted = 'true';
  return true;
};

window.AutoSaleVPSMount = mountApp;

const observeRoot = () => {
  if (observer || typeof MutationObserver === 'undefined') {
    return;
  }

  observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (!(node instanceof HTMLElement)) {
          return;
        }

        const candidate = findRoot(node);

        if (candidate) {
          mountApp(candidate);
        }
      });
    });
  });

  const host = document.body || document.documentElement;

  if (host) {
    observer.observe(host, { childList: true, subtree: true });
  }
};

mountApp();
observeRoot();
