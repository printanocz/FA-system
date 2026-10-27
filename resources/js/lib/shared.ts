/** Sdílené props z HandleInertiaRequests, které čtou víc stránek a komponent. */
export type SharedProps = {
    auth: {
        user: { id: number; name: string; email: string } | null;
    };
    status?: string | null;
};
