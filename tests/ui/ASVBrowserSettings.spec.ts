// @vitest-environment happy-dom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ASVBrowserSettings } from '../../src/ui/ASVBrowserSettings';
import { ASVApp } from '../../src/ui/ASVApp';

const defaults = { base_url: 'http://asv-browser:8191', default_base_url: 'http://asv-browser:8191' };

describe('Browser service settings modal', () => {
  beforeEach(() => { document.body.innerHTML = ''; });

  it('places the settings button immediately after VPS status for administrators only', async () => {
    vi.stubGlobal('fetch', vi.fn().mockImplementation(async (url: string) => {
      const payload = url.endsWith('/vps/cached') ? { vps: [] } : url.endsWith('/logs') ? { logs: [] } : url.endsWith('/design') ? { extraCss: '' } : { content: '' };
      return new Response(JSON.stringify(payload), { status: 200 });
    }));
    try {
      const bootstrap = { restUrl: 'http://localhost/api', nonce: '', isAdmin: true, timezone: 'Asia/Shanghai', version: 'test', hasKey: false, options: ['Asia/Shanghai'], extraCss: '' };
      const root = document.createElement('div');
      document.body.appendChild(root);
      new ASVApp(root, bootstrap).init();
      expect(root.querySelector('[data-action="check-vps"]')!.nextElementSibling?.getAttribute('data-action')).toBe('browser-settings');
      const visitorRoot = document.createElement('div');
      document.body.appendChild(visitorRoot);
      new ASVApp(visitorRoot, { ...bootstrap, isAdmin: false }).init();
      expect(visitorRoot.querySelector('[data-action="browser-settings"]')).toBeNull();
      await vi.waitFor(() => expect(root.querySelector('.asv-vps-list')!.textContent).toContain('暂无 VPS'));
    } finally {
      vi.unstubAllGlobals();
    }
  });

  it('loads the saved URL, saves changes, and restores the default only on save', async () => {
    const rest = {
      fetchBrowserSettings: vi.fn().mockResolvedValue({ ...defaults, base_url: 'http://custom-browser:8191' }),
      saveBrowserSettings: vi.fn().mockResolvedValue(defaults)
    };
    const saved = vi.fn();
    const settings = new ASVBrowserSettings(document.body, rest, saved);
    await settings.show();
    const input = document.querySelector('input')!;
    expect(input.value).toBe('http://custom-browser:8191');
    const reset = [...document.querySelectorAll('button')].find((button) => button.textContent === '恢复默认')!;
    reset.click();
    expect(input.value).toBe(defaults.base_url);
    expect(rest.saveBrowserSettings).not.toHaveBeenCalled();
    input.value = 'https://new-browser.example.net';
    document.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
    await vi.waitFor(() => expect(rest.saveBrowserSettings).toHaveBeenCalledWith('https://new-browser.example.net'));
    await vi.waitFor(() => expect(document.querySelector('.asv-modal')!.classList.contains('hidden')).toBe(true));
    expect(saved).toHaveBeenCalledOnce();
  });

  it('keeps user input and shows server validation errors when save fails', async () => {
    const rest = {
      fetchBrowserSettings: vi.fn().mockResolvedValue(defaults),
      saveBrowserSettings: vi.fn().mockRejectedValue(new Error('请输入 HTTP 或 HTTPS 地址'))
    };
    const settings = new ASVBrowserSettings(document.body, rest, vi.fn());
    await settings.show();
    document.querySelector('input')!.value = 'ftp://browser.example.net';
    document.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
    await vi.waitFor(() => expect(document.querySelector('[role="status"]')!.textContent).toContain('请输入 HTTP'));
    expect(document.querySelector('input')!.value).toBe('ftp://browser.example.net');
    expect(document.querySelector('.asv-modal')!.classList.contains('hidden')).toBe(false);
    expect(document.querySelector<HTMLButtonElement>('[type="submit"]')!.disabled).toBe(false);
  });

  it('does not let a failed settings load overwrite an existing configuration', async () => {
    const rest = { fetchBrowserSettings: vi.fn().mockRejectedValue(new Error('加载失败')), saveBrowserSettings: vi.fn() };
    const settings = new ASVBrowserSettings(document.body, rest, vi.fn());
    await settings.show();
    expect(document.querySelector('[role="status"]')!.textContent).toContain('加载失败');
    expect(document.querySelector<HTMLButtonElement>('[type="submit"]')!.disabled).toBe(true);
    expect(rest.saveBrowserSettings).not.toHaveBeenCalled();
  });
});
