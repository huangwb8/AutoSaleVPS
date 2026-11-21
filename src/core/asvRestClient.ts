export interface VpsRecord {
  vendor: string;
  pid: string;
  sale_url: string;
  valid_url: string;
  human_comment: string;
  meta: string[];
  meta_display?: string;
  meta_source?: string;
  promo: string;
  promo_source: string;
  available: boolean | null;
  check_status?: string;
  attempted_at?: number;
  checked_at: number | null;
  message: string;
  valid_delay: [number, number];
  interval: number;
}

export interface DiagnosticsResult {
  network: { ok: boolean; message: string };
  llm: { ok: boolean; message: string };
}

export interface LogEntry {
  ts: number;
  level: 'info' | 'error' | 'success';
  message: string;
}

export interface BrowserSettings {
  base_url: string;
  default_base_url: string;
}

export interface CronStatus {
  active: boolean;
  next_run: string | null;
  message: string;
}

export type CronAction = 'start' | 'stop' | 'reschedule';

export class ASVRestClient {
  private baseUrl: string;
  private nonce: string;

  constructor(baseUrl: string, nonce: string) {
    this.baseUrl = baseUrl.replace(/\/$/, '');
    this.nonce = nonce;
  }

  private async request<T>(path: string, options: RequestInit = {}): Promise<T> {
    const response = await fetch(`${this.baseUrl}/${path.replace(/^\//, '')}`, {
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': this.nonce,
        ...(options.headers || {})
      },
      credentials: 'same-origin',
      ...options
    });

    if (!response.ok) {
      const text = await response.text();
      let message = text;
      try {
        const error = JSON.parse(text) as { message?: string };
        if (typeof error.message === 'string') message = error.message;
      } catch {
        // Non-JSON errors retain their original message.
      }
      throw new Error(message || '请求失败');
    }

    if (response.status === 204) {
      return {} as T;
    }

    return (await response.json()) as T;
  }

  fetchConfig(): Promise<{ content: string }> {
    return this.request('/config');
  }

  fetchBrowserSettings(): Promise<BrowserSettings> {
    return this.request('/browser-settings');
  }

  saveBrowserSettings(baseUrl: string): Promise<BrowserSettings> {
    return this.request('/browser-settings', {
      method: 'POST',
      body: JSON.stringify({ base_url: baseUrl })
    });
  }

  saveConfig(content: string) {
    return this.request('/config', {
      method: 'POST',
      body: JSON.stringify({ content })
    });
  }

  fetchModel(): Promise<{ content: string }> {
    return this.request('/model');
  }

  saveModel(content: string) {
    return this.request('/model', {
      method: 'POST',
      body: JSON.stringify({ content })
    });
  }

  saveApiKey(apiKey: string) {
    return this.request('/key', {
      method: 'POST',
      body: JSON.stringify({ api_key: apiKey })
    });
  }

  fetchVps(): Promise<{ vps: VpsRecord[] }> {
    return this.request('/vps');
  }

  fetchCachedVps(): Promise<{ vps: VpsRecord[] }> {
    return this.request('/vps/cached');
  }

  validateVps(vendor: string, pid: string) {
    return this.request<Pick<VpsRecord, 'available' | 'message' | 'checked_at' | 'check_status'>>('/vps/validate', {
      method: 'POST',
      body: JSON.stringify({ vendor, pid })
    });
  }

  refreshPromo(vendor: string, pid: string) {
    return this.request<{ promo: string; source: string }>('/vps/promo', {
      method: 'POST',
      body: JSON.stringify({ vendor, pid })
    });
  }

  savePromo(vendor: string, pid: string, content: string) {
    return this.request<{ promo: string; source: string }>('/vps/promo', {
      method: 'POST',
      body: JSON.stringify({ vendor, pid, content })
    });
  }

  refreshMeta(vendor: string, pid: string) {
    return this.request<{ content: string; source: string }>('/vps/meta', {
      method: 'POST',
      body: JSON.stringify({ vendor, pid })
    });
  }

  saveMeta(vendor: string, pid: string, content: string) {
    return this.request<{ content: string; source: string }>('/vps/meta', {
      method: 'POST',
      body: JSON.stringify({ vendor, pid, content })
    });
  }

  runDiagnostics(): Promise<DiagnosticsResult> {
    return this.request('/diagnostics', { method: 'POST' });
  }

  saveTimezone(timezone: string) {
    return this.request('/timezone', {
      method: 'POST',
      body: JSON.stringify({ timezone })
    });
  }

  fetchExtraCss(): Promise<{ extraCss: string }> {
    return this.request('/design');
  }

  saveExtraCss(extraCss: string) {
    return this.request('/design', {
      method: 'POST',
      body: JSON.stringify({ extra_css: extraCss })
    });
  }

  fetchCronStatus(): Promise<CronStatus> {
    return this.request('/cron');
  }

  manageCron(action: CronAction, interval?: string) {
    return this.request<{ success: boolean; message: string }>('/cron', {
      method: 'POST',
      body: JSON.stringify({ action, interval })
    });
  }

  fetchLogs(): Promise<{ logs: LogEntry[] }> {
    return this.request('/logs');
  }

  appendLog(message: string, level: LogEntry['level'] = 'info') {
    return this.request<{ logs: LogEntry[] }>('/logs', {
      method: 'POST',
      body: JSON.stringify({ message, level })
    });
  }
}
