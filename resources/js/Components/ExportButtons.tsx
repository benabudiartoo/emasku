import { FilePdf, FileXls } from '@phosphor-icons/react';

type Props = {
    /** The export path without the format, e.g. `/ekspor/koleksi`. */
    base: string;
    /** Filters to carry into the file (empty values are left out). */
    query?: Record<string, string | undefined>;
    /** Names the data for screen readers, e.g. "koleksi". */
    label: string;
};

/**
 * Two plain download links, one per format. They are real links (not Inertia visits) so the browser saves the file.
 * Files are always complete: "Sembunyikan nominal" only affects what is drawn on screen.
 */
export default function ExportButtons({ base, query = {}, label }: Props) {
    const params = new URLSearchParams();
    Object.entries(query).forEach(([key, value]) => value && params.set(key, value));
    const qs = params.toString() ? `?${params}` : '';

    return (
        <div className="flex flex-wrap gap-2" role="group" aria-label={`Unduh ${label}`}>
            <a href={`${base}.xlsx${qs}`} download className="btn btn-secondary"><FileXls size={18} aria-hidden />Unduh Excel</a>
            <a href={`${base}.pdf${qs}`} download className="btn btn-secondary"><FilePdf size={18} aria-hidden />Unduh PDF</a>
        </div>
    );
}
