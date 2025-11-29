export type MetaSource = 'manual' | 'ai' | 'raw' | string | undefined;

const DEFAULT_META_MESSAGE = '等待抓取 VPS 详情，保存配置并验证后自动填充。';

export function buildMetaDisplay(metaDisplay: string | undefined, fallback: string[]): string {
  const normalized = (metaDisplay || '').trim();
  if (normalized.length) {
    return normalized;
  }

  if (fallback.length) {
    return fallback.join('\n');
  }

  return DEFAULT_META_MESSAGE;
}

export function describeMetaSource(source: MetaSource): string {
  switch (source) {
    case 'manual':
      return '当前为管理员手动整理内容';
    case 'ai':
      return '已由 AI 整理展示信息';
    case 'raw':
    default:
      return '基于抓取结果自动填充';
  }
}

export { DEFAULT_META_MESSAGE };
