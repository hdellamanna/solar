/**
 * usePix — helpers for the dedicated PIX UI (FASE 4C).
 *
 * The BR Code generator is a MOCK — it produces a string in the
 * simplified EMV format (00020126...) so the user has a "copy and
 * paste" target to share, but it is NOT a real, scannable BR Code.
 * A real BR Code requires CRC16-CCITT over the payload, merchant
 * category codes, etc. The point of this UI is to demonstrate the
 * flow, not to settle money.
 */

const PIX_KEY_TYPES = {
    cpf: { label: 'CPF',     icon: '🪪', mask: '###.###.###-##', re: /^\d{3}\.\d{3}\.\d{3}-\d{2}$|^\d{11}$/ },
    cnpj: { label: 'CNPJ',   icon: '🏢', mask: '##.###.###/####-##', re: /^\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}$|^\d{14}$/ },
    email: { label: 'E-mail', icon: '📧', re: /^[^\s@]+@[^\s@]+\.[^\s@]+$/ },
    phone: { label: 'Telefone', icon: '📱', re: /^\+?\d{10,13}$/ },
    evp: { label: 'Aleatória', icon: '🔑', re: /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i },
};

/**
 * Classify a raw key into one of the known PIX key types.
 * Returns null if it does not match any known type.
 */
export function classifyPixKey(raw) {
    if (raw === null || raw === undefined) return null;
    const key = String(raw).trim();
    if (!key) return null;

    // Order matters: CPF vs CNPJ vs phone (all digits), then email, then EVP.
    if (/^\+?\d{10,13}$/.test(key.replace(/\D/g, '')) && key.replace(/\D/g, '').length >= 10) {
        return key.replace(/\D/g, '').length === 11 ? 'cpf' : 'phone';
    }
    if (key.replace(/\D/g, '').length === 14) return 'cnpj';
    if (key.includes('@')) return 'email';
    if (PIX_KEY_TYPES.evp.re.test(key)) return 'evp';
    return null;
}

/**
 * Human label + emoji for a key type.
 */
export function pixKeyTypeInfo(type) {
    return PIX_KEY_TYPES[type] || { label: 'Outro', icon: '🔗' };
}

/**
 * Mask a key for safe display (e.g. "ama***@email.com" or "***.***.***-00").
 */
export function maskPixKey(raw) {
    if (!raw) return '';
    const key = String(raw).trim();
    if (key.includes('@')) {
        const [user, domain] = key.split('@');
        const u = user.length <= 3 ? user[0] + '***' : user.slice(0, 3) + '***';
        return `${u}@${domain}`;
    }
    if (key.length <= 8) return '***';
    return key.slice(0, 4) + '***' + key.slice(-4);
}

/**
 * Build a mock BR Code string in the simplified EMV format.
 * The string is intended to LOOK like a real BR Code and be
 * copy-pasteable, but it does NOT include a valid CRC.
 *
 * @param {Object} opts
 * @param {string} opts.key — the PIX key
 * @param {string} opts.type — cpf|cnpj|email|phone|evp
 * @param {number} [opts.amountCents=0] — amount in cents (0 = no amount)
 * @param {string} [opts.txid='***'] — transaction id
 * @param {string} [opts.merchantName='Solar App']
 * @param {string} [opts.merchantCity='Sao Paulo']
 */
export function buildMockBrCode({ key, type, amountCents = 0, txid = '***', merchantName = 'Solar App', merchantCity = 'Sao Paulo' }) {
    const formatId = (id) => String(id).padStart(2, '0');
    const len = (s) => String(s.length).padStart(2, '0');

    // 00 (payload format) + 26 (merchant account info: GUI + key + type)
    const gui = 'BR.GOV.BCB.PIX';
    const typeId = { cpf: '01', cnpj: '02', email: '03', phone: '04', evp: '05' }[type] || '99';
    const merchantAccount = `${len(gui)}${gui}${len(typeId)}${typeId}${len(key)}${key}`;

    // 52 (merchant category) + 53 (currency 986 BRL) + 54 (amount, optional) + 58 (country BR) + 59 (name) + 60 (city) + 62 (addl data, txid) + 63 (CRC placeholder)
    const parts = [
        '000201',                                 // payload format
        `26${len(merchantAccount)}${merchantAccount}`,
        '52040000',                                // category
        '5303986',                                // currency BRL
    ];
    if (amountCents && amountCents > 0) {
        const amount = (amountCents / 100).toFixed(2);
        parts.push(`54${len(amount)}${amount}`);
    }
    parts.push(
        '5802BR',
        `${formatId(59 + len(merchantName))}59${len(merchantName)}${merchantName}`,
        `${formatId(60 + len(merchantCity))}60${len(merchantCity)}${merchantCity}`,
        `62${formatId(4 + len(txid))}05${len(txid)}${txid}`,
        '6304ABCD', // mock CRC placeholder
    );

    return parts.join('');
}

/**
 * Format cents as BRL for the PIX UI.
 */
export function formatCents(cents) {
    if (cents === null || cents === undefined || isNaN(cents)) return 'R$ 0,00';
    return 'R$ ' + (cents / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
