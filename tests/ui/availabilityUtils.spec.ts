import { describe, expect, it } from 'vitest';
import { availabilityLabel } from '../../src/ui/availabilityUtils';

describe('Availability display', () => {
  it('distinguishes failed checks from confirmed stock', () => {
    expect(availabilityLabel(null, 'asv_blocked')).toBe('待确认');
    expect(availabilityLabel(true, 'asv_blocked')).toBe('在线（待复核）');
    expect(availabilityLabel(false, 'asv_blocked')).toBe('已售罄（待复核）');
    expect(availabilityLabel(false, 'ok')).toBe('已售罄');
    expect(availabilityLabel(null)).toBe('等待验证');
  });
});
