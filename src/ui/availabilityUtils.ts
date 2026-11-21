export function availabilityLabel(available: boolean | null, checkStatus?: string) {
  if (checkStatus && checkStatus !== 'ok') {
    return available === null ? '待确认' : `${available ? '在线' : '已售罄'}（待复核）`;
  }
  return available === null ? '等待验证' : available ? '在线' : '已售罄';
}
