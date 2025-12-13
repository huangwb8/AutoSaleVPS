export type LogLevel = 'info' | 'error' | 'success';

export interface PersistedLog {
  ts: number;
  level: LogLevel;
  message: string;
}

export class ASVLogPanel {
  private node: HTMLElement;
  private timezone: string;

  constructor(node: HTMLElement, timezone: string) {
    this.node = node;
    this.timezone = timezone;
  }

  setTimezone(timezone: string) {
    this.timezone = timezone;
  }

  push(message: string, level: LogLevel = 'info') {
    this.renderRow(message, level);
  }

  hydrate(entries: PersistedLog[]) {
    if (!entries?.length) {
      return;
    }

    const ordered = [...entries].sort((a, b) => a.ts - b.ts);
    ordered.forEach((entry) => {
      this.renderRow(entry.message, entry.level, entry.ts);
    });
  }

  clear() {
    this.node.innerHTML = '';
  }

  private renderRow(message: string, level: LogLevel = 'info', timestamp?: number) {
    const time = this.formatTime(timestamp);
    const row = document.createElement('div');
    row.className = `asv-log-row asv-log-${level}`;
    row.textContent = `[${time}] ${message}`;
    this.node.prepend(row);
  }

  private formatTime(timestamp?: number) {
    const date = timestamp ? new Date(timestamp * 1000) : new Date();

    try {
      return date.toLocaleString('zh-CN', {
        timeZone: this.timezone,
        hour12: false
      });
    } catch (error) {
      console.warn('timezone fallback', error);
      return date.toLocaleString();
    }
  }
}
