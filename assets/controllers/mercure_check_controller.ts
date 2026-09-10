import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['log'];
    static values = { hub: String, topic: String, publishUrl: String, csrf: String };
    declare readonly logTarget: HTMLUListElement;
    declare readonly hubValue: string;
    declare readonly topicValue: string;
    declare readonly publishUrlValue: string;
    declare readonly csrfValue: string;
    private source?: EventSource;

    connect(): void {
        const url = new URL(this.hubValue);
        url.searchParams.append('topic', this.topicValue);
        this.source = new EventSource(url, { withCredentials: true });
        this.source.onmessage = (e) => this.append(JSON.parse(e.data).text);
        this.source.onerror = () => this.append('connection error');
    }

    disconnect(): void {
        this.source?.close();
    }

    async publish(): Promise<void> {
        const body = new URLSearchParams({ topic: this.topicValue, _token: this.csrfValue });
        await fetch(this.publishUrlValue, { method: 'POST', body, headers: { 'X-Requested-With': 'fetch' } });
    }

    private append(text: string): void {
        const li = document.createElement('li');
        li.textContent = `${new Date().toLocaleTimeString()} — ${text}`;
        this.logTarget.append(li);
    }
}
