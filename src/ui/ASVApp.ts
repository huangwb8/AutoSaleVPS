import { ASVRestClient, DiagnosticsResult, VpsRecord } from '../core/asvRestClient';
import { ASVConfig } from '../core/asvConfig';

import { ASVModal } from './ASVModal';
import { ASVLogPanel } from './ASVLogPanel';
import { buildMetaDisplay, describeMetaSource } from './metaUtils';

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
model = 'gpt-4.1-mini'
prompt_valid = '基于输入判断VPS是否已经卖完或下架；如果已经卖完或下架，请返回FALSE；否则，请返回TRUE'
prompt_vps_info = '基于输入给出一断推销VPS的广告，20-100个简体中文。推广要求贴合VPS的实际，不能无脑推，要像一个优秀的VPS推广商那样推广产品。'
prompt_meta_layout = '请将输入JSON整理成固定的8行中文，依次为：厂商、CPU、内存、存储、带宽、网络、价格、地理位置。每一行必须使用“字段：内容”格式，字段名需与上述完全一致，如信息缺失则填“-”，不要输出其他文字。'`;

const EXTRA_CSS_TEMPLATE = `/* 适配 https://blognas.hwb0307.com/ad 的通透布局 */
.asv-root {
  padding: 0;
  background: transparent;
}

.asv-card {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.58), rgba(226, 239, 255, 0.32));
  border: 1px solid rgba(255, 255, 255, 0.25);
  border-radius: 24px;
  box-shadow: 0 35px 80px rgba(15, 23, 42, 0.25);
  backdrop-filter: blur(12px);
}

.asv-card--offline {
  border-color: rgba(239, 79, 79, 0.5);
  box-shadow: 0 35px 80px rgba(239, 79, 79, 0.4);
}

