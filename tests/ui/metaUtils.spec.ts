import { describe, expect, it } from 'vitest';

import { buildMetaDisplay, describeMetaSource } from '../../src/ui/metaUtils';

describe('meta utils', () => {
  it('prefers provided meta display text', () => {
    expect(buildMetaDisplay('Line A\nLine B', ['raw'])).toBe('Line A\nLine B');
  });

  it('falls back to raw meta list when display empty', () => {
    expect(buildMetaDisplay('', ['cpu', 'ram'])).toBe('cpu\nram');
  });

  it('returns default message when nothing available', () => {
    expect(buildMetaDisplay('', [])).toContain('等待抓取');
  });

  it('describes meta sources', () => {
    expect(describeMetaSource('manual')).toContain('手动');
    expect(describeMetaSource('ai')).toContain('AI');
    expect(describeMetaSource('raw')).toContain('自动');
  });
});
