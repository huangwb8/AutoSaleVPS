import toml from 'toml';

export interface ASVVpsDefinition {
  vendor: string;
  pid: string;
  humanComment: string;
  saleUrl: string;
  validUrl: string;
}

export interface DelayRange {
  min: number;
  max: number;
}

interface ConfigTree {
  aff?: Record<string, Record<string, string>>;
  url?: Record<string, Record<string, string>>;
  vps?: Record<string, Record<string, Record<string, string>>>;
}

export class ASVConfig {
  private raw: string;
  private tree: ConfigTree;

  constructor(content: string) {
    this.raw = content;
    try {
      this.tree = (toml.parse(content) as ConfigTree) || {};
    } catch (error) {
      console.error('TOML parse error', error);
      this.tree = {};
    }
  }

  getRaw() {
    return this.raw;
  }

  listVps(): ASVVpsDefinition[] {
    const aff = this.tree.aff ?? {};
    const url = this.tree.url ?? {};
    const vps = this.tree.vps ?? {};
    const output: ASVVpsDefinition[] = [];

    Object.entries(vps).forEach(([vendor, group]) => {
      Object.entries(group).forEach(([pid, details]) => {
        const pidValue = details.pid || pid;
        const saleTemplate = url[vendor]?.sale_format ?? '';
        const validTemplate = url[vendor]?.valid_format ?? '';
        const affCode = this.resolveAffCode(aff[vendor]);
        output.push({
          vendor,
          pid: pidValue,
          humanComment: details.human_comment || '',
          saleUrl: this.fillTemplate(saleTemplate, affCode, pidValue),
          validUrl: this.fillTemplate(validTemplate, affCode, pidValue)
        });
      });
    });

    return output;
  }

  getIntervalSeconds(vendor: string) {
    const raw = this.tree.url?.[vendor]?.valid_interval_time;
    return raw ? Number(raw) : 86400;
  }

  getDelayRange(vendor: string): DelayRange {
    const raw = this.tree.url?.[vendor]?.valid_vps_time ?? '5-10';
    if (!raw.includes('-')) {
      const seconds = Math.max(1, Number(raw));
      return { min: seconds, max: seconds };
    }

    const [minRaw, maxRaw] = raw.split('-');
    const min = Math.max(1, Number(minRaw));
    const max = Math.max(min, Number(maxRaw));
    return { min, max };
  }

  private resolveAffCode(entry?: Record<string, string>) {
    if (!entry) {
      return '';
    }

    return entry.code ?? Object.values(entry).join('');
  }

  private fillTemplate(template: string, aff: string, pid: string) {
    if (!template) {
      return '';
    }

    return template.replace('{aff}', encodeURIComponent(aff)).replace('{pid}', encodeURIComponent(pid));
  }
}
