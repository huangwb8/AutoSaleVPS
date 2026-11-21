// @vitest-environment happy-dom
import { describe, expect, it, vi } from 'vitest';
import { ASVApp } from '../../src/ui/ASVApp';

describe('Scheduled checks', () => {
  it('sends the nonce when pausing checks and refreshes the same dialog', async () => {
    let active = true;
    const requests: RequestInit[] = [];
    vi.stubGlobal('fetch', vi.fn().mockImplementation(async (url: string, options: RequestInit) => {
      let payload: unknown = {};
      if (url.endsWith('/vps/cached')) payload = { vps: [] };
      if (url.endsWith('/logs')) payload = { logs: [] };
      if (url.endsWith('/design')) payload = { extraCss: '' };
      if (url.endsWith('/cron')) {
        requests.push(options);
        if (options.method === 'POST') {
          active = false;
          payload = { success: true, message: '已暂停' };
        } else {
          payload = { active, next_run: null, message: active ? '运行中' : '未激活' };
        }
      }
      return new Response(JSON.stringify(payload), { status: 200 });
    }));
    const root = document.createElement('div');
    document.body.appendChild(root);
    try {
      new ASVApp(root, {
        restUrl: 'http://localhost/api', nonce: 'test-nonce', isAdmin: true, timezone: 'Asia/Shanghai',
        version: 'test', hasKey: false, options: ['Asia/Shanghai'], extraCss: ''
      }).init();
      root.querySelector<HTMLButtonElement>('[data-action="manage-cron"]')!.click();
      await vi.waitFor(() => expect(root.querySelector('.asv-cron-status')?.textContent).toContain('运行中'));
      const stop = [...root.querySelectorAll('.asv-cron-actions button')].find((button) => button.textContent === '停止定时任务');
      (stop as HTMLButtonElement).click();
      await vi.waitFor(() => expect(root.querySelector('.asv-cron-actions')?.textContent).toContain('启动定时任务'));
      expect(root.querySelectorAll('.asv-cron-status')).toHaveLength(1);
      expect(requests.map(({ method }) => method ?? 'GET')).toEqual(['GET', 'POST', 'GET']);
      expect(new Headers(requests[1].headers).get('X-WP-Nonce')).toBe('test-nonce');
      expect(JSON.parse(String(requests[1].body))).toEqual({ action: 'stop' });
    } finally {
      root.remove();
      vi.unstubAllGlobals();
    }
  });
});
