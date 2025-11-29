export type LogLevel = 'info' | 'error' | 'success';

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
    const time = this.formatTime();
    const row = document.createElement('div');
    row.className = `asv-log-row asv-log-${level}`;
    row.textContent = `[${time}] ${message}`;
    this.node.prepend(row);
  }

  clear() {
    this.node.innerHTML = '';
  }

  private formatTime() {
    try {
      return new Date().toLocaleString('zh-CN', {
        timeZone: this.timezone,
        hour12: false
      });
    } catch (error) {
      console.warn('timezone fallback', error);
      return new Date().toLocaleString();
    }
  }
}
