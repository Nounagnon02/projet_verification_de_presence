import { extraireJetonQr } from '../qr-token';

const JETON = '3f2b8c1e-9d4a-4e7b-8a55-0c1d2e3f4a5b';

describe('extraireJetonQr', () => {
  it("lit le jeton dans l'URL de validation que le web met dans le QR", () => {
    expect(
      extraireJetonQr(`https://presence-uac.vercel.app/attendance/validate?token=${JETON}`),
    ).toBe(JETON);
  });

  it('accepte un jeton nu', () => {
    expect(extraireJetonQr(JETON)).toBe(JETON);
  });

  it('ignore les espaces autour et la casse', () => {
    expect(extraireJetonQr(`  ${JETON.toUpperCase()}\n`)).toBe(JETON);
  });

  it("lit le jeton même s'il n'est pas le premier paramètre", () => {
    expect(
      extraireJetonQr(`https://x.test/attendance/validate?lang=fr&token=${JETON}&v=2`),
    ).toBe(JETON);
  });

  it("refuse un contenu qui n'est ni un UUID ni une URL à jeton", () => {
    expect(extraireJetonQr('bonjour')).toBeNull();
    expect(extraireJetonQr('https://x.test/attendance/validate')).toBeNull();
    expect(extraireJetonQr('https://x.test/?token=pas-un-uuid')).toBeNull();
    expect(extraireJetonQr('')).toBeNull();
  });
});