.asv-sale-btn {
  background: linear-gradient(120deg, #ff9f5a, #f05438);
  color: #fff !important;
  border: none;
  box-shadow: 0 14px 30px rgba(240, 84, 56, 0.3);
}`;

interface BootstrapData {
  restUrl: string;
  nonce: string;
  isAdmin: boolean;
  timezone: string;
  version: string;
  hasKey: boolean;
  options: string[];
  extraCss: string;
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
  private logPanelHost!: HTMLElement;
  private logPanelClearButton?: HTMLButtonElement;
  private bootstrap: BootstrapData;
  private vpsContainer!: HTMLElement;
  private timezoneSelect!: HTMLSelectElement;
  private configBundle!: ModalBundle;
  private modelBundle!: ModalBundle;
  private cssBundle!: ModalBundle;
  private keyModal!: ASVModal;
  private keyInput!: HTMLInputElement;
  private hasKey: boolean;
  private currentConfig?: ASVConfig;
  private currentVps: VpsRecord[] = [];
  private availabilityTimer?: number;
  private inFlightValidation = false;
  private extraCssNode?: HTMLStyleElement;

  constructor(root: HTMLElement, bootstrap: BootstrapData) {
    this.root = root;
    this.bootstrap = bootstrap;
    this.rest = new ASVRestClient(bootstrap.restUrl, bootstrap.nonce);
    this.hasKey = bootstrap.hasKey;
  }

  init() {
    this.applyExtraCss(this.bootstrap.extraCss || '');
    this.renderLayout();
    this.mountLogPanel();
    this.attachTimezone();
    this.attachButtons();
    this.loadVpsCards(true);

    if (this.bootstrap.isAdmin) {
      this.prepareModals();
      this.loadConfigForEditing();
      this.loadModelForEditing();
      this.loadExtraCss();
    }
  }

  private mountLogPanel() {
    this.logPanel = new ASVLogPanel(this.logPanelHost, this.bootstrap.timezone);
    this.logPanelClearButton?.addEventListener('click', () => {
      this.logPanel.clear();
    });
  }

  private renderLayout() {
    this.root.innerHTML = '';

    if (this.bootstrap.isAdmin) {
      const toolbar = document.createElement('div');
      toolbar.className = 'asv-toolbar';

      const primaryRow = document.createElement('div');
      primaryRow.className = 'asv-toolbar__row';

      const actions = document.createElement('div');
      actions.className = 'asv-actions';
      const secondaryActions = document.createElement('div');
      secondaryActions.className = 'asv-actions asv-actions--secondary';

      const editConfigBtn = this.createButton('编辑VPS配置');
      editConfigBtn.dataset.action = 'edit-config';
      const editModelBtn = this.createButton('编辑模型配置');
      editModelBtn.dataset.action = 'edit-model';
      const addKeyBtn = this.createButton(this.hasKey ? '更新KEY' : '添加KEY');
      addKeyBtn.dataset.action = 'add-key';
      const cssBtn = this.createButton('额外CSS');
      cssBtn.dataset.action = 'edit-css';
      const checkBtn = this.createButton('检查可用性');
      checkBtn.dataset.action = 'diagnostics';
      const statusBtn = this.createButton('查看VPS状态');
      statusBtn.dataset.action = 'check-vps';
      actions.append(editConfigBtn, editModelBtn, addKeyBtn, cssBtn);
      secondaryActions.append(checkBtn, statusBtn);

      const timezoneWrap = document.createElement('div');
      timezoneWrap.className = 'asv-timezone';
      const label = document.createElement('label');
      label.textContent = '时区';
      const select = document.createElement('select');
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

      primaryRow.append(actions, timezoneWrap);
      toolbar.append(primaryRow, secondaryActions);
      this.root.appendChild(toolbar);
    }

    const logWrapper = document.createElement('div');
    logWrapper.className = 'asv-log-panel';
    const logHeader = document.createElement('div');
    logHeader.className = 'asv-log-panel__header';
    const logTitle = document.createElement('span');
    logTitle.textContent = '系统日志';
    const clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.className = 'asv-log-panel__clear';
    clearBtn.textContent = '清空日志';
    logHeader.append(logTitle, clearBtn);
    const logBody = document.createElement('div');
    logBody.className = 'asv-log-panel__body';
    logWrapper.append(logHeader, logBody);
    this.logPanelHost = logBody;
    this.logPanelClearButton = clearBtn;
    this.root.appendChild(logWrapper);

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
        case 'edit-css':
          this.cssBundle.modal.show();
          break;
        case 'add-key':
          this.keyModal.show();
          break;
        case 'check-vps':
          this.handleManualStatusCheck();
          break;
        case 'diagnostics':
          this.runDiagnostics();
          break;
        default:
          break;
      }
    });
  }

  private async handleManualStatusCheck() {
    if (!this.bootstrap.isAdmin) {
      return;
    }

    await this.loadVpsCards(false);
    this.triggerAvailabilitySweep('手动查看VPS状态');
  }

  private prepareModals() {
    this.configBundle = this.createEditorModal('编辑 config.toml', CONFIG_DEFAULT_TEMPLATE, async () => {
      try {
        await this.rest.saveConfig(this.configBundle.textarea.value);
        this.logPanel.push('配置已保存', 'success');
        this.currentConfig = new ASVConfig(this.configBundle.textarea.value);
        this.scheduleValidation();
        this.configBundle.modal.hide();
      } catch (error) {
        this.logPanel.push(`保存失败：${(error as Error).message}`, 'error');
      }
    });

    this.modelBundle = this.createEditorModal('编辑 model.toml', MODEL_DEFAULT_TEMPLATE, async () => {
      try {
        await this.rest.saveModel(this.modelBundle.textarea.value);
        this.logPanel.push('模型配置已保存', 'success');
        this.modelBundle.modal.hide();
      } catch (error) {
        this.logPanel.push(`保存模型失败：${(error as Error).message}`, 'error');
      }
    });

    this.cssBundle = this.createEditorModal('额外 CSS（可选）', EXTRA_CSS_TEMPLATE, async () => {
      try {
        await this.rest.saveExtraCss(this.cssBundle.textarea.value);
        this.applyExtraCss(this.cssBundle.textarea.value);
        this.logPanel.push('额外 CSS 已保存', 'success');
        this.cssBundle.modal.hide();
      } catch (error) {
        this.logPanel.push(`保存 CSS 失败：${(error as Error).message}`, 'error');
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

  private async loadExtraCss() {
    try {
      const { extraCss } = await this.rest.fetchExtraCss();
      this.cssBundle.textarea.value = extraCss;
      this.applyExtraCss(extraCss);
      this.logPanel.push('已载入额外 CSS');
    } catch (error) {
      this.logPanel.push(`载入额外 CSS 失败：${(error as Error).message}`, 'error');
    }
  }

  private async loadVpsCards(useCache = true) {
    this.vpsContainer.innerHTML = '<div class="asv-loading">正在载入VPS数据...</div>';
    try {
      const { vps } = useCache ? await this.rest.fetchCachedVps() : await this.rest.fetchVps();
      this.currentVps = vps;
      this.renderVpsList(vps);
      if (this.bootstrap.isAdmin) {
        if (useCache) {
          this.logPanel.push('已载入缓存的 VPS 状态，如需更新请点击“查看VPS状态”', 'info');
        } else {
          this.logPanel.push('已抓取最新 VPS 数据', 'success');
        }
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

      const title = document.createElement('div');
      title.className = 'asv-card__title';
      const heading = document.createElement('strong');
      heading.textContent = `${item.vendor.toUpperCase()} #${item.pid}`;
      title.append(heading, this.createStatusPill(item));
      card.appendChild(title);

      const saleBtn = document.createElement('a');
      saleBtn.href = item.sale_url;
      saleBtn.target = '_blank';
      saleBtn.rel = 'noopener';
      saleBtn.className = 'asv-sale-btn';
      saleBtn.textContent = '打开推广链接';
      card.appendChild(saleBtn);

      if (!this.bootstrap.isAdmin) {
        const promo = document.createElement('p');
        promo.className = 'asv-card__promo';
        promo.textContent = this.formatPromo(item);
        card.appendChild(promo);
      } else {
        card.appendChild(this.createPromoEditor(item));
      }

      if (this.bootstrap.isAdmin) {
        card.appendChild(this.createMetaEditor(item));
      } else {
        card.appendChild(this.createMetaViewer(item));
      }

      if (item.human_comment) {
        const note = document.createElement('div');
        note.className = 'asv-card__note';
        note.textContent = item.human_comment;
        card.appendChild(note);
      }

      const footer = document.createElement('footer');
      footer.className = 'asv-card__footer';
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

  private createPromoEditor(item: VpsRecord) {
    const editor = document.createElement('div');
    editor.className = 'asv-promo-editor';

    const textarea = document.createElement('textarea');
    textarea.className = 'asv-promo-editor__textarea';
    textarea.value = item.promo || '';
    textarea.placeholder = '输入自定义推广语，20-100 字，保持真实配置。';
    editor.appendChild(textarea);

    const actions = document.createElement('div');
    actions.className = 'asv-promo-actions';

    const saveBtn = this.createButton('保存推广语');
    saveBtn.classList.add('asv-btn--sm');

    const regenBtn = this.createButton('AI 重写');
    regenBtn.classList.add('asv-btn--ghost', 'asv-btn--sm');

    saveBtn.addEventListener('click', () =>
      this.persistPromoOverride(item.vendor, item.pid, textarea, saveBtn, regenBtn)
    );
    regenBtn.addEventListener('click', () =>
      this.regeneratePromo(item.vendor, item.pid, textarea, saveBtn, regenBtn)
    );

    actions.append(saveBtn, regenBtn);
    editor.appendChild(actions);

    return editor;
  }

  private createMetaViewer(item: VpsRecord) {
    const container = document.createElement('div');
    container.className = 'asv-meta-section';

    const pre = document.createElement('pre');
    pre.className = 'asv-meta-block';
    pre.textContent = this.formatMetaDisplay(item);
    container.appendChild(pre);

    return container;
  }

  private createMetaEditor(item: VpsRecord) {
    const container = document.createElement('div');
    container.className = 'asv-meta-editor';

    const textarea = document.createElement('textarea');
    textarea.className = 'asv-meta-editor__textarea asv-meta-block';
    textarea.value = this.formatMetaDisplay(item);
    textarea.spellcheck = false;
    container.appendChild(textarea);

    const hint = document.createElement('div');
    hint.className = 'asv-meta-hint';
    hint.textContent = describeMetaSource(item.meta_source);
    container.appendChild(hint);

    const actions = document.createElement('div');
    actions.className = 'asv-meta-actions';

    const saveBtn = this.createButton('保存信息');
    saveBtn.classList.add('asv-btn--sm');

    const aiBtn = this.createButton('AI 整理');
    aiBtn.classList.add('asv-btn--ghost', 'asv-btn--sm');

    saveBtn.addEventListener('click', () =>
      this.persistMetaOverride(item.vendor, item.pid, textarea, hint, saveBtn, aiBtn)
    );

    aiBtn.addEventListener('click', () =>
      this.regenerateMeta(item.vendor, item.pid, textarea, hint, saveBtn, aiBtn)
    );

    actions.append(saveBtn, aiBtn);
    container.appendChild(actions);

    return container;
  }

  private formatMetaDisplay(item: VpsRecord) {
    return buildMetaDisplay(item.meta_display, item.meta || []);
  }

  private togglePromoButtons(disabled: boolean, ...buttons: HTMLButtonElement[]) {
    buttons.forEach((btn) => {
      btn.disabled = disabled;
    });
  }

  private toggleMetaButtons(disabled: boolean, ...buttons: HTMLButtonElement[]) {
    buttons.forEach((btn) => {
      btn.disabled = disabled;
    });
  }

  private async persistPromoOverride(
    vendor: string,
    pid: string,
    textarea: HTMLTextAreaElement,
    saveBtn: HTMLButtonElement,
    regenBtn: HTMLButtonElement
  ) {
    const value = textarea.value.trim();
    if (!value) {
      this.logPanel.push('推广语不能为空', 'error');
      textarea.focus();
      return;
    }

    this.togglePromoButtons(true, saveBtn, regenBtn);
    try {
      const result = await this.rest.savePromo(vendor, pid, value);
      textarea.value = result.promo || '';
      this.updatePromoRecord(vendor, pid, result.promo, result.source);
      this.logPanel.push(`${vendor} ${pid} 推广语已保存`, 'success');
    } catch (error) {
      this.logPanel.push(`保存推广语失败：${(error as Error).message}`, 'error');
    } finally {
      this.togglePromoButtons(false, saveBtn, regenBtn);
    }
  }

  private async persistMetaOverride(
    vendor: string,
    pid: string,
    textarea: HTMLTextAreaElement,
    hint: HTMLElement,
    saveBtn: HTMLButtonElement,
    regenBtn: HTMLButtonElement
  ) {
    const value = textarea.value.trim();
    if (!value) {
      this.logPanel.push('展示信息不能为空', 'error');
      textarea.focus();
      return;
    }

    this.toggleMetaButtons(true, saveBtn, regenBtn);
    try {
      const result = await this.rest.saveMeta(vendor, pid, value);
      const content = result.content || '';
      textarea.value = content;
      hint.textContent = describeMetaSource(result.source);
      this.updateMetaRecord(vendor, pid, content, result.source);
      this.logPanel.push(`${vendor} ${pid} 元信息已保存`, 'success');
    } catch (error) {
      this.logPanel.push(`保存元信息失败：${(error as Error).message}`, 'error');
    } finally {
      this.toggleMetaButtons(false, saveBtn, regenBtn);
    }
  }

  private async regenerateMeta(
    vendor: string,
    pid: string,
    textarea: HTMLTextAreaElement,
    hint: HTMLElement,
    saveBtn: HTMLButtonElement,
    regenBtn: HTMLButtonElement
  ) {
    this.toggleMetaButtons(true, saveBtn, regenBtn);
    try {
      const result = await this.rest.refreshMeta(vendor, pid);
      const content = result.content || '';
      textarea.value = content;
      hint.textContent = describeMetaSource(result.source);
      this.updateMetaRecord(vendor, pid, content, result.source);
      this.logPanel.push(`${vendor} ${pid} 元信息已由 AI 整理`, 'success');
    } catch (error) {
      this.logPanel.push(`AI 整理失败：${(error as Error).message}`, 'error');
    } finally {
      this.toggleMetaButtons(false, saveBtn, regenBtn);
    }
  }

  private async regeneratePromo(
    vendor: string,
    pid: string,
    textarea: HTMLTextAreaElement,
    saveBtn: HTMLButtonElement,
    regenBtn: HTMLButtonElement
  ) {
    this.togglePromoButtons(true, saveBtn, regenBtn);
    try {
      const result = await this.rest.refreshPromo(vendor, pid);
      textarea.value = result.promo || '';
      this.updatePromoRecord(vendor, pid, result.promo, result.source);
      this.logPanel.push(`${vendor} ${pid} 推广语已重新生成`, 'success');
    } catch (error) {
      this.logPanel.push(`重新生成推广语失败：${(error as Error).message}`, 'error');
    } finally {
      this.togglePromoButtons(false, saveBtn, regenBtn);
    }
  }

  private updatePromoRecord(vendor: string, pid: string, promo: string, source: string) {
    const record = this.currentVps.find((item) => item.vendor === vendor && item.pid === pid);
    if (record) {
      record.promo = promo;
      record.promo_source = source;
    }
  }

  private updateMetaRecord(vendor: string, pid: string, content: string, source?: string) {
    const record = this.currentVps.find((item) => item.vendor === vendor && item.pid === pid);
    if (record) {
      record.meta_display = content;
      record.meta_source = source;
    }
  }

  private formatPromo(item: VpsRecord) {
    return item.promo || '等待生成推广话术...';
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
      card.querySelector('.asv-soldout')?.remove();
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

  private applyExtraCss(css: string) {
    const trimmed = (css || '').trim();
    if (!trimmed) {
      if (this.extraCssNode) {
        this.extraCssNode.remove();
        this.extraCssNode = undefined;
      }
      return;
    }

    if (!this.extraCssNode) {
      const style = document.createElement('style');
      style.dataset.source = 'asv-extra-css';
      document.head.appendChild(style);
      this.extraCssNode = style;
    }

    this.extraCssNode.textContent = trimmed;
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
