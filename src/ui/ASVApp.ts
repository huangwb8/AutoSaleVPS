import { ASVRestClient, DiagnosticsResult, VpsRecord } from '../core/asvRestClient';
import { ASVConfig } from '../core/asvConfig';

import { ASVModal } from './ASVModal';
import { ASVLogPanel } from './ASVLogPanel';

const CONFIG_DEFAULT_TEMPLATE = `[aff]
[aff.rn]
4886

[url]
[url.rn]
sale_format = 'https://my.racknerd.com/aff.php?aff={aff}&pid={pid}'
valid_format = 'https://my.racknerd.com/cart.php?a=add&pid={pid}'
valid_interval_time = '172800'
valid_vps_time = '5-10'

[vps]
[vps.rn.923]
pid = '923'
human_comment = '非常基础的一款VPS，但是容量相对来说还是比较大的。'

[vps.rn.924]
pid = '924'
human_comment = ''

[vps.rn.925]
pid = '925'
human_comment = ''

[vps.rn.926]
pid = '926'
human_comment = ''

[vps.rn.927]
pid = '927'
human_comment = ''`;

const MODEL_DEFAULT_TEMPLATE = `[model_providers]
[model_providers.omg]
base_url = 'https://api.ohmygpt.com/v1'
prompt_valid = '基于输入判断VPS是否已经卖完或下架；如果已经卖完或下架，请返回FALSE；否则，请返回TRUE'
prompt_vps_info = '基于输入给出一断推销VPS的广告，20-100个简体中文。推广要求贴合VPS的实际，不能无脑推，要像一个优秀的VPS推广商那样推广产品。'`;

interface BootstrapData {
  restUrl: string;
  nonce: string;
  isAdmin: boolean;
  timezone: string;
  version: string;
  hasKey: boolean;
  options: string[];
}

interface ModalBundle {
  modal: ASVModal;
  textarea: HTMLTextAreaElement;
  saveButton: HTMLButtonElement;
}

export class ASVApp {
  private root: HTMLElement;
  private rest: ASVRestClient;
  private logPanel!: ASVLogPanel;
  private bootstrap: BootstrapData;
  private vpsContainer!: HTMLElement;
  private timezoneSelect!: HTMLSelectElement;
  private configBundle!: ModalBundle;
  private modelBundle!: ModalBundle;
  private keyModal!: ASVModal;
  private keyInput!: HTMLInputElement;
  private hasKey: boolean;
  private currentConfig?: ASVConfig;
  private currentVps: VpsRecord[] = [];
  private availabilityTimer?: number;
  private inFlightValidation = false;

  constructor(root: HTMLElement, bootstrap: BootstrapData) {
    this.root = root;
    this.bootstrap = bootstrap;
    this.rest = new ASVRestClient(bootstrap.restUrl, bootstrap.nonce);
    this.hasKey = bootstrap.hasKey;
  }

  init() {
    this.renderLayout();
    this.mountLogPanel();
    this.attachTimezone();
    this.attachButtons();
    this.loadVpsCards();

    if (this.bootstrap.isAdmin) {
      this.prepareModals();
      this.loadConfigForEditing();
      this.loadModelForEditing();
    }
  }

  private mountLogPanel() {
    const panel = document.createElement('div');
    panel.className = 'asv-log-panel';
    this.root.appendChild(panel);
    this.logPanel = new ASVLogPanel(panel, this.bootstrap.timezone);
  }

  private renderLayout() {
    this.root.innerHTML = '';

    const toolbar = document.createElement('div');
    toolbar.className = 'asv-toolbar';

    const actions = document.createElement('div');
    actions.className = 'asv-actions';

    const editConfigBtn = this.createButton('编辑VPS配置');
    editConfigBtn.dataset.action = 'edit-config';
    const editModelBtn = this.createButton('编辑模型配置');
    editModelBtn.dataset.action = 'edit-model';
    const addKeyBtn = this.createButton(this.hasKey ? '更新KEY' : '添加KEY');
    addKeyBtn.dataset.action = 'add-key';
    const checkBtn = this.createButton('检查可用性');
    checkBtn.dataset.action = 'diagnostics';

    if (this.bootstrap.isAdmin) {
      actions.append(editConfigBtn, editModelBtn, addKeyBtn, checkBtn);
    } else {
      const info = document.createElement('p');
      info.className = 'asv-viewer-note';
      info.textContent = '您正在查看公开推广信息，配置项仅管理员可见。';
      actions.appendChild(info);
    }

    const timezoneWrap = document.createElement('div');
    timezoneWrap.className = 'asv-timezone';
    const label = document.createElement('label');
    label.textContent = '时区';
    const select = document.createElement('select');
    select.disabled = !this.bootstrap.isAdmin;
    this.bootstrap.options.forEach((zone) => {
      const option = document.createElement('option');
      option.value = zone;
      option.textContent = zone;
      if (zone === this.bootstrap.timezone) {
        option.selected = true;
      }
      select.appendChild(option);
    });
    this.timezoneSelect = select;
    label.appendChild(select);
    timezoneWrap.appendChild(label);

    toolbar.append(actions, timezoneWrap);
    this.root.appendChild(toolbar);

    this.vpsContainer = document.createElement('div');
    this.vpsContainer.className = 'asv-vps-list';
    this.root.appendChild(this.vpsContainer);
  }

