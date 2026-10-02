// Kiosco de check-in. Página independiente (sin Livewire): habla con la API
// usando el token del dispositivo guardado en este navegador.
import Alpine from 'alpinejs';
import jsQR from 'jsqr';

const TOKEN_KEY = 'kiosk_token';
const QR_PREFIX = 'AC1.';

const storage = {
    get() {
        try { return window.localStorage.getItem(TOKEN_KEY); } catch { return null; }
    },
    set(value) {
        try { window.localStorage.setItem(TOKEN_KEY, value); } catch { /* sin almacenamiento */ }
    },
    clear() {
        try { window.localStorage.removeItem(TOKEN_KEY); } catch { /* sin almacenamiento */ }
    },
};

Alpine.data('kiosk', () => ({
    token: null,
    state: 'loading', // loading | unpaired | idle | busy | result | offline
    mode: 'qr', // qr | number | phone
    info: { device: '', location: '', brand: '' },
    pairInput: '',
    number: '',
    phone: '',
    pin: '',
    phoneStep: 'phone', // phone | pin
    result: null,
    clock: '',
    cameraError: '',
    stream: null,
    scanTimer: null,
    resetTimer: null,
    scanBuffer: '',
    lastKeyAt: 0,

    init() {
        this.tick();
        setInterval(() => this.tick(), 1000 * 15);

        this.readPairingLink();
        this.token = storage.get();
        this.token ? this.load() : (this.state = 'unpaired');

        window.addEventListener('hashchange', () => {
            if (this.readPairingLink()) {
                this.token = storage.get();
                this.load();
            }
        });

        // Lectores QR USB/Bluetooth: escriben como un teclado y terminan en Enter.
        window.addEventListener('keydown', (e) => this.onKey(e));
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') this.keepAwake();
        });
    },

    // Enlace de vinculación: /kiosco#vincular=<token>. El fragmento no llega al servidor.
    readPairingLink() {
        const match = window.location.hash.match(/vincular=([^&]+)/);
        if (!match) return false;
        storage.set(decodeURIComponent(match[1]));
        history.replaceState(null, '', window.location.pathname);
        return true;
    },

    tick() {
        this.clock = new Intl.DateTimeFormat('es-CO', { hour: '2-digit', minute: '2-digit' }).format(new Date());
    },

    async keepAwake() {
        try { await navigator.wakeLock?.request('screen'); } catch { /* opcional */ }
    },

    async api(path, options = {}) {
        const response = await fetch(`/api/kiosk/${path}`, {
            ...options,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                Authorization: `Bearer ${this.token}`,
            },
        });

        if (response.status === 401 || response.status === 403) {
            this.unpair();
            throw new Error('unauthorized');
        }

        return response;
    },

    async load() {
        this.state = 'loading';
        try {
            const response = await this.api('me');
            if (!response.ok) throw new Error('offline');
            this.info = await response.json();
            this.state = 'idle';
            this.keepAwake();
            this.setMode(this.mode);
        } catch (e) {
            if (e.message !== 'unauthorized') {
                this.state = 'offline';
                setTimeout(() => this.load(), 10000);
            }
        }
    },

    pair() {
        const value = this.pairInput.trim();
        if (!value) return;
        storage.set(value);
        this.token = value;
        this.pairInput = '';
        this.load();
    },

    unpair() {
        this.stopCamera();
        storage.clear();
        this.token = null;
        this.state = 'unpaired';
    },

    setMode(mode) {
        this.mode = mode;
        this.number = '';
        this.phone = '';
        this.pin = '';
        this.phoneStep = 'phone';
        mode === 'qr' ? this.startCamera() : this.stopCamera();
    },

    // Teclado numérico en pantalla
    press(digit) {
        if (this.mode === 'number' && this.number.length < 12) this.number += digit;
        if (this.mode === 'phone' && this.phoneStep === 'phone' && this.phone.length < 13) this.phone += digit;
        if (this.mode === 'phone' && this.phoneStep === 'pin' && this.pin.length < 4) {
            this.pin += digit;
            if (this.pin.length === 4) this.submit();
        }
    },

    erase() {
        if (this.mode === 'number') this.number = this.number.slice(0, -1);
        if (this.mode === 'phone') {
            if (this.phoneStep === 'pin') this.pin = this.pin.slice(0, -1);
            else this.phone = this.phone.slice(0, -1);
        }
    },

    confirm() {
        if (this.mode === 'number' && this.number) this.submit();
        if (this.mode === 'phone' && this.phoneStep === 'phone' && this.phone.length >= 7) this.phoneStep = 'pin';
    },

    submit() {
        if (this.mode === 'number') return this.send({ method: 'member_number', value: this.number });
        if (this.mode === 'phone') return this.send({ method: 'phone', value: this.phone, pin: this.pin });
    },

    onKey(e) {
        if (this.state !== 'idle' || e.target?.tagName === 'INPUT') return;
        const now = Date.now();
        if (now - this.lastKeyAt > 100) this.scanBuffer = '';
        this.lastKeyAt = now;

        if (e.key === 'Enter') {
            if (this.scanBuffer.startsWith(QR_PREFIX)) this.send({ method: 'qr', value: this.scanBuffer });
            this.scanBuffer = '';
        } else if (e.key.length === 1) {
            this.scanBuffer += e.key;
        }
    },

    async send(payload) {
        if (this.state === 'busy') return;
        this.state = 'busy';
        this.stopCamera();

        try {
            const response = await this.api('check-ins', { method: 'POST', body: JSON.stringify(payload) });
            const body = await response.json();
            this.result = response.ok || response.status === 429
                ? body
                : { status: 'rejected', title: 'No pudimos registrar tu ingreso', message: 'Revisa los datos e inténtalo de nuevo.', notices: [] };
        } catch (e) {
            if (e.message === 'unauthorized') return;
            this.result = { status: 'rejected', title: 'Sin conexión', message: 'No hay conexión con el servidor. Acércate a recepción.', notices: [] };
        }

        this.state = 'result';
        clearTimeout(this.resetTimer);
        this.resetTimer = setTimeout(() => this.reset(), this.result.status === 'accepted' ? 5000 : 8000);
    },

    reset() {
        clearTimeout(this.resetTimer);
        this.result = null;
        this.state = 'idle';
        this.setMode(this.mode);
    },

    async startCamera() {
        this.cameraError = '';
        if (this.stream || !navigator.mediaDevices?.getUserMedia) {
            if (!navigator.mediaDevices?.getUserMedia) this.cameraError = 'Este dispositivo no permite usar la cámara. Usa un lector QR o el teclado.';
            return;
        }

        try {
            this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
        } catch {
            this.cameraError = 'No se pudo abrir la cámara. Revisa los permisos del navegador.';
            return;
        }

        const video = this.$refs.video;
        video.srcObject = this.stream;
        await video.play().catch(() => {});

        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d', { willReadFrequently: true });

        this.scanTimer = setInterval(() => {
            if (this.state !== 'idle' || video.readyState < 2) return;
            const scale = Math.min(1, 480 / video.videoWidth);
            canvas.width = Math.floor(video.videoWidth * scale);
            canvas.height = Math.floor(video.videoHeight * scale);
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            const image = context.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' });
            if (code?.data?.startsWith(QR_PREFIX)) this.send({ method: 'qr', value: code.data });
        }, 250);
    },

    stopCamera() {
        clearInterval(this.scanTimer);
        this.scanTimer = null;
        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;
    },
}));

window.Alpine = Alpine;
Alpine.start();
