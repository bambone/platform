/**
 * Международный телефон: нормализация (+ и цифры), маска для UI, курсор.
 * Синхронизировать правила с app/Support/Phone/IntlPhoneNormalizer.php и config/intl_phone.php
 */
(function (global) {
    'use strict';

    const COUNTRIES = [
        { key: 'AM', code: '374', nationalMin: 8, nationalMax: 8, priority: 30, example: '+374 91 234567' },
        { key: 'AE', code: '971', nationalMin: 9, nationalMax: 9, priority: 30, example: '+971 50 123 4567' },
        { key: 'DE', code: '49', nationalMin: 10, nationalMax: 11, priority: 20, example: '+49 1512 3456789' },
        { key: 'GB', code: '44', nationalMin: 10, nationalMax: 10, priority: 20, example: '+44 7700 900123' },
        { key: 'RU', code: '7', nationalMin: 10, nationalMax: 10, priority: 10, example: '+7 (999) 123-45-67' },
        { key: 'NANP', code: '1', nationalMin: 10, nationalMax: 10, priority: 5, example: '+1 (415) 555-2671' },
    ];

    function sortedCountries() {
        return [...COUNTRIES].sort((a, b) => {
            if (b.code.length !== a.code.length) {
                return b.code.length - a.code.length;
            }

            return b.priority - a.priority;
        });
    }

    function sanitizePhoneInput(raw) {
        if (raw == null) {
            return '';
        }

        return String(raw)
            .replace(/^tel:/gi, '')
            .trim();
    }

    function digitsOnly(s) {
        const d = String(s).replace(/\D/g, '');

        return d.length > 15 ? d.slice(0, 15) : d;
    }

    function detectCountryByDigits(digitsWithCountry) {
        for (const row of sortedCountries()) {
            if (digitsWithCountry.startsWith(row.code)) {
                return row;
            }
        }

        return null;
    }

    function normalizePhone(raw, _defaultCountry) {
        const s = sanitizePhoneInput(raw);
        if (s === '') {
            return '';
        }

        const hadPlus = s.includes('+');
        const had00 = /^\s*\+?\s*00/.test(s);
        let d = digitsOnly(s);
        if (had00 && d.startsWith('00')) {
            d = d.slice(2);
        }

        if (d === '') {
            return hadPlus ? '+' : '';
        }

        if (hadPlus || had00) {
            return '+' + d.slice(0, 15);
        }

        if (d[0] === '8') {
            d = '7' + d.slice(1);
        }

        if (d.length === 11 && d[0] === '7') {
            return '+' + d.slice(0, 15);
        }

        if (d.length === 11 && d[0] === '1') {
            return '+' + d.slice(0, 15);
        }

        if (d.length === 10) {
            d = '7' + d;
        }

        if (d[0] === '7') {
            return '+' + d.slice(0, 15);
        }

        return '+' + d.slice(0, 15);
    }

    function formatInternationalFallback(allDigits) {
        if (!allDigits) {
            return '+';
        }
        const parts = allDigits.match(/.{1,3}/g) || [];

        return '+' + parts.join(' ');
    }

    function formatRU(nat) {
        const n = nat.replace(/\D/g, '').slice(0, 10);
        if (n.length === 0) {
            return '+7';
        }
        const a = n.slice(0, 3);
        const b = n.slice(3, 6);
        const c = n.slice(6, 8);
        const d = n.slice(8, 10);
        let out = '+7 (' + a;
        if (n.length <= 3) {
            return out;
        }
        out += ') ' + b;
        if (n.length <= 6) {
            return out;
        }
        out += c.length ? '-' + c : '';
        if (n.length <= 8) {
            return out;
        }
        out += d.length ? '-' + d : '';

        return out;
    }

    function formatNANP(nat) {
        const n = nat.replace(/\D/g, '').slice(0, 10);
        if (n.length === 0) {
            return '+1';
        }
        const a = n.slice(0, 3);
        const b = n.slice(3, 6);
        const c = n.slice(6, 10);
        let out = '+1 (' + a;
        if (n.length <= 3) {
            return out;
        }
        out += ') ' + b;
        if (n.length <= 6) {
            return out;
        }
        out += '-' + c;

        return out;
    }

    function formatGB(nat) {
        const n = nat.replace(/\D/g, '').slice(0, 10);
        if (n.length === 0) {
            return '+44';
        }
        if (n.length <= 4) {
            return '+44 ' + n;
        }

        return '+44 ' + n.slice(0, 4) + ' ' + n.slice(4);
    }

    function formatDE(nat) {
        const n = nat.replace(/\D/g, '').slice(0, 11);
        if (n.length === 0) {
            return '+49';
        }
        const parts = n.match(/.{1,3}/g) || [];

        return '+49 ' + parts.join(' ');
    }

    function formatAM(nat) {
        const n = nat.replace(/\D/g, '').slice(0, 8);
        if (n.length === 0) {
            return '+374';
        }
        if (n.length <= 2) {
            return '+374 ' + n;
        }

        return '+374 ' + n.slice(0, 2) + ' ' + n.slice(2);
    }

    function formatAE(nat) {
        const n = nat.replace(/\D/g, '').slice(0, 9);
        if (n.length === 0) {
            return '+971';
        }
        if (n.length <= 2) {
            return '+971 ' + n;
        }
        if (n.length <= 5) {
            return '+971 ' + n.slice(0, 2) + ' ' + n.slice(2);
        }

        return '+971 ' + n.slice(0, 2) + ' ' + n.slice(2, 5) + ' ' + n.slice(5);
    }

    function formatPhoneForDisplay(normalized) {
        if (!normalized || normalized === '+') {
            return '';
        }
        const d = normalized.replace(/\D/g, '').slice(0, 15);
        if (!d) {
            return '';
        }
        const c = detectCountryByDigits(d);
        if (!c) {
            return formatInternationalFallback(d);
        }
        const nat = d.slice(c.code.length);
        switch (c.key) {
            case 'RU':
                return formatRU(nat);
            case 'NANP':
                return formatNANP(nat);
            case 'GB':
                return formatGB(nat);
            case 'DE':
                return formatDE(nat);
            case 'AM':
                return formatAM(nat);
            case 'AE':
                return formatAE(nat);
            default:
                return formatInternationalFallback(d);
        }
    }

    /**
     * Принимает и нормализованный +79…, и «как в поле» (+7 (951) …) — иначе автозаполнение/вставка
     * оставляют красивое значение в input, а Alpine state не обновляется до input-события.
     */
    function validatePhone(value) {
        if (value == null) {
            return false;
        }
        const normalized = normalizePhone(sanitizePhoneInput(String(value)));
        if (!normalized || normalized === '+' || !/^\+[1-9]\d{6,14}$/.test(normalized)) {
            return false;
        }
        const d = normalized.slice(1);
        const c = detectCountryByDigits(d);
        if (!c) {
            return d.length >= 8 && d.length <= 15;
        }
        const nat = d.slice(c.code.length);

        return nat.length >= c.nationalMin && nat.length <= c.nationalMax;
    }

    function phoneHelperHint(normalized) {
        const base = 'Введите номер в международном формате. Для России можно начинать с 8 или +7.';
        if (!normalized || normalized.length < 2) {
            return base;
        }
        const d = normalized.replace(/\D/g, '');
        const c = detectCountryByDigits(d);
        if (c && c.key === 'RU') {
            return 'Формат: +7 (999) 123-45-67';
        }
        if (c) {
            return 'Формат: ' + c.example;
        }

        return 'Формат: +код страны и номер';
    }

    function countDigitsBefore(str, pos) {
        const end = Math.min(pos ?? 0, str.length);
        let n = 0;
        for (let i = 0; i < end; i++) {
            if (/\d/.test(str[i])) {
                n++;
            }
        }

        return n;
    }

    function cursorPosFromDigitIndex(str, digitIndex) {
        if (digitIndex <= 0) {
            return 0;
        }
        let seen = 0;
        for (let i = 0; i < str.length; i++) {
            if (/\d/.test(str[i])) {
                seen++;
                if (seen === digitIndex) {
                    return i + 1;
                }
            }
        }

        return str.length;
    }

    /**
     * @param {HTMLInputElement} el
     * @param {(normalized: string) => void} setNormalized
     */
    function handleInput(el, setNormalized) {
        const v = el.value;
        const start = el.selectionStart ?? 0;
        const digitsBefore = countDigitsBefore(v, start);
        const norm = normalizePhone(v);
        setNormalized(norm);
        const display = formatPhoneForDisplay(norm);
        el.value = display;
        const newPos = cursorPosFromDigitIndex(display, digitsBefore);
        requestAnimationFrame(() => {
            try {
                el.setSelectionRange(newPos, newPos);
            } catch (e) {}
        });
    }

    function syncInputDisplay(el, normalized) {
        const display = formatPhoneForDisplay(normalized || '');
        el.value = display;
    }

    global.TenantIntlPhone = {
        COUNTRIES,
        sanitizePhoneInput,
        digitsOnly,
        detectCountryByDigits,
        normalizePhone,
        formatPhoneForDisplay,
        validatePhone,
        phoneHelperHint,
        handleInput,
        syncInputDisplay,
    };
})(typeof window !== 'undefined' ? window : globalThis);
