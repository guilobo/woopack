const metaBaseUrl = document
  .querySelector('meta[name="woopack-base-url"]')
  ?.getAttribute('content')
  ?.replace(/\/$/, '') ?? window.location.origin;

const parsedBaseUrl = new URL(metaBaseUrl, window.location.origin);
const normalizedPath = parsedBaseUrl.pathname.replace(/\/$/, '') || '/';

export const appBaseUrl = parsedBaseUrl.toString().replace(/\/$/, '');
export const routerBasename = normalizedPath === '/' ? undefined : normalizedPath;
