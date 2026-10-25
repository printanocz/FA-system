/**
 * Body zlomu rozvržení (README design systému, „Rozvržení“):
 * mobil < 760 px, tablet 760–1023 px, desktop ≥ 1024 px.
 *
 * Stejné hodnoty jsou v resources/css/app.css (`--breakpoint-tablet`,
 * `--breakpoint-desktop`), na kterých stojí varianty `tablet:` a `desktop:`.
 * Při změně je nutné upravit obě místa.
 */
export const MOBILE_QUERY = '(width < 760px)';
export const TABLET_QUERY = '(760px <= width < 1024px)';
