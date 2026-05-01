export interface SsrRequestContext {
  cookie?: string;
  host?: string;
  /** http | https — для сборки публичного origin на SSR (как у клиента после strip поддомена игры). */
  protocol?: string;
}
