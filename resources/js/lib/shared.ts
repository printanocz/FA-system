/** Chyba akce, která neskončila validací. */
export type FlashError = { message: string; href?: string; label?: string };

/** Sdílené props z HandleInertiaRequests, které čtou víc stránek a komponent. */
export type SharedProps = {
    auth: {
        user: { id: number; name: string; email: string } | null;
    };
    status?: string | null;
    error?: string | FlashError | null;
    /** Počet faktur po splatnosti (štítek u „Faktury vydané“ v menu). */
    overdueInvoices?: number;
};
