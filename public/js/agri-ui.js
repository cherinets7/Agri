(function () {
    const div = (a, b) => Math.trunc(a / b);

    const EtDate = {
        gregToJdn(y, m, d) {
            const a = div(14 - m, 12);
            const yy = y + 4800 - a;
            const mm = m + 12 * a - 3;
            return d + div(153 * mm + 2, 5) + 365 * yy + div(yy, 4) - div(yy, 100) + div(yy, 400) - 32045;
        },
        fromGregorian(y, m, d) {
            const jdn = this.gregToJdn(y, m, d);
            const r = (jdn - 1723856) % 1461;
            const n = (r % 365) + 365 * div(r, 1460);
            const ey = 4 * div(jdn - 1723856, 1461) + div(r, 365) - div(r, 1460);
            const em = div(n, 30) + 1;
            const ed = (n % 30) + 1;
            return [ey, em, ed];
        },
        toGregorian(ey, em, ed) {
            const jdn = 1723856 + 365 + 365 * (ey - 1) + div(ey, 4) + 30 * em + ed - 31;
            let l = jdn + 68569;
            const n = div(4 * l, 146097);
            l -= div(146097 * n + 3, 4);
            const i = div(4000 * (l + 1), 1461001);
            l = l - div(1461 * i, 4) + 31;
            const j = div(80 * l, 2447);
            const d = l - div(2447 * j, 80);
            l = div(j, 11);
            const m = j + 2 - 12 * l;
            const y = 100 * (n - 49) + i + l;
            return `${String(y).padStart(4, '0')}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        },
        daysInMonth(ey, em) {
            if (em < 13) return 30;
            return ey % 4 === 3 ? 6 : 5;
        },
    };

    function initEthiopianDatePickers() {
        document.querySelectorAll('[data-et-date-picker]').forEach((wrap) => {
            const gInput = wrap.querySelector('[data-et-greg]');
            const mSel = wrap.querySelector('[data-et-month]');
            const dSel = wrap.querySelector('[data-et-day]');
            const ySel = wrap.querySelector('[data-et-year]');
            if (!gInput || !mSel || !dSel || !ySel) return;

            function syncDayOptions(ey, em, selectedDay) {
                const maxDays = EtDate.daysInMonth(ey, em);
                const cur = Math.min(Number(selectedDay || dSel.value || 1), maxDays);
                dSel.innerHTML = '';
                for (let d = 1; d <= maxDays; d++) {
                    const opt = document.createElement('option');
                    opt.value = String(d);
                    opt.textContent = String(d);
                    if (d === cur) opt.selected = true;
                    dSel.appendChild(opt);
                }
            }

            function syncFromGreg() {
                const val = gInput.value;
                if (!/^\d{4}-\d{2}-\d{2}$/.test(val)) return;
                const [y, m, d] = val.split('-').map(Number);
                const [ey, em, ed] = EtDate.fromGregorian(y, m, d);
                if (![...ySel.options].some((o) => Number(o.value) === ey)) {
                    const opt = document.createElement('option');
                    opt.value = String(ey);
                    opt.textContent = `${ey} ዓ/ም`;
                    ySel.appendChild(opt);
                }
                ySel.value = String(ey);
                mSel.value = String(em);
                syncDayOptions(ey, em, ed);
            }

            function syncFromEt() {
                const ey = Number(ySel.value);
                const em = Number(mSel.value);
                syncDayOptions(ey, em, dSel.value);
                const ed = Number(dSel.value);
                const g = EtDate.toGregorian(ey, em, ed);
                if (gInput.max && g > gInput.max) {
                    gInput.value = gInput.max;
                    syncFromGreg();
                    return;
                }
                gInput.value = g;
                gInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            syncFromGreg();
            gInput.addEventListener('change', syncFromGreg);
            ySel.addEventListener('change', syncFromEt);
            mSel.addEventListener('change', syncFromEt);
            dSel.addEventListener('change', syncFromEt);
        });
    }

    function initSignaturePads() {
        document.querySelectorAll('[data-sig-wrap]').forEach((wrap) => {
            const canvas = wrap.querySelector('canvas');
            const hidden = wrap.querySelector('[data-sig-input]');
            const clearBtn = wrap.querySelector('[data-sig-clear]');
            if (!canvas || !canvas.getContext || !hidden) return;
            const ctx = canvas.getContext('2d');
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#1c2a22';
            let drawing = false;
            let dirty = Boolean(hidden.value);

            function pos(e) {
                const r = canvas.getBoundingClientRect();
                const p = e.touches ? e.touches[0] : e;
                return {
                    x: (p.clientX - r.left) * (canvas.width / r.width),
                    y: (p.clientY - r.top) * (canvas.height / r.height),
                };
            }

            function start(e) {
                drawing = true;
                dirty = true;
                const p = pos(e);
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
                e.preventDefault();
            }

            function move(e) {
                if (!drawing) return;
                const p = pos(e);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                e.preventDefault();
            }

            function stop() {
                if (!drawing) return;
                drawing = false;
                if (dirty) hidden.value = canvas.toDataURL('image/png');
            }

            canvas.addEventListener('mousedown', start);
            canvas.addEventListener('mousemove', move);
            window.addEventListener('mouseup', stop);
            canvas.addEventListener('touchstart', start, { passive: false });
            canvas.addEventListener('touchmove', move, { passive: false });
            window.addEventListener('touchend', stop);

            if (clearBtn) {
                clearBtn.addEventListener('click', () => {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    dirty = false;
                    hidden.value = '';
                });
            }
        });
    }

    function initPwaAndOfflineDrafts() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(() => {});
        }

        const banner = document.getElementById('offline-banner');
        function updateOnline() {
            if (!banner) return;
            banner.style.display = navigator.onLine ? 'none' : 'block';
        }
        window.addEventListener('online', updateOnline);
        window.addEventListener('offline', updateOnline);
        updateOnline();

        document.querySelectorAll('form[data-offline-draft]').forEach((form) => {
            const key = 'agri-draft:' + window.location.pathname;
            try {
                const saved = localStorage.getItem(key);
                if (saved) {
                    const data = JSON.parse(saved);
                    Object.entries(data).forEach(([name, val]) => {
                        if (name === '_token' || name === '_method' || name === 'signature_data') return;
                        const els = form.querySelectorAll(`[name="${CSS.escape(name)}"]`);
                        els.forEach((el) => {
                            if (el.type === 'checkbox') {
                                el.checked = Array.isArray(val) ? val.includes(el.value) : Boolean(val);
                            } else if (!el.value) {
                                el.value = val;
                            }
                        });
                    });
                }
            } catch (_) {}

            let timer = null;
            form.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => {
                    const fd = new FormData(form);
                    const out = {};
                    for (const [k, v] of fd.entries()) {
                        if (k === '_token' || k === '_method' || k === 'signature_data') continue;
                        if (k.endsWith('[]')) {
                            out[k] = out[k] || [];
                            out[k].push(v);
                        } else {
                            out[k] = v;
                        }
                    }
                    try { localStorage.setItem(key, JSON.stringify(out)); } catch (_) {}
                }, 400);
            });

            form.addEventListener('submit', () => {
                try { localStorage.removeItem(key); } catch (_) {}
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initEthiopianDatePickers();
        initSignaturePads();
        initPwaAndOfflineDrafts();
    });
})();
