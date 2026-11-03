import { Link } from '@inertiajs/react';
import { Diamond, Stack } from '@phosphor-icons/react';
import Modal from '@/Components/Modal';

export default function PilihJenisModal({ open, onClose }: { open: boolean; onClose: () => void }) {
    return (
        <Modal open={open} onClose={onClose} title="Tambah apa?">
            <div className="grid gap-3">
                <Link href="/koleksi/baru/batangan" className="panel flex items-center gap-3 p-4 hover:bg-paper">
                    <Stack size={28} className="text-brand" aria-hidden />
                    <span><span className="block font-medium">Emas batangan</span><span className="block text-sm text-muted">Keping atau lot batangan identik</span></span>
                </Link>
                <Link href="/koleksi/baru/perhiasan" className="panel flex items-center gap-3 p-4 hover:bg-paper">
                    <Diamond size={28} className="text-brand" aria-hidden />
                    <span><span className="block font-medium">Perhiasan</span><span className="block text-sm text-muted">Cincin, kalung, gelang, dan lainnya</span></span>
                </Link>
            </div>
            <button type="button" onClick={onClose} className="btn btn-secondary mt-4 w-full">Tutup</button>
        </Modal>
    );
}
