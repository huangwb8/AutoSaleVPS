import './style.css';
import { ASVApp } from './ui/ASVApp';

declare global {
  interface Window {
    ASV_BOOTSTRAP: {
      restUrl: string;
      nonce: string;
      isAdmin: boolean;
      timezone: string;
      version: string;
      hasKey: boolean;
      options: string[];
    };
  }
}

const bootstrap = window.ASV_BOOTSTRAP;
const root = document.getElementById('asv-root');

if (root && bootstrap) {
  const app = new ASVApp(root, bootstrap);
  app.init();
} else {
  console.warn('AutoSaleVPS: 缺少挂载节点或初始化数据');
}
