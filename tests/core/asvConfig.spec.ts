import { describe, it, expect } from 'vitest';
import { ASVConfig } from '../../src/core/asvConfig';

const sample = `
[aff]
[aff.rn]
code = '1234'

[url]
[url.rn]
sale_format = 'https://example.com/sale?aff={aff}&pid={pid}'
valid_format = 'https://example.com/valid?pid={pid}'
valid_interval_time = '3600'
valid_vps_time = '5-10'

[vps]
[vps.rn.100]
pid = '100'
human_comment = 'test'
`;

describe('ASVConfig', () => {
  it('parses VPS entries and builds URLs', () => {
    const config = new ASVConfig(sample);
    const list = config.listVps();
    expect(list).toHaveLength(1);
    expect(list[0].saleUrl).toBe('https://example.com/sale?aff=1234&pid=100');
    expect(list[0].validUrl).toBe('https://example.com/valid?pid=100');
  });

  it('returns delay range', () => {
    const config = new ASVConfig(sample);
    expect(config.getDelayRange('rn')).toEqual({ min: 5, max: 10 });
  });
});