  private createButton(text: string) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = text;
    btn.className = 'asv-btn';
    return btn;
  }

  private attachTimezone() {
    this.timezoneSelect?.addEventListener('change', async () => {
      const timezone = this.timezoneSelect.value;
      try {
        await this.rest.saveTimezone(timezone);
        this.logPanel.setTimezone(timezone);
        this.logPanel.push(`已切换到 ${timezone}`);
      } catch (error) {
        this.logPanel.push(`设置时区失败：${(error as Error).message}`, 'error');
      }
    });
  }

  private attachButtons() {
    this.root.addEventListener('click', (event) => {
      const target = event.target as HTMLElement;
      if (!target.matches('[data-action]')) {
        return;
      }

      if (!this.bootstrap.isAdmin) {
        this.logPanel.push('需要管理员权限执行此操作', 'error');
        return;
      }

      switch (target.dataset.action) {
        case 'edit-config':
          this.configBundle.modal.show();
          break;
        case 'edit-model':
          this.modelBundle.modal.show();
          break;
        case 'add-key':
          this.keyModal.show();
          break;
        case 'diagnostics':
          this.runDiagnostics();
          break;
        default:
          break;
      }
    });
  }

  private prepareModals() {
    this.configBundle = this.createEditorModal('编辑 config.toml', CONFIG_DEFAULT_TEMPLATE, async () => {
      try {
        await this.rest.saveConfig(this.configBundle.textarea.value);
        this.logPanel.push('配置已保存', 'success');
        this.currentConfig = new ASVConfig(this.configBundle.textarea.value);
        this.loadVpsCards();
        this.triggerAvailabilitySweep('保存配置后立即验证');
      } catch (error) {
        this.logPanel.push(`保存失败：${(error as Error).message}`, 'error');
      }
    });

    this.modelBundle = this.createEditorModal('编辑 model.toml', MODEL_DEFAULT_TEMPLATE, async () => {
      try {
        await this.rest.saveModel(this.modelBundle.textarea.value);
        this.logPanel.push('模型配置已保存', 'success');
        this.loadVpsCards();
      } catch (error) {
        this.logPanel.push(`保存模型失败：${(error as Error).message}`, 'error');
      }
    });

    const keyContent = document.createElement('div');
    keyContent.className = 'asv-modal__content';
    const input = document.createElement('input');
    input.type = 'password';
    input.placeholder = 'sk-xxx';
    input.className = 'asv-input';
    this.keyInput = input;

    const eye = document.createElement('button');
    eye.type = 'button';
    eye.className = 'asv-eye';
    eye.textContent = '👁';
    eye.addEventListener('mouseenter', () => {
      this.keyInput.type = 'text';
    });
    eye.addEventListener('mouseleave', () => {
      this.keyInput.type = 'password';
    });

    const saveBtn = this.createButton('保存配置');
    saveBtn.addEventListener('click', async () => {
      try {
        await this.rest.saveApiKey(this.keyInput.value);
        this.hasKey = true;
        this.logPanel.push('API KEY 已更新', 'success');
        this.keyModal.hide();
        this.keyInput.value = '';
      } catch (error) {
        this.logPanel.push(`保存 KEY 失败：${(error as Error).message}`, 'error');
      }
    });

    keyContent.append(input, eye, saveBtn);
    this.keyModal = new ASVModal('添加/更新 KEY', keyContent);
    this.keyModal.mount(this.root);
  }

  private createEditorModal(title: string, helperTemplate: string, onSave: () => void): ModalBundle {
    const wrapper = document.createElement('div');
    wrapper.className = 'asv-modal__content';
    if (helperTemplate) {
      const helper = document.createElement('details');
      helper.className = 'asv-helper';
      const summary = document.createElement('summary');
      summary.textContent = '查看默认示例';
      const pre = document.createElement('pre');
      pre.textContent = helperTemplate;
      helper.append(summary, pre);
      wrapper.appendChild(helper);
    }
    const textarea = document.createElement('textarea');
    textarea.className = 'asv-textarea';
    textarea.rows = 18;
    const saveBtn = this.createButton('保存配置');
    saveBtn.addEventListener('click', onSave);
    wrapper.append(textarea, saveBtn);
    const modal = new ASVModal(title, wrapper);
    modal.mount(this.root);
    return { modal, textarea, saveButton: saveBtn };
  }

  private async loadConfigForEditing() {
    try {
      const { content } = await this.rest.fetchConfig();
      this.configBundle.textarea.value = content;
      this.currentConfig = new ASVConfig(content);
      this.logPanel.push('已载入 config.toml');
      this.scheduleValidation();
    } catch (error) {
      this.logPanel.push(`载入 config.toml 失败：${(error as Error).message}`, 'error');
    }
  }

  private async loadModelForEditing() {
    try {
      const { content } = await this.rest.fetchModel();
      this.modelBundle.textarea.value = content;
      this.logPanel.push('已载入 model.toml');
    } catch (error) {
      this.logPanel.push(`载入 model.toml 失败：${(error as Error).message}`, 'error');
    }
  }

  private async loadVpsCards() {
    this.vpsContainer.innerHTML = '<div class="asv-loading">正在抓取VPS数据...</div>';
    try {
      const { vps } = await this.rest.fetchVps();
      this.currentVps = vps;
      this.renderVpsList(vps);
      if (this.bootstrap.isAdmin) {
        this.scheduleValidation();
      }
    } catch (error) {
      this.vpsContainer.innerHTML = '<p class="asv-error">无法获取VPS信息</p>';
      this.logPanel.push(`获取VPS失败：${(error as Error).message}`, 'error');
    }
  }

  private renderVpsList(vps: VpsRecord[]) {
    this.vpsContainer.innerHTML = '';
    if (!vps.length) {
      this.vpsContainer.innerHTML = '<p>暂无 VPS 记录</p>';
      return;
    }

    vps.forEach((item) => {
      const card = document.createElement('article');
      card.className = 'asv-card';
      card.dataset.key = `${item.vendor}-${item.pid}`;
      if (item.available === false) {
        card.classList.add('asv-card--offline');
      }

      const header = document.createElement('header');
      header.className = 'asv-card__header';
      const left = document.createElement('div');
      const link = document.createElement('a');
      link.href = item.sale_url;
      link.target = '_blank';
      link.rel = 'noopener';
      link.className = 'asv-sale-link';
      link.textContent = '推广链接';
      const tag = document.createElement('span');
      tag.className = 'asv-card__tag';
      tag.textContent = `${item.vendor.toUpperCase()} #${item.pid}`;
      left.append(link, tag);
      header.append(left, this.createStatusPill(item));
      card.appendChild(header);

      const promo = document.createElement('p');
      promo.className = 'asv-card__promo';
      promo.textContent = item.promo || '等待生成推广话术...';
      card.appendChild(promo);

      const metaList = document.createElement('ul');
      metaList.className = 'asv-card__meta';
      item.meta.forEach((line) => {
        const li = document.createElement('li');
        li.textContent = line;
        metaList.appendChild(li);
      });
      card.appendChild(metaList);

      const footer = document.createElement('footer');
      footer.className = 'asv-card__footer';
      if (item.human_comment) {
        const note = document.createElement('span');
        note.textContent = `备注：${item.human_comment}`;
        footer.appendChild(note);
      }
      if (this.bootstrap.isAdmin) {
        const btn = document.createElement('button');
        btn.className = 'asv-btn asv-btn--ghost';
        btn.type = 'button';
        btn.textContent = '验证';
        btn.addEventListener('click', () => this.validateSingle(item.vendor, item.pid, '手动验证'));
        footer.appendChild(btn);
      }
      card.appendChild(footer);

      this.vpsContainer.appendChild(card);
    });
  }

  private createStatusPill(item: VpsRecord) {
    const pill = document.createElement('span');
    pill.className = 'asv-status-pill';
    if (item.available === null) {
      pill.classList.add('asv-status-pill--idle');
      pill.textContent = '等待验证';
    } else if (item.available) {
      pill.classList.add('asv-status-pill--up');
      pill.textContent = '在线';
    } else {
      pill.classList.add('asv-status-pill--down');
      pill.textContent = '已售罄';
    }

    return pill;
  }

  private scheduleValidation() {
    if (!this.bootstrap.isAdmin || !this.currentConfig) {
      return;
    }

    if (this.availabilityTimer) {
      window.clearInterval(this.availabilityTimer);
    }

    const definitions = this.currentConfig.listVps();
    if (!definitions.length) {
      return;
    }

    const intervals = definitions.map((item) => this.currentConfig?.getIntervalSeconds(item.vendor) ?? 86400);
    const nextInterval = Math.max(60, Math.min(...intervals));
    this.availabilityTimer = window.setInterval(() => {
      this.triggerAvailabilitySweep('定时巡检');
    }, nextInterval * 1000);
  }

  private triggerAvailabilitySweep(reason: string) {
    if (!this.bootstrap.isAdmin || this.inFlightValidation) {
      return;
    }

    this.inFlightValidation = true;
    this.logPanel.push(`${reason}：开始验证所有 VPS`);
    this.validateAll()
      .catch((error) => this.logPanel.push(`批量验证失败：${(error as Error).message}`, 'error'))
      .finally(() => {
        this.inFlightValidation = false;
      });
  }

  private async validateAll() {
    for (const vps of this.currentVps) {
      await this.validateSingle(vps.vendor, vps.pid, '批量队列');
      const range = this.currentConfig?.getDelayRange(vps.vendor) ?? { min: 5, max: 10 };
      const delay = this.randomDelay(range.min, range.max);
      await this.sleep(delay);
    }
  }

  private async validateSingle(vendor: string, pid: string, source: string) {
    try {
      const result = await this.rest.validateVps(vendor, pid);
      this.logPanel.push(`${source}：${vendor} ${pid} -> ${result.available ? '在线' : '售罄'} (${result.message})`);
      this.applyStatus(vendor, pid, result.available, result.message, result.checked_at);
    } catch (error) {
      this.logPanel.push(`验证 ${vendor} ${pid} 失败：${(error as Error).message}`, 'error');
    }
  }

  private applyStatus(vendor: string, pid: string, available: boolean, message: string, checkedAt?: number) {
    const key = `${vendor}-${pid}`;
    const card = this.vpsContainer.querySelector(`[data-key="${key}"]`);
    if (card) {
      card.classList.toggle('asv-card--offline', !available);
      const pill = card.querySelector('.asv-status-pill');
      if (pill) {
        pill.textContent = available ? '在线' : '已售罄';
        pill.className = `asv-status-pill ${available ? 'asv-status-pill--up' : 'asv-status-pill--down'}`;
      }
      if (!available) {
        let notice = card.querySelector('.asv-soldout') as HTMLElement | null;
        if (!notice) {
          notice = document.createElement('div');
          notice.className = 'asv-soldout';
          card.appendChild(notice);
        }
        notice.textContent = message || '该 VPS 暂不可用';
      } else {
        card.querySelector('.asv-soldout')?.remove();
      }
    }

    const match = this.currentVps.find((item) => item.vendor === vendor && item.pid === pid);
    if (match) {
      match.available = available;
      match.message = message;
      match.checked_at = checkedAt ?? Date.now() / 1000;
    }
  }

  private async runDiagnostics() {
    try {
      const result = await this.rest.runDiagnostics();
      this.renderDiagnostics(result);
    } catch (error) {
      this.logPanel.push(`诊断失败：${(error as Error).message}`, 'error');
    }
  }

  private renderDiagnostics(result: DiagnosticsResult) {
    this.logPanel.push(`网络：${result.network.ok ? '正常' : '异常'} - ${result.network.message}`);
    this.logPanel.push(`LLM：${result.llm.ok ? '就绪' : '异常'} - ${result.llm.message}`);
  }

  private randomDelay(min: number, max: number) {
    const delta = max - min;
    return (min + Math.random() * delta) * 1000;
  }

  private sleep(duration: number) {
    return new Promise((resolve) => {
      window.setTimeout(resolve, duration);
    });
  }
}
