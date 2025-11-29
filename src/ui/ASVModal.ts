export class ASVModal {
  private element: HTMLElement;

  constructor(title: string, content: HTMLElement) {
    this.element = document.createElement('div');
    this.element.className = 'asv-modal hidden';

    const inner = document.createElement('div');
    inner.className = 'asv-modal__body';

    const header = document.createElement('header');
    header.className = 'asv-modal__header';
    header.innerHTML = `<h3>${title}</h3>`;

    const closeBtn = document.createElement('button');
    closeBtn.className = 'asv-modal__close';
    closeBtn.type = 'button';
    closeBtn.textContent = '×';
    closeBtn.addEventListener('click', () => this.hide());
    header.appendChild(closeBtn);

    inner.appendChild(header);
    inner.appendChild(content);
    this.element.appendChild(inner);
  }

  mount(target: HTMLElement) {
    target.appendChild(this.element);
  }

  show() {
    this.element.classList.remove('hidden');
  }

  hide() {
    this.element.classList.add('hidden');
  }

  getNode() {
    return this.element;
  }
}
