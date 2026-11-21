import type { ASVRestClient } from '../core/asvRestClient';

import { ASVModal } from './ASVModal';

type SettingsClient = Pick<ASVRestClient, 'fetchBrowserSettings' | 'saveBrowserSettings'>;

export class ASVBrowserSettings {
  private modal: ASVModal;
  private input: HTMLInputElement;
  private status: HTMLParagraphElement;
  private saveButton: HTMLButtonElement;
  private resetButton: HTMLButtonElement;
  private defaultUrl = 'http://asv-browser:8191';
  private saving = false;
  private rest: SettingsClient;
  private onSaved: () => void;

  constructor(root: HTMLElement, rest: SettingsClient, onSaved: () => void) {
    this.rest = rest;
    this.onSaved = onSaved;
    const form = document.createElement('form');
    form.className = 'asv-modal__content';
    const label = document.createElement('label');
    label.textContent = '浏览器采集服务地址';
    this.input = document.createElement('input');
    this.input.type = 'url';
    this.input.className = 'asv-input';
    this.input.placeholder = this.defaultUrl;
    this.input.autocomplete = 'off';
    label.appendChild(this.input);

    const help = document.createElement('p');
    help.textContent = '填写 WordPress 服务器可以访问的采集服务地址。留空将使用默认地址 http://asv-browser:8191。';
    this.status = document.createElement('p');
    this.status.setAttribute('role', 'status');
    this.status.setAttribute('aria-live', 'polite');

    const actions = document.createElement('div');
    actions.className = 'asv-actions';
    this.resetButton = document.createElement('button');
    this.resetButton.type = 'button';
    this.resetButton.className = 'asv-btn';
    this.resetButton.textContent = '恢复默认';
    this.resetButton.addEventListener('click', () => {
      this.input.value = this.defaultUrl;
      this.input.focus();
    });
    this.saveButton = document.createElement('button');
    this.saveButton.type = 'submit';
    this.saveButton.className = 'asv-btn';
    this.saveButton.textContent = '保存设置';
    actions.append(this.resetButton, this.saveButton);
    form.append(label, help, this.status, actions);
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!this.saving && !this.saveButton.disabled && form.reportValidity()) void this.save();
    });
    this.modal = new ASVModal('采集设置', form);
    this.modal.getNode().classList.add('asv-browser-settings');
    this.modal.getNode().setAttribute('role', 'dialog');
    this.modal.getNode().setAttribute('aria-label', '采集设置');
    this.modal.getNode().setAttribute('aria-modal', 'true');
    this.modal.mount(root);
  }

  async show() {
    this.modal.show();
    this.setBusy(true);
    this.status.className = '';
    this.status.textContent = '正在加载设置…';
    try {
      const settings = await this.rest.fetchBrowserSettings();
      this.defaultUrl = settings.default_base_url;
      this.input.value = settings.base_url;
      this.status.textContent = '';
      this.setBusy(false);
      this.input.focus();
    } catch (error) {
      this.showError(`加载设置失败：${(error as Error).message}`);
    }
  }

  private async save() {
    this.saving = true;
    this.setBusy(true);
    this.status.className = '';
    this.status.textContent = '正在保存…';
    this.saveButton.textContent = '保存中…';
    try {
      const settings = await this.rest.saveBrowserSettings(this.input.value.trim());
      this.input.value = settings.base_url;
      this.status.textContent = '';
      this.modal.hide();
      this.onSaved();
    } catch (error) {
      this.showError(`保存失败：${(error as Error).message}`);
    } finally {
      this.saving = false;
      this.setBusy(false);
      this.saveButton.textContent = '保存设置';
    }
  }

  private setBusy(busy: boolean) {
    this.input.disabled = busy;
    this.saveButton.disabled = busy;
    this.resetButton.disabled = busy;
  }

  private showError(message: string) {
    this.status.className = 'asv-error';
    this.status.textContent = message;
  }
}
